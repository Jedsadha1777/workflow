"""
Caching services for LLM responses
"""

import asyncio
import hashlib
from typing import Optional

import redis.asyncio as redis

from config import Config


class LLMCache:
    """
    Cache LLM responses with context-aware keys.
    Uses hash(question + context) to prevent semantic collisions.
    """
    
    def __init__(self, redis_client: redis.Redis):
        self.redis = redis_client
    
    def _hash_key(self, question: str, context: str = "") -> str:
        """Create hash key from question + context"""
        q_norm = question.lower().strip()
        c_norm = context[:200].lower().strip() if context else ""
        combined = f"{q_norm}|{c_norm}"
        return f"llm_cache:{hashlib.sha1(combined.encode()).hexdigest()}"
    
    async def get(self, question: str, context: str = "") -> Optional[str]:
        """Get cached answer"""
        try:
            key = self._hash_key(question, context)
            return await asyncio.wait_for(self.redis.get(key), timeout=2.0)
        except Exception:
            return None
    
    async def set(self, question: str, answer: str, context: str = ""):
        """Cache answer"""
        try:
            key = self._hash_key(question, context)
            await asyncio.wait_for(
                self.redis.setex(key, Config.LLM_CACHE_TTL, answer),
                timeout=2.0
            )
        except Exception:
            pass
    
    async def get_stats(self) -> dict:
        """Get cache stats"""
        try:
            keys = await asyncio.wait_for(self.redis.keys("llm_cache:*"), timeout=2.0)
            return {"cached_answers": len(keys)}
        except Exception:
            return {"cached_answers": 0}


class FreeChatCache:
    """Short TTL cache for free chat responses (5 min)"""
    
    def __init__(self, redis_client: redis.Redis):
        self.redis = redis_client
    
    def _hash_question(self, question: str) -> str:
        normalized = question.lower().strip()
        return f"freechat_cache:{hashlib.sha1(normalized.encode()).hexdigest()}"
    
    async def get(self, question: str) -> Optional[str]:
        """Get cached answer"""
        try:
            key = self._hash_question(question)
            return await asyncio.wait_for(self.redis.get(key), timeout=2.0)
        except Exception:
            return None
    
    async def set(self, question: str, answer: str):
        """Cache answer with short TTL"""
        try:
            key = self._hash_question(question)
            await asyncio.wait_for(
                self.redis.setex(key, Config.FREE_CHAT_CACHE_TTL, answer),
                timeout=2.0
            )
        except Exception:
            pass
