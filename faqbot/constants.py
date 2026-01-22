"""
Constants and static data
"""

# Small talk responses (normalized to lowercase keys)
_SMALL_TALK_RAW = {
    "สวัสดี": "สวัสดีครับ มีอะไรให้ช่วยไหมครับ?",
    "สวัสดีครับ": "สวัสดีครับ มีอะไรให้ช่วยไหมครับ?",
    "สวัสดีค่ะ": "สวัสดีค่ะ มีอะไรให้ช่วยไหมคะ?",
    "หวัดดี": "สวัสดีครับ มีอะไรให้ช่วยไหมครับ?",
    "ดี": "สวัสดีครับ มีอะไรให้ช่วยไหมครับ?",
    "hello": "Hello! How can I help you?",
    "hi": "Hi! How can I help you?",
    "hey": "Hey! What can I do for you?",
    "こんにちは": "こんにちは！何かお手伝いできますか？",
    "ありがとう": "どういたしまして！他にご質問はありますか？",
    "ขอบคุณ": "ยินดีครับ มีอะไรให้ช่วยเพิ่มเติมไหมครับ?",
    "ขอบคุณครับ": "ยินดีครับ มีอะไรให้ช่วยเพิ่มเติมไหมครับ?",
    "ขอบคุณค่ะ": "ยินดีค่ะ มีอะไรให้ช่วยเพิ่มเติมไหมคะ?",
    "thanks": "You're welcome! Is there anything else I can help you with?",
    "thank you": "You're welcome! Is there anything else I can help you with?",
}

# Normalize keys to lowercase
SMALL_TALK = {k.lower(): v for k, v in _SMALL_TALK_RAW.items()}
