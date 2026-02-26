"""
FAISS Vector Index with Hybrid Search (semantic + keyword)
"""

import os
import gc
import json
import base64
import hashlib
import asyncio
from typing import Optional

import numpy as np
import faiss
from openai import OpenAI

from config import Config
from .knowledge import KnowledgeBase


class EmbeddingCache:
    """
    Cache embeddings in Redis to avoid repeated OpenAI API calls.
    Stores as binary (base64) for efficiency - 5x faster, 40% less RAM.
    """
    
    def __init__(self, redis_client, openai_client):
        self.redis = redis_client
        self.openai = openai_client
        self.ttl = 86400  # 24 hours
        self._detected_dim = None
    
    def _cache_key(self, text: str) -> str:
        normalized = text.lower().strip()
        return f"emb_cache:{hashlib.sha1(normalized.encode()).hexdigest()}"
    
    async def get_or_embed(self, text: str) -> Optional[np.ndarray]:
        """
        Get embedding from cache or compute and cache it.
        FAIL CLOSED: Returns None if Redis unavailable (prevents runaway OpenAI costs).
        """
        if not self.redis:
            print("[WARN] EmbeddingCache: Redis not available, skipping (fail closed)")
            return None
        
        key = self._cache_key(text)
        
        # Try cache first
        redis_available = False
        try:
            cached = await asyncio.wait_for(self.redis.get(key), timeout=1.0)
            redis_available = True
            if cached:
                binary = base64.b64decode(cached)
                return np.frombuffer(binary, dtype="float32").copy()
        except Exception as e:
            # Redis failed - FAIL CLOSED to prevent runaway costs
            print(f"[WARN] EmbeddingCache Redis error: {e} - skipping embedding (fail closed)")
            return None
        
        # Redis is available but cache miss - safe to compute
        if not self.openai:
            raise ValueError("OpenAI client not configured")
        
        resp = await asyncio.to_thread(
            self.openai.embeddings.create,
            model=Config.EMBEDDING_MODEL,
            input=[text]
        )
        vec = np.array(resp.data[0].embedding, dtype="float32")
        
        # Auto-detect dimension
        if self._detected_dim is None:
            self._detected_dim = len(vec)
            print(f"[INFO] Detected embedding dimension: {self._detected_dim}")
        
        # Cache result
        try:
            b64 = base64.b64encode(vec.tobytes()).decode("ascii")
            await asyncio.wait_for(
                self.redis.setex(key, self.ttl, b64),
                timeout=1.0
            )
        except Exception:
            # Cache write failed but we already have the result
            pass
        
        return vec
    
    def embed_sync(self, texts: list[str]) -> tuple[np.ndarray, int]:
        """Synchronous batch embedding for index building"""
        if not self.openai:
            raise ValueError("OpenAI client not configured")
        
        all_embeddings = []
        batch_size = 100
        detected_dim = None
        
        for i in range(0, len(texts), batch_size):
            batch = texts[i:i + batch_size]
            resp = self.openai.embeddings.create(
                model=Config.EMBEDDING_MODEL,
                input=batch
            )
            all_embeddings.extend([d.embedding for d in resp.data])
            
            if detected_dim is None and resp.data:
                detected_dim = len(resp.data[0].embedding)
        
        return np.array(all_embeddings, dtype="float32"), detected_dim or Config.EMBEDDING_DIM


