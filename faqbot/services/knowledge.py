"""
Knowledge Base - JSON-based Q&A storage with keyword + fuzzy search
"""

import os
import json
from typing import Optional
from difflib import SequenceMatcher

from config import Config


class KnowledgeBase:
    """Simple JSON-based Knowledge Base with keyword + fuzzy search"""
    
    _instance = None
    _data = None
    _last_loaded = None
    
    @classmethod
    def get_instance(cls):
        if cls._instance is None:
            cls._instance = cls()
        return cls._instance
    
    def load(self, force: bool = False):
        """Load knowledge.json"""
        filepath = Config.KNOWLEDGE_FILE
        
        if not os.path.exists(filepath):
            print(f"[WARN] Knowledge file not found: {filepath}")
            self._data = {"qa": [], "company_info": {}}
            return
        
        mtime = os.path.getmtime(filepath)
        if not force and self._last_loaded and mtime <= self._last_loaded:
            return
        
        try:
            with open(filepath, 'r', encoding='utf-8') as f:
                self._data = json.load(f)
            self._last_loaded = mtime
            print(f"[INFO] Loaded KB: {len(self._data.get('qa', []))} items")
        except Exception as e:
            print(f"[ERROR] Failed to load KB: {e}")
            self._data = {"qa": [], "company_info": {}}
    
    # Thai + English stopwords (common words that don't carry meaning)
    STOPWORDS = {
        # Thai
        "มี", "ไหม", "ที่", "ได้", "ไป", "มา", "จะ", "ว่า", "ให้", "ของ", "และ", "หรือ",
        "ไม่", "เป็น", "อยู่", "นี้", "นั้น", "ก็", "แล้ว", "กับ", "จาก", "ใน", "บน",
        "คือ", "ทำ", "ยัง", "ถ้า", "เมื่อ", "อะไร", "ทำไม", "อย่างไร", "เท่าไหร่",
        "ครับ", "ค่ะ", "คะ", "นะ", "สิ", "ล่ะ", "หน่อย",
        # English
        "a", "an", "the", "is", "are", "was", "were", "be", "been", "being",
        "have", "has", "had", "do", "does", "did", "will", "would", "could", "should",
        "may", "might", "can", "to", "of", "in", "for", "on", "with", "at", "by",
        "from", "as", "into", "through", "during", "before", "after", "above", "below",
        "it", "its", "this", "that", "these", "those", "i", "you", "he", "she", "we", "they",
        "what", "which", "who", "whom", "how", "when", "where", "why",
    }
    
    def _similarity(self, a: str, b: str) -> float:
        """Disabled - fuzzy match causes too many false positives"""
        # SequenceMatcher matches "มีแมวไหม" to "บริษัทมีมาตรฐาน ISO อะไรไหม"
        # because of common substrings "มี" and "ไหม"
        # Use FAISS for semantic similarity instead
        return 0.0
    
    def _keyword_match(self, query: str, text: str) -> float:
        """
        Score by keyword overlap.
        For Thai: only match explicit keywords from KB, not substrings.
        This function is now mostly disabled - rely on FAISS for semantic.
        """
        # Disable loose matching - too many false positives with Thai
        # Only FAISS semantic + explicit keywords array should match
        return 0.0
    
    def search(self, query: str) -> list[dict]:
        """Search KB - explicit keywords only, FAISS handles semantic"""
        self.load()
        
        if not self._data or not self._data.get("qa"):
            return []
        
        results = []
        query_lower = query.lower().strip()
        
        for item in self._data["qa"]:
            q = item.get("q", "")
            a = item.get("a", "")
            keywords = item.get("keywords", [])
            
            # ONLY match explicit keywords from JSON
            # Keywords should be specific: "iso9001", "มาตรฐานiso" not just "iso"
            kw_score = 0
            for kw in keywords:
                kw_lower = kw.lower()
                # Skip if keyword is just repeated chars like "มีมีมี"
                unique_chars = len(set(kw_lower))
                if unique_chars < 3:
                    continue
                # Require keyword length >= 5 AND unique chars >= 3
                if len(kw_lower) >= 5 and kw_lower in query_lower:
                    kw_score = max(kw_score, 0.8)
            
            # Exact question match only
            exact = False
            if query_lower == q.lower().strip():
                kw_score = 1.0
                exact = True
            
            if kw_score >= Config.SIMILARITY_THRESHOLD:
                results.append({
                    "q": q,
                    "a": a,
                    "score": round(kw_score, 3),
                    "exact": exact
                })
        
        results.sort(key=lambda x: x["score"], reverse=True)
        return results[:Config.MAX_SEARCH_RESULTS]
    
    def get_direct_answer(self, query: str) -> Optional[dict]:
        """Try to get a direct answer (exact match)"""
        results = self.search(query)
        
        if not results:
            return None
        
        top = results[0]
        if top["exact"] and top["score"] >= 0.7:
            return {
                "answer": top["a"],
                "source": top["q"],
                "score": top["score"],
                "used_llm": False
            }
        
        return None
    
    def get_context_for_llm(self, query: str) -> Optional[str]:
        """Get context from KB for LLM to summarize/combine"""
        results = self.search(query)
        
        if not results:
            return None
        
        context_parts = []
        for r in results:
            context_parts.append(f"Q: {r['q']}\nA: {r['a']}")
        
        return "\n\n".join(context_parts)
    
    def get_company_info(self) -> dict:
        self.load()
        return self._data.get("company_info", {})
    
    def get_all_qa(self) -> list[dict]:
        """Get all Q&A items for FAISS indexing"""
        self.load()
        return self._data.get("qa", [])