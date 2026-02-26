"""
Configuration settings from environment variables
"""

import os


class Config:
    # Redis
    REDIS_URL = os.getenv("REDIS_URL", "redis://localhost:6379/0")
    
    # OpenAI (Layer 2 only)
    OPENAI_API_KEY = os.getenv("OPENAI_API_KEY", "")
    MODEL = os.getenv("MODEL", "gpt-4o-mini")
    
    # Rate Limit (per IP)
    RATE_LIMIT_PER_MINUTE = int(os.getenv("RATE_LIMIT_PER_MINUTE", "20"))
    RATE_LIMIT_PER_DAY = int(os.getenv("RATE_LIMIT_PER_DAY", "100"))
    
    # Global Rate Limit (all users combined - protect against distributed attacks)
    GLOBAL_RATE_LIMIT_PER_MINUTE = int(os.getenv("GLOBAL_RATE_LIMIT_PER_MINUTE", "200"))
    
    # Knowledge Base
    KNOWLEDGE_FILE = os.getenv("KNOWLEDGE_FILE", "/data/knowledge/knowledge.json")
    
    # Search Settings
    SIMILARITY_THRESHOLD = float(os.getenv("SIMILARITY_THRESHOLD", "0.7"))
    MAX_SEARCH_RESULTS = int(os.getenv("MAX_SEARCH_RESULTS", "3"))
    
    # LLM Settings
    LLM_TIMEOUT = int(os.getenv("LLM_TIMEOUT", "30"))
    MAX_TOKENS = int(os.getenv("MAX_TOKENS", "500"))
    
    # Cache Settings
    LLM_CACHE_TTL = int(os.getenv("LLM_CACHE_TTL", "3600"))
    FREE_CHAT_CACHE_TTL = int(os.getenv("FREE_CHAT_CACHE_TTL", "300"))  # 5 minutes
    
    # Input Validation
    MAX_QUESTION_LENGTH = int(os.getenv("MAX_QUESTION_LENGTH", "200"))
    MAX_EMOJI_COUNT = int(os.getenv("MAX_EMOJI_COUNT", "3"))
    
    # Daily Budget (USD)
    DAILY_BUDGET_USD = float(os.getenv("DAILY_BUDGET_USD", "10.0"))
    
    # Cost per 1M tokens
    COST_INPUT_PER_1M = 0.15
    COST_OUTPUT_PER_1M = 0.60
    
    # FAISS / Embedding Settings
    EMBEDDING_MODEL = os.getenv("EMBEDDING_MODEL", "text-embedding-3-small")
    EMBEDDING_DIM = int(os.getenv("EMBEDDING_DIM", "1536"))
    FAISS_INDEX_PATH = os.getenv("FAISS_INDEX_PATH", "/data/knowledge/faiss.index")
    USE_FAISS = os.getenv("USE_FAISS", "true").lower() == "true"
    
    # Hybrid Search Weights (FAISS score * weight + keyword score * weight)
    FAISS_WEIGHT = float(os.getenv("FAISS_WEIGHT", "0.7"))
    KEYWORD_WEIGHT = float(os.getenv("KEYWORD_WEIGHT", "0.3"))
    
    # Embedding cost per 1M tokens
    COST_EMBEDDING_PER_1M = 0.02
