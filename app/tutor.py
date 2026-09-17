import json
import os
import re
import requests
from .knowledge import KnowledgeBase

GROQ_API_KEY = os.getenv("GROQ_API_KEY", "")
GROQ_MODEL = os.getenv("GROQ_MODEL", "openai/gpt-oss-20b")
GROQ_URL = "https://api.groq.com/openai/v1/chat/completions"
OLLAMA_URL = os.getenv("OLLAMA_URL", "http://127.0.0.1:11434/api/generate")
OLLAMA_MODEL = os.getenv("OLLAMA_MODEL", "qwen2.5:3b")

BASE_RULES = '''You are Chalo Padhaye, a patient, warm, interactive tutor for a child.
The school medium is English. Keep textbook terms and school vocabulary in English, but explain in simple natural Hindi/Hinglish when helpful.
Use ONLY the supplied lesson context for syllabus facts. If context is insufficient, clearly say the lesson material could not be loaded instead of inventing facts.

TEACH LIKE A HUMAN TUTOR, NOT LIKE AN ARTICLE OR LECTURE:
- Teach only ONE small concept per turn.
- Usually use 2 to 5 short spoken sentences before the question.
- Use one simple everyday example when useful.
- Ask EXACTLY ONE short question at the end, then STOP and wait.
- Never answer your own question in the same turn.
- Never reveal the answer before the child attempts it.
- Use the recent conversation to understand whether the child is answering your previous question.
- If the answer is correct: praise briefly, reinforce why in one sentence, then move to the next small step with one question.
- If the first answer is wrong: do NOT give the answer. Give one small hint and ask one easier or rephrased question.
- If the child is still struggling after repeated attempts: explain gently with one tiny example, then ask one easier checking question.
- Avoid long headings, bullet-heavy lectures, repeated summaries, and robotic phrases.
- Prefer natural punctuation and short clauses because your response will be read aloud.
- Do not ask two questions in one turn.
- Keep encouragement genuine and brief.
- Never shame, pressure, compare siblings, or use marks as punishment.
- Do not encourage the child to browse the open web or contact strangers. Keep activities age-appropriate and parent-safe.
'''

ACTIVITY_RULES = {
    "teach": "Teach a tiny concept, give one example if useful, then ask one checking question.",
    "practice": "Keep explanation minimal. Give one practice item at a time. After an answer, give brief feedback and the next single item.",
    "quiz": "Act like a gentle oral quiz. Ask only one question at a time. Do not teach before the first question unless the child asks for help.",
    "revision": "Give a very short recap of one previously relevant concept, then ask one recall question.",
    "reading": "Use a short passage or idea from the supplied lesson context. Help with meaning, fluency, vocabulary, or comprehension one step at a time. Ask only one reading/comprehension question.",
    "project": "Turn the lesson into one simple age-appropriate hands-on project using ordinary household materials when possible. Give only the NEXT step, not the entire project at once, and ask one short check-in question.",
}

JSON_RULES = '''Return ONLY valid JSON with this shape:
{
  "reply": "the child-facing tutor response",
  "assessment": "correct|partial|incorrect|not_answer",
  "topic": "short topic name",
  "confidence": 0.0
}
Use assessment=not_answer when the child's message is a new question/request rather than an attempt to answer your previous question.
Do not put markdown fences around the JSON.'''


