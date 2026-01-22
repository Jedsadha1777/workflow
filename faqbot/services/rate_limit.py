"""
Rate Limiting - Multi-layer protection (IP + fingerprint + global)
"""

import time
import asyncio
import hashlib

import redis.asyncio as redis
from fastapi import Request

from config import Config


class RateLimiter:
    """
    Multi-layer rate limiting:
    1. IP-based (basic)
    2. Fingerprint-based (User-Agent + Accept-Language hash)
    3. Global limit (protect against distributed attacks)
    """
    
    def __init__(self, redis_client: redis.Redis):
        self.redis = redis_client
    
    def _get_fingerprint(self, request: Request) -> str:
        """Create fingerprint from request headers"""
        ua = request.headers.get("User-Agent", "")
        lang = request.headers.get("Accept-Language", "")
        fp = hashlib.md5(f"{ua}:{lang}".encode()).hexdigest()[:12]
        return fp
    
    async def check(self, ip: str, request: Request = None) -> tuple[bool, str]:
        """Check rate limit by IP + fingerprint + global"""
        try:
            now = int(time.time())
            
            # Layer 1: IP-based rate limit
            minute_key = f"rate:min:{ip}:{now // 60}"
            day_key = f"rate:day:{ip}:{now // 86400}"
            
            minute_count = await asyncio.wait_for(self.redis.incr(minute_key), timeout=2.0)
            if minute_count == 1:
                await self.redis.expire(minute_key, 60)
            
            if minute_count > Config.RATE_LIMIT_PER_MINUTE:
                return False, "Please wait a moment and try again."
            
            day_count = await asyncio.wait_for(self.redis.incr(day_key), timeout=2.0)
            if day_count == 1:
                await self.redis.expire(day_key, 86400)
            
            if day_count > Config.RATE_LIMIT_PER_DAY:
                return False, "Daily limit reached. Please try again tomorrow."
            
            # Layer 2: Fingerprint-based
            if request:
                fp = self._get_fingerprint(request)
                fp_key = f"rate:fp:{fp}:{now // 60}"
                fp_count = await asyncio.wait_for(self.redis.incr(fp_key), timeout=2.0)
                if fp_count == 1:
                    await self.redis.expire(fp_key, 60)
                
                if fp_count > Config.RATE_LIMIT_PER_MINUTE * 2:
                    return False, "Please wait a moment and try again."
            
            # Layer 3: Global rate limit
            global_key = f"rate:global:{now // 60}"
            global_count = await asyncio.wait_for(self.redis.incr(global_key), timeout=2.0)
            if global_count == 1:
                await self.redis.expire(global_key, 60)
            
            if global_count > Config.GLOBAL_RATE_LIMIT_PER_MINUTE:
                print(f"[WARN] Global rate limit hit: {global_count}/min")
                return False, "Service is busy. Please try again shortly."
            
            return True, ""
        except Exception as e:
            print(f"[WARN] Rate limit Redis error: {e}")
            return False, "Service temporarily unavailable."
    
    async def get_remaining(self, ip: str) -> dict:
        """Get remaining quota"""
        try:
            now = int(time.time())
            minute_key = f"rate:min:{ip}:{now // 60}"
            day_key = f"rate:day:{ip}:{now // 86400}"
            
            minute_used = int(await asyncio.wait_for(self.redis.get(minute_key), timeout=2.0) or 0)
            day_used = int(await asyncio.wait_for(self.redis.get(day_key), timeout=2.0) or 0)
            
            return {
                "minute": {"used": minute_used, "max": Config.RATE_LIMIT_PER_MINUTE},
                "day": {"used": day_used, "max": Config.RATE_LIMIT_PER_DAY}
            }
        except Exception:
            return {
                "minute": {"used": 0, "max": Config.RATE_LIMIT_PER_MINUTE},
                "day": {"used": 0, "max": Config.RATE_LIMIT_PER_DAY}
            }
