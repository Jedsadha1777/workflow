"""
Utility functions
"""

import re
from fastapi import Request

from config import Config


def get_client_ip(request: Request) -> str:
    """Get client IP from request (supports Cloudflare, Nginx, Load Balancer)"""
    for header in ["CF-Connecting-IP", "X-Real-IP", "X-Forwarded-For"]:
        if request.headers.get(header):
            return request.headers[header].split(",")[0].strip()
    return request.client.host or "unknown"


class InputValidator:
    """Validate and sanitize user input"""
    
    EMOJI_PATTERN = re.compile(
        "["
        "\U0001F600-\U0001F64F"
        "\U0001F300-\U0001F5FF"
        "\U0001F680-\U0001F6FF"
        "\U0001F1E0-\U0001F1FF"
        "\U00002702-\U000027B0"
        "\U000024C2-\U0001F251"
        "]+", 
        flags=re.UNICODE
    )
    
    @classmethod
    def validate(cls, question: str) -> tuple[bool, str]:
        """Validate question - return generic user-friendly messages"""
        if len(question) > Config.MAX_QUESTION_LENGTH:
            return False, "Please enter a shorter question."
        
        emoji_count = len(cls.EMOJI_PATTERN.findall(question))
        if emoji_count > Config.MAX_EMOJI_COUNT:
            return False, "Please use fewer emojis."
        
        dangerous = ["<script", "javascript:", "onclick", "onerror"]
        if any(d in question.lower() for d in dangerous):
            return False, "Invalid input."
        
        return True, ""
    
    @classmethod
    def is_injection_attempt(cls, text: str) -> bool:
        """Check for prompt injection patterns"""
        patterns = [
            "ignore previous",
            "ignore above",
            "disregard",
            "forget your instructions",
            "new instructions",
            "system prompt",
        ]
        text_lower = text.lower()
        return any(p in text_lower for p in patterns)
