"""
Pydantic models for request/response schemas
"""

from typing import Optional
from pydantic import BaseModel, Field


class AskRequest(BaseModel):
    question: str = Field(..., min_length=1, max_length=500)


class AskResponse(BaseModel):
    answer: str
    source: Optional[str] = None
    used_llm: bool = False
    cached: bool = False
    score: Optional[float] = None
