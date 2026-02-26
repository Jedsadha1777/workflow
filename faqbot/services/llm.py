"""
LLM Service - OpenAI integration for summarization and free chat
"""

import asyncio
from openai import OpenAI

from config import Config


class LLMService:
    """OpenAI LLM service with hallucination guard"""
    
    def __init__(self):
        self.client = OpenAI(api_key=Config.OPENAI_API_KEY) if Config.OPENAI_API_KEY else None
    
    def is_available(self) -> bool:
        return self.client is not None
    
    async def summarize(self, query: str, context: str, company_info: dict) -> tuple[str, dict]:
        """Use LLM to summarize/rephrase answer from KB context"""
        if not self.client:
            return "Sorry, please try again.", {}
        
        company_name = company_info.get("name", "")
        
        system_prompt = f"""You are a female customer service assistant for {company_name if company_name else 'the company'}.
Use only the provided information to answer. Do not make up information.
If there is insufficient information, say you don't know.
Be concise, polite, and professional.
When answering in Thai:
- Use "คะ" for questions.
- Use "ค่ะ" to end statements.
Never use "ครับ".
Maintain a professional customer-service tone."""

        user_prompt = f"""Question: {query}

Related information:
{context}

Please answer the question using the information above:"""

        try:
            response = await asyncio.wait_for(
                asyncio.to_thread(
                    self.client.chat.completions.create,
                    model=Config.MODEL,
                    messages=[
                        {"role": "system", "content": system_prompt},
                        {"role": "user", "content": user_prompt}
                    ],
                    max_tokens=Config.MAX_TOKENS,
                    temperature=0.3,
                ),
                timeout=Config.LLM_TIMEOUT
            )
            
            usage = {
                "input_tokens": response.usage.prompt_tokens,
                "output_tokens": response.usage.completion_tokens
            }
            
            return response.choices[0].message.content.strip(), usage
        except asyncio.TimeoutError:
            return "Sorry, please try again.", {}
        except Exception as e:
            print(f"[ERROR] LLM: {e}")
            return "Sorry, please try again.", {}
    
    async def free_chat(self, question: str) -> tuple[str, dict]:
        """Free chat without KB context - with hallucination guard"""
        if not self.client:
            return "Sorry, please try again.", {}
        
        system_prompt = """You are a helpful assistant. Answer concisely in the same language as the question.

IMPORTANT RULES:
- If you are not sure, say you don't know.
- Do not invent real-world facts (names, dates, statistics, etc).
- Do not provide medical, legal, or financial advice.
- Keep answers brief and helpful."""
        
        try:
            response = await asyncio.wait_for(
                asyncio.to_thread(
                    self.client.chat.completions.create,
                    model=Config.MODEL,
                    messages=[
                        {"role": "system", "content": system_prompt},
                        {"role": "user", "content": question}
                    ],
                    max_tokens=200,
                    temperature=0.7,
                ),
                timeout=Config.LLM_TIMEOUT
            )
            
            usage = {
                "input_tokens": response.usage.prompt_tokens,
                "output_tokens": response.usage.completion_tokens
            }
            
            return response.choices[0].message.content.strip(), usage
        except asyncio.TimeoutError:
            return "Sorry, please try again.", {}
        except Exception as e:
            print(f"[ERROR] LLM free_chat: {e}")
            return "Sorry, please try again.", {}
