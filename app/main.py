import os
from pathlib import Path
from typing import Optional

import requests
from fastapi import FastAPI, Header, HTTPException, Response
from fastapi.staticfiles import StaticFiles
from pydantic import BaseModel
from .tutor import Tutor

ROOT = Path(__file__).resolve().parents[1]
app = FastAPI(title="Chalo Padhaye")
tutor = Tutor()
SUPABASE_URL = os.getenv("NEXT_PUBLIC_SUPABASE_URL") or os.getenv("SUPABASE_URL", "")
SUPABASE_ANON_KEY = os.getenv("NEXT_PUBLIC_SUPABASE_ANON_KEY") or os.getenv("SUPABASE_ANON_KEY", "")
FISH_AUDIO_API_KEY = os.getenv("FISH_AUDIO_API_KEY", "")
FISH_AUDIO_VOICE_ID = os.getenv("FISH_AUDIO_VOICE_ID", "4f021a03744e46628e3653d0b26c959e")
FISH_AUDIO_MODEL = os.getenv("FISH_AUDIO_MODEL", "s2.1-pro-free")


class ChatIn(BaseModel):
    message: str
    student_id: str
    subject: str = "evs1"
    session_id: Optional[str] = None


class TTSIn(BaseModel):
    text: str


def _headers(token: str, prefer: Optional[str] = None):
    h = {"apikey": SUPABASE_ANON_KEY, "Authorization": f"Bearer {token}", "Content-Type": "application/json"}
    if prefer:
        h["Prefer"] = prefer
    return h


def _token(authorization: Optional[str]) -> str:
    if not authorization or not authorization.lower().startswith("bearer "):
        raise HTTPException(401, "Parent login required")
    return authorization.split(" ", 1)[1].strip()


def _student(token: str, student_id: str):
    if not SUPABASE_URL or not SUPABASE_ANON_KEY:
        raise HTTPException(503, "Supabase is not configured")
    r = requests.get(f"{SUPABASE_URL}/rest/v1/students", headers=_headers(token), params={"id": f"eq.{student_id}", "select": "id,full_name,grade,medium,board"}, timeout=10)
    if not r.ok:
        raise HTTPException(401, "Could not verify student")
    rows = r.json()
    if not rows:
        raise HTTPException(403, "Student is not available to this parent")
    return rows[0]


def _insert(token: str, table: str, payload: dict):
    r = requests.post(f"{SUPABASE_URL}/rest/v1/{table}", headers=_headers(token, "return=representation"), json=payload, timeout=10)
    if not r.ok:
        raise HTTPException(502, f"Could not save {table}")
    rows = r.json()
    return rows[0] if rows else None


@app.get("/api/config")
def config():
    return {"supabase_url": SUPABASE_URL, "supabase_anon_key": SUPABASE_ANON_KEY, "custom_voice": bool(FISH_AUDIO_API_KEY and FISH_AUDIO_VOICE_ID)}


@app.get("/api/health")
def health():
    return {"ok": True, "indexed_chunks": len(tutor.kb.docs), "custom_voice": bool(FISH_AUDIO_API_KEY and FISH_AUDIO_VOICE_ID)}


@app.post("/api/tts")
def tts(data: TTSIn, authorization: Optional[str] = Header(default=None)):
    _token(authorization)
    if not FISH_AUDIO_API_KEY or not FISH_AUDIO_VOICE_ID:
        raise HTTPException(503, "Custom tutor voice is not configured")
    text = data.text.replace("**", "").strip()
    if not text:
        raise HTTPException(400, "Text is required")
    if len(text) > 1800:
        text = text[:1800]
    r = requests.post(
        "https://api.fish.audio/v1/tts",
        headers={"Authorization": f"Bearer {FISH_AUDIO_API_KEY}", "Content-Type": "application/json", "model": FISH_AUDIO_MODEL},
        json={"text": text, "reference_id": FISH_AUDIO_VOICE_ID, "format": "mp3"},
        timeout=60,
    )
    if not r.ok:
        raise HTTPException(502, f"Tutor voice generation failed ({r.status_code})")
    return Response(content=r.content, media_type="audio/mpeg", headers={"Cache-Control": "no-store"})


@app.post("/api/chat")
def chat(data: ChatIn, authorization: Optional[str] = Header(default=None)):
    token = _token(authorization)
    student = _student(token, data.student_id)
    session_id = data.session_id
    if not session_id:
        session = _insert(token, "tutor_sessions", {"student_id": student["id"], "subject": data.subject})
        session_id = session["id"]
    else:
        r = requests.get(f"{SUPABASE_URL}/rest/v1/tutor_sessions", headers=_headers(token), params={"id": f"eq.{session_id}", "student_id": f"eq.{student['id']}", "select": "id"}, timeout=10)
        if not r.ok or not r.json():
            raise HTTPException(403, "Session is not available")

    _insert(token, "tutor_messages", {"session_id": session_id, "role": "student", "message": data.message})
    answer = tutor.reply(data.message, student_name=student["full_name"], grade=int(student["grade"]), subject=data.subject)
    if answer.get("text"):
        _insert(token, "tutor_messages", {"session_id": session_id, "role": "tutor", "message": answer["text"]})
    answer["session_id"] = session_id
    return answer


app.mount("/", StaticFiles(directory=ROOT / "frontend", html=True), name="frontend")
