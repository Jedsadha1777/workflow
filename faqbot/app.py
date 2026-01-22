"""
FAQ Bot - FastAPI Application
==============================
Routes only - business logic in services/
"""

import os
import time
import asyncio

from fastapi import FastAPI, HTTPException, Request, BackgroundTasks
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import FileResponse

from config import Config
from models import AskRequest, AskResponse
from constants import SMALL_TALK
from utils import get_client_ip, InputValidator
from lifecycle import (
    lifespan,
    get_redis,
    get_llm_service,
    get_faiss_index,
    get_hybrid_search,
    set_hybrid_search,
)
from services import (
    KnowledgeBase,
    FAISSIndex,
    HybridSearch,
    LLMCache,
    FreeChatCache,
    RateLimiter,
    BudgetService,
)


# =============================================================================
# APP
# =============================================================================

app = FastAPI(title="FAQ Bot", lifespan=lifespan)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)


# =============================================================================
# ENDPOINTS
# =============================================================================

@app.post("/api/ask", response_model=AskResponse)
async def ask(req: AskRequest, request: Request):
    """
    Main Q&A endpoint
    Flow: validate → rate limit → small talk → KB → LLM
    """
    redis_client = get_redis()
    llm_service = get_llm_service()
    hybrid_search = get_hybrid_search()
    
    ip = get_client_ip(request)
    question = req.question.strip()
    
    # 1. Input validation
    valid, error = InputValidator.validate(question)
    if not valid:
        raise HTTPException(400, error)
    
    # 2. Rate limit
    rate_limiter = RateLimiter(redis_client)
    allowed, error = await rate_limiter.check(ip, request)
    if not allowed:
        raise HTTPException(429, error)
    
    # 3. Small talk check
    if question.lower() in SMALL_TALK:
        return AskResponse(
            answer=SMALL_TALK[question.lower()],
            used_llm=False
        )
    
    # 4. Short message guard
    if len(question) < 4:
        return AskResponse(
            answer="Hi! How can I help you?",
            used_llm=False
        )
    
    kb = KnowledgeBase.get_instance()
    
    # ===== Layer 1: Direct Search (Hybrid if available) =====
    if hybrid_search and hybrid_search.faiss_index.is_ready():
        direct = await hybrid_search.get_direct_answer(question)
        context = await hybrid_search.get_context_for_llm(question) if not direct else None
    else:
        direct = kb.get_direct_answer(question)
        context = kb.get_context_for_llm(question) if not direct else None
    
    if direct:
        return AskResponse(
            answer=direct["answer"],
            source=direct["source"],
            used_llm=False,
            cached=False,
            score=direct["score"]
        )
    
    # ===== Layer 2: LLM with KB Context =====
    # If no KB context → free chat
    if not context:
        if not llm_service or not llm_service.is_available():
            return AskResponse(
                answer="Sorry, no related information found.",
                used_llm=False
            )
        
        budget_service = BudgetService(redis_client)
        if await budget_service.is_budget_exceeded():
            return AskResponse(
                answer="Sorry, no related information found.",
                used_llm=False
            )
        
        # Check free chat cache
        free_cache = FreeChatCache(redis_client)
        cached_answer = await free_cache.get(question)
        if cached_answer:
            print(f"[TELEMETRY] free_chat cache_hit=true")
            return AskResponse(
                answer=cached_answer,
                used_llm=True,
                cached=True
            )
        
        # Free chat with LLM
        start_time = time.time()
        answer, usage = await llm_service.free_chat(question)
        latency = time.time() - start_time
        
        cost = 0.0
        if usage:
            await budget_service.add_cost(
                usage.get("input_tokens", 0),
                usage.get("output_tokens", 0)
            )
            cost = (usage.get("input_tokens", 0) / 1_000_000) * Config.COST_INPUT_PER_1M
            cost += (usage.get("output_tokens", 0) / 1_000_000) * Config.COST_OUTPUT_PER_1M
        
        await free_cache.set(question, answer)
        
        print(f"[TELEMETRY] free_chat used_llm=true cache_hit=false "
              f"input_tokens={usage.get('input_tokens', 0)} "
              f"output_tokens={usage.get('output_tokens', 0)} "
              f"latency={latency:.2f}s cost=${cost:.6f}")
        
        return AskResponse(
            answer=answer,
            used_llm=True
        )
    
    # Has KB context → summarize with LLM
    if not llm_service or not llm_service.is_available():
        results = kb.search(question)
        if results:
            return AskResponse(
                answer=results[0]["a"],
                source=results[0]["q"],
                used_llm=False,
                score=results[0]["score"]
            )
        return AskResponse(answer="Sorry, no information found.", used_llm=False)
    
    # Check LLM cache (include context)
    llm_cache = LLMCache(redis_client)
    cached_answer = await llm_cache.get(question, context)
    if cached_answer:
        print(f"[TELEMETRY] summarize cache_hit=true")
        return AskResponse(
            answer=cached_answer,
            used_llm=True,
            cached=True
        )
    
    # Check budget
    budget_service = BudgetService(redis_client)
    if await budget_service.is_budget_exceeded():
        results = kb.search(question)
        if results:
            return AskResponse(
                answer=results[0]["a"],
                source=results[0]["q"],
                used_llm=False,
                score=results[0]["score"]
            )
        return AskResponse(
            answer="Sorry, no related information found.",
            used_llm=False
        )
    
    # Call LLM
    start_time = time.time()
    company_info = kb.get_company_info()
    answer, usage = await llm_service.summarize(question, context, company_info)
    latency = time.time() - start_time
    
    cost = 0.0
    if usage:
        await budget_service.add_cost(
            usage.get("input_tokens", 0),
            usage.get("output_tokens", 0)
        )
        cost = (usage.get("input_tokens", 0) / 1_000_000) * Config.COST_INPUT_PER_1M
        cost += (usage.get("output_tokens", 0) / 1_000_000) * Config.COST_OUTPUT_PER_1M
    
    # Cache answer
    await llm_cache.set(question, answer, context)
    
    print(f"[TELEMETRY] summarize used_llm=true cache_hit=false "
          f"input_tokens={usage.get('input_tokens', 0)} "
          f"output_tokens={usage.get('output_tokens', 0)} "
          f"latency={latency:.2f}s cost=${cost:.6f}")
    
    return AskResponse(
        answer=answer,
        used_llm=True,
        cached=False
    )