class Tutor:
    def __init__(self):
        self.kb = KnowledgeBase()

    def _system(self, student_name: str, grade: int, subject: str, curriculum: str = "maharashtra", activity: str = "teach"):
        grade_rules = (
            "For Grade 3 / NIOS Level A, use very short sentences, concrete objects, and very easy checking questions."
            if grade == 3
            else "For Grade 5 / NIOS Level B, explain clearly with a little more detail, but still one step at a time."
        )
        curriculum_name = "NIOS Open Basic Education homeschool track" if curriculum == "nios" else "Maharashtra State Board school track"
        activity_rule = ACTIVITY_RULES.get(activity, ACTIVITY_RULES["teach"])
        return (
            f"{BASE_RULES}\nThe student is {student_name}, Grade {grade}, English medium. "
            f"Current curriculum is {curriculum_name}. Current subject is {subject}. {grade_rules}\n"
            f"Current activity mode: {activity}. {activity_rule}\n{JSON_RULES}"
        )

    def _groq_reply(self, messages):
        r = requests.post(
            GROQ_URL,
            headers={"Authorization": f"Bearer {GROQ_API_KEY}", "Content-Type": "application/json"},
            json={"model": GROQ_MODEL, "messages": messages, "temperature": 0.15, "max_tokens": 380},
            timeout=60,
        )
        r.raise_for_status()
        return r.json()["choices"][0]["message"]["content"].strip()

    def _ollama_reply(self, prompt):
        r = requests.post(OLLAMA_URL, json={"model": OLLAMA_MODEL, "prompt": prompt, "stream": False}, timeout=120)
        r.raise_for_status()
        return r.json()["response"].strip()

    def _parse(self, raw: str):
        cleaned = raw.strip()
        cleaned = re.sub(r"^```(?:json)?\s*", "", cleaned, flags=re.I)
        cleaned = re.sub(r"\s*```$", "", cleaned)
        try:
            data = json.loads(cleaned)
            if isinstance(data, dict) and data.get("reply"):
                assessment = str(data.get("assessment", "not_answer")).lower()
                if assessment not in {"correct", "partial", "incorrect", "not_answer"}:
                    assessment = "not_answer"
                try:
                    confidence = max(0.0, min(1.0, float(data.get("confidence", 0.0))))
                except Exception:
                    confidence = 0.0
                return {
                    "text": str(data.get("reply", "")).strip(),
                    "assessment": assessment,
                    "topic": str(data.get("topic", "General")).strip()[:120] or "General",
                    "confidence": confidence,
                }
        except Exception:
            pass
        return {"text": raw.strip(), "assessment": "not_answer", "topic": "General", "confidence": 0.0}

    def reply(
        self,
        message: str,
        student_name: str = "Usman",
        grade: int = 5,
        subject: str = "evs1",
        curriculum: str = "maharashtra",
        activity: str = "teach",
        history=None,
    ):
        history = history or []
        last_tutor = next((item.get("message", "") for item in reversed(history) if item.get("role") == "tutor"), "")
        retrieval_query = f"{last_tutor[-900:]}\n{message}" if last_tutor else message
        hits = self.kb.search(retrieval_query, 4, grade=grade, subject=subject, curriculum=curriculum)
        context = "\n\n".join(f"SOURCE {h['source']}:\n{h['text'][:2400]}" for h in hits)
        if not context:
            context = "No relevant lesson material could be loaded for this request. Do not invent syllabus facts."

        system = self._system(student_name, grade, subject, curriculum, activity)
        messages = [{"role": "system", "content": system}]
        for item in history[-8:]:
            role = "assistant" if item.get("role") == "tutor" else "user"
            messages.append({"role": role, "content": item.get("message", "")[:1800]})
        messages.append({"role": "user", "content": f"LESSON CONTEXT:\n{context}\n\nCHILD'S NEW MESSAGE: {message}"})
        sources = [h["source"] for h in hits]
        supplemental = any(bool(h.get("supplemental")) for h in hits)

        if GROQ_API_KEY:
            try:
                parsed = self._parse(self._groq_reply(messages))
                parsed.update({"sources": sources, "model": GROQ_MODEL, "provider": "groq", "supplemental": supplemental})
                return parsed
            except Exception as e:
                groq_error = str(e)
        else:
            groq_error = "GROQ_API_KEY is not configured"

        prompt = "\n\n".join(f"{m['role'].upper()}: {m['content']}" for m in messages)
        try:
            parsed = self._parse(self._ollama_reply(prompt))
            parsed.update({"sources": sources, "model": OLLAMA_MODEL, "provider": "ollama", "supplemental": supplemental})
            return parsed
        except Exception as e:
            return {
                "text": "Cloud AI is not available right now. Please try again shortly.",
                "sources": sources,
                "assessment": "not_answer",
                "topic": "General",
                "confidence": 0.0,
                "error": f"Groq: {groq_error}; Ollama: {e}",
            }
