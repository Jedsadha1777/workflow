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
    
    def _similarity(self, a: str, b: str) -> float:
        """Calculate string similarity (0-1)"""
        return SequenceMatcher(None, a.lower(), b.lower()).ratio()
    
    def _keyword_match(self, query: str, text: str) -> float:
        """Score by keyword overlap"""
        query_words = set(query.lower().split())
        text_words = set(text.lower().split())
        
        if not query_words:
            return 0
        
        common = query_words & text_words
        score = len(common) / len(query_words)
        
        if query.lower() in text.lower() or text.lower() in query.lower():
            score += 0.3
        
        return min(score, 1.0)
    
    def search(self, query: str) -> list[dict]:
        """Search KB for matching Q&A"""
        self.load()
        
        if not self._data or not self._data.get("qa"):
            return []
        
        results = []
        query_lower = query.lower().strip()
        
        for item in self._data["qa"]:
            q = item.get("q", "")
            a = item.get("a", "")
            keywords = item.get("keywords", [])
            
            q_similarity = self._similarity(query_lower, q.lower())
            q_keyword = self._keyword_match(query, q)
            
            kw_score = 0
            for kw in keywords:
                if kw.lower() in query_lower:
                    kw_score = max(kw_score, 0.8)
            
            score = max(q_similarity, q_keyword, kw_score)
            exact = q_similarity > 0.85 or q_keyword > 0.9
            
            if score >= Config.SIMILARITY_THRESHOLD:
                results.append({
                    "q": q,
                    "a": a,
                    "score": round(score, 3),
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
