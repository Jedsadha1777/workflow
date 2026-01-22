"""
Application Lifecycle - Initialize Redis, KB, FAISS, LLM
"""

import os
import asyncio
from contextlib import asynccontextmanager
from typing import Optional

import redis.asyncio as redis
from fastapi import FastAPI

from config import Config
from services import (
    KnowledgeBase,
    FAISSIndex,
    HybridSearch,
    LLMService,
)

# Global instances
redis_client: Optional[redis.Redis] = None
llm_service: Optional[LLMService] = None
faiss_index: Optional[FAISSIndex] = None
hybrid_search: Optional[HybridSearch] = None


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Application lifespan - startup and shutdown"""
    global redis_client, llm_service, faiss_index, hybrid_search
    
    # Initialize Redis
    if os.getenv("USE_FAKE_REDIS"):
        import fakeredis.aioredis
        redis_client = fakeredis.aioredis.FakeRedis(decode_responses=True)
        print("[WARN] Using FakeRedis (for testing only)")
    else:
        redis_client = redis.from_url(Config.REDIS_URL, decode_responses=True)
    
    # Initialize LLM Service
    llm_service = LLMService()
    if llm_service.is_available():
        print(f"[INFO] LLM ready: {Config.MODEL}")
    else:
        print("[WARN] LLM not configured (OPENAI_API_KEY missing)")
    
    # Load Knowledge Base
    kb = KnowledgeBase.get_instance()
    kb.load()
    
    # Initialize FAISS index
    if Config.USE_FAISS and Config.OPENAI_API_KEY:
        faiss_index = FAISSIndex.get_instance()
        faiss_index.set_redis(redis_client)
        
        # Try to load existing index first (fast startup)
        index_loaded = faiss_index.load()
        
        if index_loaded:
            hybrid_search = HybridSearch(kb, faiss_index)
            print(f"[INFO] Hybrid search ready (FAISS loaded: {faiss_index.index.ntotal} vectors)")
        else:
            qa_list = kb.get_all_qa()
            if qa_list:
                if len(qa_list) < 100:
                    # Small KB - build sync
                    print(f"[INFO] Building FAISS index ({len(qa_list)} items)...")
                    await faiss_index.build_async(qa_list)
                    hybrid_search = HybridSearch(kb, faiss_index)
                    print(f"[INFO] Hybrid search ready (FAISS built: {faiss_index.index.ntotal} vectors)")
                else:
                    # Large KB - build in background
                    print(f"[INFO] Large KB ({len(qa_list)} items) - starting background FAISS build")
                    hybrid_search = None
                    
                    async def background_build():
                        global hybrid_search
                        await faiss_index.build_async(qa_list)
                        hybrid_search = HybridSearch(kb, faiss_index)
                        print(f"[INFO] Background FAISS build complete: {faiss_index.index.ntotal} vectors")
                    
                    asyncio.create_task(background_build())
            else:
                print("[WARN] No Q&A items in KB, FAISS disabled")
    else:
        print("[INFO] FAISS disabled, using keyword search only")
    
    yield
    
    # Shutdown
    await redis_client.close()


def get_redis():
    """Get Redis client instance"""
    return redis_client


def get_llm_service():
    """Get LLM service instance"""
    return llm_service


def get_faiss_index():
    """Get FAISS index instance"""
    return faiss_index


def get_hybrid_search():
    """Get hybrid search instance"""
    return hybrid_search


def set_hybrid_search(hs):
    """Set hybrid search instance (for rebuild)"""
    global hybrid_search
    hybrid_search = hs