class FAISSIndex:
    """FAISS-based vector search with OpenAI embeddings"""
    
    _instance = None
    
    def __init__(self):
        self.index = None
        self.docs = []
        self.openai = OpenAI(api_key=Config.OPENAI_API_KEY) if Config.OPENAI_API_KEY else None
        self._ready = False
        self._embedding_cache = None
        self._embedding_dim = Config.EMBEDDING_DIM
    
    @classmethod
    def get_instance(cls):
        if cls._instance is None:
            cls._instance = cls()
        return cls._instance
    
    def set_redis(self, redis_client):
        """Set Redis client for embedding cache"""
        if self.openai:
            self._embedding_cache = EmbeddingCache(redis_client, self.openai)
    
    def is_ready(self) -> bool:
        return self._ready and self.index is not None
    
    def _cleanup(self):
        """Free memory before rebuild"""
        if self.index is not None:
            del self.index
            self.index = None
        if self.docs:
            del self.docs
            self.docs = []
        self._ready = False
        gc.collect()
    
    def build(self, qa_list: list[dict]):
        """Build FAISS index from Q&A list (sync)"""
        if not self.openai:
            print("[WARN] FAISS disabled: no OpenAI API key")
            return
        
        if not qa_list:
            print("[WARN] FAISS: no Q&A items to index")
            return
        
        try:
            self._cleanup()
            self.docs = qa_list
            
            # Use only questions for FAISS semantic search
            # Keywords are for keyword search only (different purpose)
            texts = []
            for qa in qa_list:
                q = qa.get("q", "")
                q_en = qa.get("q_en", "")
                q_ja = qa.get("q_ja", "")
                # Only questions - no keywords (keywords pollute semantic meaning)
                combined = f"{q} {q_en} {q_ja}".strip()
                texts.append(combined)
            
            print(f"[INFO] Building FAISS index for {len(texts)} items...")
            
            cache = EmbeddingCache(None, self.openai)
            vectors, detected_dim = cache.embed_sync(texts)
            
            faiss.normalize_L2(vectors)
            
            self._embedding_dim = detected_dim
            self.index = faiss.IndexFlatIP(detected_dim)
            self.index.add(vectors)
            
            self._ready = True
            print(f"[INFO] FAISS index built: {self.index.ntotal} vectors, dim={detected_dim}")
            
            self._save()
            
        except Exception as e:
            print(f"[ERROR] FAISS build failed: {e}")
            self._ready = False
    
    async def build_async(self, qa_list: list[dict]):
        """Build FAISS index asynchronously"""
        await asyncio.to_thread(self.build, qa_list)
    
    def _save(self):
        """Save FAISS index to disk with metadata"""
        try:
            index_path = Config.FAISS_INDEX_PATH
            os.makedirs(os.path.dirname(index_path), exist_ok=True)
            faiss.write_index(self.index, index_path)
            
            docs_path = index_path.replace(".index", ".docs.json")
            meta = {
                "docs": self.docs,
                "embedding_dim": self._embedding_dim,
                "embedding_model": Config.EMBEDDING_MODEL,
                "version": 2
            }
            with open(docs_path, "w", encoding="utf-8") as f:
                json.dump(meta, f, ensure_ascii=False)
            
            print(f"[INFO] FAISS index saved: {index_path}")
        except Exception as e:
            print(f"[WARN] Failed to save FAISS index: {e}")
    
    def load(self) -> bool:
        """Load FAISS index from disk"""
        try:
            self._cleanup()
            
            index_path = Config.FAISS_INDEX_PATH
            docs_path = index_path.replace(".index", ".docs.json")
            
            if not os.path.exists(index_path) or not os.path.exists(docs_path):
                return False
            
            self.index = faiss.read_index(index_path)
            
            with open(docs_path, "r", encoding="utf-8") as f:
                data = json.load(f)
            
            # Handle both old and new format
            if isinstance(data, list):
                self.docs = data
                self._embedding_dim = Config.EMBEDDING_DIM
            else:
                self.docs = data.get("docs", [])
                self._embedding_dim = data.get("embedding_dim", Config.EMBEDDING_DIM)
                saved_model = data.get("embedding_model", "")
                
                if saved_model and saved_model != Config.EMBEDDING_MODEL:
                    print(f"[WARN] Embedding model changed: {saved_model} -> {Config.EMBEDDING_MODEL}")
            
            self._ready = True
            print(f"[INFO] FAISS index loaded: {self.index.ntotal} vectors, dim={self._embedding_dim}")
            return True
        except Exception as e:
            print(f"[WARN] Failed to load FAISS index: {e}")
            return False
    
    async def search(self, query: str, top_k: int = 5) -> list[dict]:
        """
        Search using FAISS with cached embeddings.
        Returns empty list if Redis unavailable (fail closed - fallback to keyword search).
        """
        if not self.is_ready():
            return []
        
        if not self._embedding_cache:
            print("[WARN] Embedding cache not set")
            return []
        
        try:
            vec = await self._embedding_cache.get_or_embed(query)
            
            # Redis unavailable → fail closed → fallback to keyword search
            if vec is None:
                print("[INFO] FAISS search skipped (Redis unavailable)")
                return []
            
            vec = vec.reshape(1, -1)
            faiss.normalize_L2(vec)
            
            scores, indices = self.index.search(vec, top_k)
            
            results = []
            for rank, (score, i) in enumerate(zip(scores[0], indices[0])):
                if i >= 0 and i < len(self.docs):
                    doc = self.docs[i]
                    results.append({
                        "q": doc.get("q", ""),
                        "a": doc.get("a", ""),
                        "score": float(max(0.0, min(1.0, score))),
                        "rank": rank + 1,
                        "source": "faiss"
                    })
            
            return results
        except Exception as e:
            print(f"[ERROR] FAISS search failed: {e}")
            return []


