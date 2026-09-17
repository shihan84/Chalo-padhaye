import os
import requests
from .knowledge import KnowledgeBase

GROQ_API_KEY = os.getenv("GROQ_API_KEY", "")
GROQ_MODEL = os.getenv("GROQ_MODEL", "openai/gpt-oss-20b")
GROQ_URL = "https://api.groq.com/openai/v1/chat/completions"
OLLAMA_URL = os.getenv("OLLAMA_URL", "http://127.0.0.1:11434/api/generate")
OLLAMA_MODEL = os.getenv("OLLAMA_MODEL", "qwen2.5:3b")

BASE_RULES = '''You are Chalo Padhaye, a patient, warm, interactive tutor for a child.
The school medium is English. Keep textbook terms and school vocabulary in English, but explain in simple natural Hindi/Hinglish when helpful.
Use ONLY the supplied textbook context for syllabus facts. If context is insufficient, clearly say so instead of inventing facts.

TEACH LIKE A HUMAN TUTOR, NOT LIKE AN ARTICLE OR LECTURE:
- Teach only ONE small concept per turn.
- Usually speak in 2 to 5 short sentences before the question.
- Use one simple everyday example when useful.
- Ask EXACTLY ONE short question at the end, then STOP and wait for the child.
- Never answer your own question in the same turn.
- Never reveal the answer before the child attempts it.
- Use the recent conversation to understand whether the child is answering your previous question.
- If the answer is correct: praise briefly, reinforce why in one sentence, then move to the next small step with one question.
- If the first answer is wrong: do NOT give the answer. Give one small hint and ask one question again.
- If the child is still struggling after repeated attempts: explain gently with a tiny example, then ask one easier checking question.
- Avoid long headings, bullet-heavy lectures, repeated summaries, and robotic phrases.
- Prefer natural spoken phrasing and punctuation because your response will be read aloud.
- Do not say multiple questions joined together.
- Keep encouragement genuine and brief.
'''


class Tutor:
    def __init__(self):
        self.kb = KnowledgeBase()

    def _system(self, student_name: str, grade: int, subject: str, curriculum: str = "maharashtra"):
        grade_rules = (
            "For Grade 3 / NIOS Level A, use very short sentences, concrete objects, and very easy checking questions."
            if grade == 3
            else "For Grade 5 / NIOS Level B, explain clearly with a little more detail, but still one step at a time."
        )
        curriculum_name = "NIOS Open Basic Education" if curriculum == "nios" else "Maharashtra State Board"
        return (
            f"{BASE_RULES}\nThe student is {student_name}, Grade {grade}, English medium. "
            f"Current curriculum is {curriculum_name}. Current subject is {subject}. {grade_rules}"
        )

    def _groq_reply(self, messages):
        r = requests.post(
            GROQ_URL,
            headers={"Authorization": f"Bearer {GROQ_API_KEY}", "Content-Type": "application/json"},
            json={"model": GROQ_MODEL, "messages": messages, "temperature": 0.2, "max_tokens": 380},
            timeout=60,
        )
        r.raise_for_status()
        return r.json()["choices"][0]["message"]["content"].strip()

    def _ollama_reply(self, prompt):
        r = requests.post(OLLAMA_URL, json={"model": OLLAMA_MODEL, "prompt": prompt, "stream": False}, timeout=120)
        r.raise_for_status()
        return r.json()["response"].strip()

    def reply(self, message: str, student_name: str = "Usman", grade: int = 5, subject: str = "evs1", curriculum: str = "maharashtra", history=None):
        hits = self.kb.search(message, 4, grade=grade, subject=subject, curriculum=curriculum)
        context = "\n\n".join(f"SOURCE {h['source']}:\n{h['text'][:2400]}" for h in hits)
        if not context:
            context = "No relevant textbook passage could be loaded for this request."

        system = self._system(student_name, grade, subject, curriculum)
        messages = [{"role": "system", "content": system}]
        for item in (history or [])[-6:]:
            role = "assistant" if item.get("role") == "tutor" else "user"
            messages.append({"role": role, "content": item.get("message", "")[:1800]})
        messages.append({"role": "user", "content": f"TEXTBOOK CONTEXT:\n{context}\n\nCHILD'S NEW MESSAGE: {message}"})
        sources = [h["source"] for h in hits]

        if GROQ_API_KEY:
            try:
                text = self._groq_reply(messages)
                return {"text": text, "sources": sources, "model": GROQ_MODEL, "provider": "groq"}
            except Exception as e:
                groq_error = str(e)
        else:
            groq_error = "GROQ_API_KEY is not configured"

        prompt = "\n\n".join(f"{m['role'].upper()}: {m['content']}" for m in messages)
        try:
            text = self._ollama_reply(prompt)
            return {"text": text, "sources": sources, "model": OLLAMA_MODEL, "provider": "ollama"}
        except Exception as e:
            return {"text": "Cloud AI is not available right now. Please try again shortly.", "sources": sources, "error": f"Groq: {groq_error}; Ollama: {e}"}
