import os, requests
from .knowledge import KnowledgeBase

OLLAMA_URL = os.getenv('OLLAMA_URL', 'http://127.0.0.1:11434/api/generate')
OLLAMA_MODEL = os.getenv('OLLAMA_MODEL', 'qwen2.5:3b')

SYSTEM = '''You are Chalo Padhaye, a warm Class 5 Maharashtra State Board tutor for Usman Ali.
The school medium is English. Explain in simple Hindi/Hinglish while preserving important textbook terms in English.
Use ONLY the supplied textbook context for syllabus facts. If context is insufficient, say you need the relevant textbook page.
Teach one concept at a time. Ask exactly one short question at the end. Do not reveal the answer before the child attempts it.
If the child is wrong, first give a small hint. Keep language age-appropriate, encouraging, and concise.'''

class Tutor:
    def __init__(self):
        self.kb = KnowledgeBase()

    def reply(self, message: str):
        hits = self.kb.search(message, 4)
        context = '\n\n'.join(f"SOURCE {h['source']}:\n{h['text'][:3000]}" for h in hits)
        if not context:
            context = 'No textbook pages indexed yet.'
        prompt = f"{SYSTEM}\n\nTEXTBOOK CONTEXT:\n{context}\n\nCHILD: {message}\nTUTOR:"
        try:
            r = requests.post(OLLAMA_URL, json={'model': OLLAMA_MODEL, 'prompt': prompt, 'stream': False}, timeout=120)
            r.raise_for_status()
            return {'text': r.json()['response'].strip(), 'sources': [h['source'] for h in hits], 'model': OLLAMA_MODEL}
        except Exception as e:
            return {'text': 'Local AI model is not ready yet. Please start Ollama, then try again.', 'sources': [h['source'] for h in hits], 'error': str(e)}