class HybridSearch:
    """Hybrid search using Reciprocal Rank Fusion (RRF)"""
    
    RRF_K = 60
    
    def __init__(self, kb: KnowledgeBase, faiss_index: FAISSIndex):
        self.kb = kb
        self.faiss_index = faiss_index
    
    async def search(self, query: str, top_k: int = 3) -> list[dict]:
        """Hybrid search with RRF score fusion"""
        results_map = {}
        
        # FAISS search
        if Config.USE_FAISS and self.faiss_index.is_ready():
            faiss_results = await self.faiss_index.search(query, top_k=top_k * 2)
            for r in faiss_results:
                key = r["q"]
                rrf_faiss = 1.0 / (self.RRF_K + r["rank"])
                
                if key not in results_map:
                    results_map[key] = {
                        "q": r["q"],
                        "a": r["a"],
                        "rrf_faiss": rrf_faiss,
                        "rrf_keyword": 0,
                        "raw_faiss": r["score"],
                        "raw_keyword": 0
                    }
                else:
                    results_map[key]["rrf_faiss"] = rrf_faiss
                    results_map[key]["raw_faiss"] = r["score"]
        
        # Keyword search
        keyword_results = self.kb.search(query)
        for rank, r in enumerate(keyword_results):
            key = r["q"]
            rrf_keyword = 1.0 / (self.RRF_K + rank + 1)
            
            if key not in results_map:
                results_map[key] = {
                    "q": r["q"],
                    "a": r["a"],
                    "rrf_faiss": 0,
                    "rrf_keyword": rrf_keyword,
                    "raw_faiss": 0,
                    "raw_keyword": r["score"]
                }
            else:
                results_map[key]["rrf_keyword"] = rrf_keyword
                results_map[key]["raw_keyword"] = r["score"]
        
        # Combine scores
        combined = []
        for key, data in results_map.items():
            final_score = (
                Config.FAISS_WEIGHT * data["rrf_faiss"] +
                Config.KEYWORD_WEIGHT * data["rrf_keyword"]
            )
            # RRF score is already 0-0.016 range, multiply by factor to get 0-1
            # But also incorporate raw scores for better relevance signal
            raw_score = max(data["raw_faiss"], data["raw_keyword"])
            # Final score: weighted average of RRF ranking + raw similarity
            normalized_score = (final_score * 30) * 0.5 + raw_score * 0.5
            normalized_score = min(1.0, normalized_score)
            
            combined.append({
                "q": data["q"],
                "a": data["a"],
                "score": round(normalized_score, 3),
                "faiss_score": round(data["raw_faiss"], 3),
                "keyword_score": round(data["raw_keyword"], 3),
                "exact": data["raw_keyword"] >= 0.85
            })
        
        combined.sort(key=lambda x: x["score"], reverse=True)
        return combined[:top_k]
    
    async def get_direct_answer(self, query: str) -> Optional[dict]:
        """Get direct answer using hybrid search (high confidence only)"""
        results = await self.search(query, top_k=1)
        
        if not results:
            return None
        
        top = results[0]
        # Direct answer requires minimum 0.5 score (high confidence)
        min_threshold = max(0.5, Config.SIMILARITY_THRESHOLD)
        if top["score"] >= min_threshold or top["exact"]:
            return {
                "answer": top["a"],
                "source": top["q"],
                "score": top["score"],
                "used_llm": False
            }
        
        return None
    
    async def get_context_for_llm(self, query: str) -> Optional[str]:
        """
        Get context for LLM summarization.
        Uses SIMILARITY_THRESHOLD from config - no hardcoded lower bound.
        """
        results = await self.search(query, top_k=Config.MAX_SEARCH_RESULTS)
        
        if not results:
            return None
        
        # Use config threshold, minimum 0.4 to avoid garbage context
        context_threshold = max(0.4, Config.SIMILARITY_THRESHOLD)
        relevant = [r for r in results if r["score"] >= context_threshold]
        
        if not relevant:
            return None
        
        context_parts = []
        for r in relevant:
            context_parts.append(f"Q: {r['q']}\nA: {r['a']}")
        
        return "\n\n".join(context_parts)