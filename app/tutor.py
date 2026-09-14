import os
import requests
from .knowledge import KnowledgeBase

GROQ_API_KEY = os.getenv("GROQ_API_KEY", "")
GROQ_MODEL = os.getenv("GROQ_MODEL", "openai/gpt-oss-20b")
GROQ_URL = "https://api.groq.com/openai/v1/chat/completions"
OLLAMA_URL = os.getenv("OLLAMA_URL", "http://127.0.0.1:11434/api/generate")
OLLAMA_MODEL = os.getenv("OLLAMA_MODEL", "qwen2.5:3b")

BASE_RULES = '''You are Chalo Padhaye, a warm Maharashtra State Board tutor.
The school medium is English. Keep textbook terms and school vocabulary in English, but explain in simple Hindi/Hinglish when helpful.
Use ONLY the supplied textbook context for syllabus facts. If context is insufficient, clearly say so instead of inventing facts.
Teach one concept at a time. Ask exactly one short question at the end. Wait for the child to answer.
Do not reveal the answer before the child attempts it. If the child is wrong, give a small hint first.
Keep the language age-appropriate, encouraging, concise, and easy to understand.'''


class Tutor:
    def __init__(self):
        self.kb = KnowledgeBase()

    def _system(self, student_name: str, grade: int, subject: str):
        grade_rules = (
            "For Grade 3, use very short sentences, simple examples, and concrete everyday comparisons."
            if grade == 3
            else "For Grade 5, explain clearly with slightly more detail while staying child-friendly."
        )
        return (
            f"{BASE_RULES}\nThe student is {student_name}, Grade {grade}, English medium. "
            f"Current subject is {subject}. {grade_rules}"
        )

    def _groq_reply(self, system: str, prompt: str):
        r = requests.post(
            GROQ_URL,
            headers={
                "Authorization": f"Bearer {GROQ_API_KEY}",
                "Content-Type": "application/json",
            },
            json={
                "model": GROQ_MODEL,
                "messages": [
                    {"role": "system", "content": system},
                    {"role": "user", "content": prompt},
                ],
                "temperature": 0.25,
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

    def reply(self, message: str, student_name: str = "Usman", grade: int = 5, subject: str = "evs1"):
        hits = self.kb.search(message, 4, grade=grade, subject=subject)
        context = "\n\n".join(
            f"SOURCE {h['source']}:\n{h['text'][:2600]}" for h in hits
        )
        if not context:
            context = "No relevant textbook passage could be loaded for this request."

        system = self._system(student_name, grade, subject)
        prompt = f"TEXTBOOK CONTEXT:\n{context}\n\nCHILD: {message}\nTUTOR:"
        sources = [h["source"] for h in hits]

        if GROQ_API_KEY:
            try:
                text = self._groq_reply(system, prompt)
                return {
                    "text": text,
                    "sources": sources,
                    "model": GROQ_MODEL,
                    "provider": "groq",
                }
            except Exception as e:
                groq_error = str(e)
        else:
            groq_error = "GROQ_API_KEY is not configured"

        try:
            text = self._ollama_reply(f"{system}\n\n{prompt}")
            return {
                "text": text,
                "sources": sources,
                "model": OLLAMA_MODEL,
                "provider": "ollama",
            }
        except Exception as e:
            return {
                "text": "Cloud AI is not available right now. Please try again shortly.",
                "sources": sources,
                "error": f"Groq: {groq_error}; Ollama: {e}",
            }
