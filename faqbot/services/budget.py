"""
Budget Service - Track and limit daily LLM spend
"""

import time
import asyncio

import redis.asyncio as redis

from config import Config


class BudgetService:
    """Track and limit daily LLM spend - FAIL CLOSED if Redis unavailable"""
    
    def __init__(self, redis_client: redis.Redis):
        self.redis = redis_client
    
    def _today_key(self) -> str:
        return f"budget:{time.strftime('%Y-%m-%d')}"
    
    async def get_today_cost(self) -> float:
        """Get today's total cost in USD"""
        try:
            cost = await asyncio.wait_for(self.redis.get(self._today_key()), timeout=2.0)
            return float(cost) if cost else 0.0
        except Exception:
            return 0.0
    
    async def add_cost(self, input_tokens: int, output_tokens: int):
        """Add cost from LLM call"""
        try:
            cost = (input_tokens / 1_000_000) * Config.COST_INPUT_PER_1M
            cost += (output_tokens / 1_000_000) * Config.COST_OUTPUT_PER_1M
            
            key = self._today_key()
            current = await asyncio.wait_for(self.redis.incrbyfloat(key, cost), timeout=2.0)
            
            ttl = await self.redis.ttl(key)
            if ttl == -1:
                await self.redis.expire(key, 86400)
            
            return current
        except Exception:
            return 0.0
    
    async def is_budget_exceeded(self) -> bool:
        """Check if daily budget exceeded - FAIL CLOSED"""
        try:
            cost = await self.get_today_cost()
            return cost >= Config.DAILY_BUDGET_USD
        except Exception:
            print("[WARN] Redis unavailable, blocking LLM (fail closed)")
            return True
    
    async def get_status(self) -> dict:
        """Get budget status"""
        try:
            cost = await self.get_today_cost()
            return {
                "today_cost_usd": round(cost, 4),
                "daily_budget_usd": Config.DAILY_BUDGET_USD,
                "remaining_usd": round(max(0, Config.DAILY_BUDGET_USD - cost), 4),
                "exceeded": cost >= Config.DAILY_BUDGET_USD
            }
        except Exception:
            return {
                "today_cost_usd": 0,
                "daily_budget_usd": Config.DAILY_BUDGET_USD,
                "remaining_usd": 0,
                "exceeded": True
            }
