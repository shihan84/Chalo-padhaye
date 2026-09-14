import os
import requests
from .knowledge import KnowledgeBase

GROQ_API_KEY = os.getenv("GROQ_API_KEY", "")
GROQ_MODEL = os.getenv("GROQ_MODEL", "llama-3.1-8b-instant")
GROQ_URL = "https://api.groq.com/openai/v1/chat/completions"
OLLAMA_URL = os.getenv("OLLAMA_URL", "http://127.0.0.1:11434/api/generate")
OLLAMA_MODEL = os.getenv("OLLAMA_MODEL", "qwen2.5:3b")

SYSTEM = '''You are Chalo Padhaye, a warm Maharashtra State Board tutor.
The school medium is English. Explain in simple Hindi/Hinglish while preserving important textbook terms in English.
Use ONLY the supplied textbook context for syllabus facts. If context is insufficient, clearly say that the relevant textbook material is needed.
Teach one concept at a time. Ask exactly one short question at the end. Do not reveal the answer before the child attempts it.
If the child is wrong, first give a small hint. Keep language age-appropriate, encouraging, and concise.'''


class Tutor:
    def __init__(self):
        self.kb = KnowledgeBase()

    def _prompt(self, message, context):
        return f"{SYSTEM}\n\nTEXTBOOK CONTEXT:\n{context}\n\nCHILD: {message}\nTUTOR:"

    def _groq_reply(self, prompt):
        r = requests.post(
            GROQ_URL,
            headers={
                "Authorization": f"Bearer {GROQ_API_KEY}",
                "Content-Type": "application/json",
            },
            json={
                "model": GROQ_MODEL,
                "messages": [
                    {"role": "system", "content": SYSTEM},
                    {"role": "user", "content": prompt},
                ],
                "temperature": 0.3,
                "max_tokens": 700,
            },
            timeout=60,
        )
        r.raise_for_status()
        return r.json()["choices"][0]["message"]["content"].strip()

    def _ollama_reply(self, prompt):
        r = requests.post(
            OLLAMA_URL,
            json={"model": OLLAMA_MODEL, "prompt": prompt, "stream": False},
            timeout=120,
        )
        r.raise_for_status()
        return r.json()["response"].strip()

    def reply(self, message: str):
        hits = self.kb.search(message, 4)
        context = "\n\n".join(
            f"SOURCE {h['source']}:\n{h['text'][:3000]}" for h in hits
        )
        if not context:
            context = "No textbook pages indexed yet."
        prompt = self._prompt(message, context)
        sources = [h["source"] for h in hits]

        if GROQ_API_KEY:
            try:
                text = self._groq_reply(prompt)
                return {"text": text, "sources": sources, "model": GROQ_MODEL, "provider": "groq"}
            except Exception as e:
                groq_error = str(e)
        else:
            groq_error = "GROQ_API_KEY is not configured"

        try:
            text = self._ollama_reply(prompt)
            return {"text": text, "sources": sources, "model": OLLAMA_MODEL, "provider": "ollama"}
        except Exception as e:
            return {
                "text": "Cloud AI is not available right now. Please try again shortly.",
                "sources": sources,
                "error": f"Groq: {groq_error}; Ollama: {e}",
            }