@app.get("/api/search")
async def search(q: str, request: Request, mode: str = "hybrid"):
    """Search KB (for debugging/testing)"""
    redis_client = get_redis()
    faiss_index = get_faiss_index()
    hybrid_search = get_hybrid_search()
    
    ip = get_client_ip(request)
    
    rate_limiter = RateLimiter(redis_client)
    allowed, error = await rate_limiter.check(ip, request)
    if not allowed:
        raise HTTPException(429, error)
    
    kb = KnowledgeBase.get_instance()
    
    if mode == "hybrid" and hybrid_search and hybrid_search.faiss_index.is_ready():
        results = await hybrid_search.search(q)
    elif mode == "faiss" and faiss_index and faiss_index.is_ready():
        results = await faiss_index.search(q)
    else:
        results = kb.search(q)
    
    return {
        "query": q,
        "mode": mode,
        "faiss_ready": faiss_index.is_ready() if faiss_index else False,
        "results": results
    }


@app.get("/api/status")
async def status(request: Request):
    """Get system status"""
    redis_client = get_redis()
    llm_service = get_llm_service()
    faiss_index = get_faiss_index()
    
    ip = get_client_ip(request)
    
    rate_limiter = RateLimiter(redis_client)
    remaining = await rate_limiter.get_remaining(ip)
    
    budget_service = BudgetService(redis_client)
    budget = await budget_service.get_status()
    
    llm_cache = LLMCache(redis_client)
    cache_stats = await llm_cache.get_stats()
    
    return {
        "rate_limits": remaining,
        "budget": budget,
        "cache": cache_stats,
        "llm_available": llm_service.is_available() if llm_service else False,
        "llm_enabled": (llm_service.is_available() if llm_service else False) and not budget["exceeded"],
        "model": Config.MODEL if llm_service and llm_service.is_available() else None,
        "faiss": {
            "enabled": Config.USE_FAISS,
            "ready": faiss_index.is_ready() if faiss_index else False,
            "vectors": faiss_index.index.ntotal if faiss_index and faiss_index.is_ready() else 0
        },
        "search_weights": {
            "faiss": Config.FAISS_WEIGHT,
            "keyword": Config.KEYWORD_WEIGHT
        }
    }


@app.get("/api/kb/reload")
async def reload_kb():
    """Force reload knowledge base"""
    kb = KnowledgeBase.get_instance()
    kb.load(force=True)
    return {"status": "reloaded", "count": len(kb._data.get("qa", []))}


@app.post("/api/faiss/rebuild")
async def rebuild_faiss(background_tasks: BackgroundTasks):
    """Rebuild FAISS index in background"""
    redis_client = get_redis()
    
    if not Config.USE_FAISS:
        raise HTTPException(400, "FAISS is disabled")
    
    if not Config.OPENAI_API_KEY:
        raise HTTPException(400, "OpenAI API key not configured")
    
    kb = KnowledgeBase.get_instance()
    kb.load(force=True)
    qa_list = kb.get_all_qa()
    
    if not qa_list:
        raise HTTPException(400, "No Q&A items in knowledge base")
    
    async def do_rebuild():
        faiss_idx = FAISSIndex.get_instance()
        faiss_idx.set_redis(redis_client)
        await faiss_idx.build_async(qa_list)
        set_hybrid_search(HybridSearch(kb, faiss_idx))
        print(f"[INFO] FAISS rebuild complete: {faiss_idx.index.ntotal} vectors")
    
    background_tasks.add_task(asyncio.create_task, do_rebuild())
    
    return {
        "status": "rebuilding",
        "message": "Index rebuild started in background",
        "items": len(qa_list)
    }


@app.get("/")
async def index():
    """Serve frontend"""
    return FileResponse("index.html")


# =============================================================================
# RUN
# =============================================================================

if __name__ == "__main__":
    import uvicorn
    port = int(os.getenv("PORT", 8000))
    print(f"[INFO] FAQ Bot starting on http://localhost:{port}")
    uvicorn.run(app, host="0.0.0.0", port=port)
