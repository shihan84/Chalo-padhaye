import os
import re
from datetime import datetime, timezone
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
FISH_AUDIO_VOICE_ID = os.getenv("FISH_AUDIO_VOICE_ID", "")
FISH_AUDIO_MODEL = os.getenv("FISH_AUDIO_MODEL", "s2.1-pro-free")


class ChatIn(BaseModel):
    message: str
    student_id: str
    subject: str = "evs1"
    curriculum: str = "maharashtra"
    activity: str = "teach"
    session_id: Optional[str] = None


class TTSIn(BaseModel):
    text: str


class EndSessionIn(BaseModel):
    student_id: str
    session_id: str


def _headers(token: str, prefer: Optional[str] = None):
    h = {
        "apikey": SUPABASE_ANON_KEY,
        "Authorization": f"Bearer {token}",
        "Content-Type": "application/json",
    }
    if prefer:
        h["Prefer"] = prefer
    return h


def _token(authorization: Optional[str]) -> str:
    if not authorization or not authorization.lower().startswith("bearer "):
        raise HTTPException(401, "Parent login required")
    return authorization.split(" ", 1)[1].strip()


def _require_user(token: str):
    if not SUPABASE_URL or not SUPABASE_ANON_KEY:
        raise HTTPException(503, "Supabase is not configured")
    r = requests.get(f"{SUPABASE_URL}/auth/v1/user", headers=_headers(token), timeout=10)
    if not r.ok:
        raise HTTPException(401, "Parent session expired")
    return r.json()


def _student(token: str, student_id: str):
    if not SUPABASE_URL or not SUPABASE_ANON_KEY:
        raise HTTPException(503, "Supabase is not configured")
    r = requests.get(
        f"{SUPABASE_URL}/rest/v1/students",
        headers=_headers(token),
        params={"id": f"eq.{student_id}", "select": "id,full_name,grade,medium,board"},
        timeout=10,
    )
    if not r.ok:
        raise HTTPException(401, "Could not verify student")
    rows = r.json()
    if not rows:
        raise HTTPException(403, "Student is not available to this parent")
    return rows[0]


def _insert(token: str, table: str, payload: dict):
    r = requests.post(
        f"{SUPABASE_URL}/rest/v1/{table}",
        headers=_headers(token, "return=representation"),
        json=payload,
        timeout=10,
    )
    if not r.ok:
        raise HTTPException(502, f"Could not save {table}")
    rows = r.json()
    return rows[0] if rows else None


def _patch(token: str, table: str, params: dict, payload: dict, quiet: bool = False):
    headers = _headers(token, "return=representation")
    r = requests.patch(f"{SUPABASE_URL}/rest/v1/{table}", headers=headers, params=params, json=payload, timeout=10)
    if not r.ok:
        if quiet:
            return None
        raise HTTPException(502, f"Could not update {table}")
    try:
        rows = r.json()
        return rows[0] if rows else None
    except Exception:
        return None


def _history(token: str, session_id: str, limit: int = 9):
    r = requests.get(
        f"{SUPABASE_URL}/rest/v1/tutor_messages",
        headers=_headers(token),
        params={
            "session_id": f"eq.{session_id}",
            "select": "role,message,created_at",
            "order": "created_at.desc",
            "limit": str(limit),
        },
        timeout=10,
    )
    if not r.ok:
        return []
    return list(reversed(r.json()))


def _update_progress(token: str, student_id: str, curriculum: str, subject: str, answer: dict):
    assessment = answer.get("assessment", "not_answer")
    topic = (answer.get("topic") or "General").strip()[:120]
    if assessment not in {"correct", "partial", "incorrect"}:
        return None

    subject_key = f"{curriculum}:{subject}"
    r = requests.get(
        f"{SUPABASE_URL}/rest/v1/student_progress",
        headers=_headers(token),
        params={
            "student_id": f"eq.{student_id}",
            "subject": f"eq.{subject_key}",
            "topic": f"eq.{topic}",
            "select": "id,score,attempts,status,topic,subject,updated_at",
            "order": "updated_at.desc",
            "limit": "1",
        },
        timeout=10,
    )
    rows = r.json() if r.ok else []
    current = rows[0] if rows else None
    old_score = int((current or {}).get("score") or 0)
    attempts = int((current or {}).get("attempts") or 0) + 1
    delta = {"correct": 12, "partial": 5, "incorrect": -3}[assessment]
    score = max(0, min(100, old_score + delta))
    status = "mastered" if score >= 80 else "developing" if score >= 45 else "started"
    payload = {
        "student_id": student_id,
        "subject": subject_key,
        "topic": topic,
        "score": score,
        "attempts": attempts,
        "status": status,
        "updated_at": datetime.now(timezone.utc).isoformat(),
    }
    if current:
        saved = _patch(token, "student_progress", {"id": f"eq.{current['id']}"}, payload, quiet=True)
    else:
        try:
            saved = _insert(token, "student_progress", payload)
        except HTTPException:
            saved = None
    return saved or payload


def _get_progress(token: str, student_id: str):
    r = requests.get(
        f"{SUPABASE_URL}/rest/v1/student_progress",
        headers=_headers(token),
        params={
            "student_id": f"eq.{student_id}",
            "select": "id,subject,chapter,topic,score,attempts,status,updated_at",
            "order": "updated_at.desc",
            "limit": "60",
        },
        timeout=10,
    )
    return r.json() if r.ok else []


def _get_sessions(token: str, student_id: str):
    r = requests.get(
        f"{SUPABASE_URL}/rest/v1/tutor_sessions",
        headers=_headers(token),
        params={
            "student_id": f"eq.{student_id}",
            "select": "id,subject,chapter,started_at,ended_at",
            "order": "started_at.desc",
            "limit": "30",
        },
        timeout=10,
    )
    return r.json() if r.ok else []


def _daily_plan_for(student: dict, curriculum: str, progress: list):
    grade = int(student["grade"])
    if curriculum == "nios":
        items = (
            [("math", "Mathematics", 20), ("english", "English / Reading", 15), ("evs", "Environmental Studies", 20), ("computer", "Computer Skills", 15), ("life", "Life Skills", 15)]
            if grade == 3
            else [("math", "Mathematics", 30), ("english", "English / Reading", 20), ("evs", "Environmental Studies", 25), ("computer", "Computer Skills", 20), ("life", "Life Skills", 15)]
        )
    else:
        items = (
            [("math", "Mathematics", 25), ("english", "English", 20)]
            if grade == 3
            else [("math", "Mathematics", 30), ("english", "English", 25), ("evs1", "EVS Part 1", 25), ("evs2", "EVS Part 2", 20)]
        )

    weak = {}
    for row in progress:
        try:
            raw_subject = row.get("subject", "")
            c, sid = raw_subject.split(":", 1) if ":" in raw_subject else ("maharashtra", raw_subject)
            if c == curriculum:
                weak[sid] = min(weak.get(sid, 101), int(row.get("score") or 0))
        except Exception:
            pass
    items = sorted(items, key=lambda item: weak.get(item[0], 101))
    return [
        {
            "subject": sid,
            "title": title,
            "minutes": minutes,
            "activity": "revision" if weak.get(sid, 100) < 45 else "teach",
            "reason": "Needs revision" if weak.get(sid, 100) < 45 else "Today's learning block",
        }
        for sid, title, minutes in items
    ]


@app.get("/api/config")
def config():
    return {
        "supabase_url": SUPABASE_URL,
        "supabase_anon_key": SUPABASE_ANON_KEY,
        "custom_voice": bool(FISH_AUDIO_API_KEY and FISH_AUDIO_VOICE_ID),
    }


@app.get("/api/catalog")
def catalog():
    return tutor.kb.catalog()


@app.get("/api/health")
def health():
    return {
        "ok": True,
        "indexed_chunks": len(tutor.kb.docs),
        "custom_voice": bool(FISH_AUDIO_API_KEY and FISH_AUDIO_VOICE_ID),
    }


@app.get("/api/dashboard")
def dashboard(student_id: str, authorization: Optional[str] = Header(default=None)):
    token = _token(authorization)
    student = _student(token, student_id)
    progress = _get_progress(token, student_id)
    sessions = _get_sessions(token, student_id)
    scores = [int(x.get("score") or 0) for x in progress if x.get("score") is not None]
    weak = sorted([x for x in progress if int(x.get("attempts") or 0) > 0], key=lambda x: int(x.get("score") or 0))[:5]
    mastered = [x for x in progress if int(x.get("score") or 0) >= 80]
    return {
        "student": student,
        "stats": {
            "sessions": len(sessions),
            "topics": len(progress),
            "mastered": len(mastered),
            "average_mastery": round(sum(scores) / len(scores)) if scores else 0,
        },
        "progress": progress,
        "weak_topics": weak,
        "recent_sessions": sessions[:8],
        "last_session": sessions[0] if sessions else None,
    }


@app.get("/api/daily-plan")
def daily_plan(student_id: str, curriculum: str = "nios", authorization: Optional[str] = Header(default=None)):
    token = _token(authorization)
    student = _student(token, student_id)
    if curriculum not in ("maharashtra", "nios"):
        raise HTTPException(400, "Unknown curriculum")
    progress = _get_progress(token, student_id)
    items = _daily_plan_for(student, curriculum, progress)
    return {
        "curriculum": curriculum,
        "level": "NIOS Level A" if curriculum == "nios" and int(student["grade"]) == 3 else "NIOS Level B" if curriculum == "nios" else f"Grade {student['grade']}",
        "items": items,
        "total_minutes": sum(x["minutes"] for x in items),
        "note": "This is a Chalo Padhaye home-study plan, not an official NIOS timetable.",
    }


@app.post("/api/end-session")
def end_session(data: EndSessionIn, authorization: Optional[str] = Header(default=None)):
    token = _token(authorization)
    _student(token, data.student_id)
    r = requests.get(
        f"{SUPABASE_URL}/rest/v1/tutor_sessions",
        headers=_headers(token),
        params={"id": f"eq.{data.session_id}", "student_id": f"eq.{data.student_id}", "select": "id"},
        timeout=10,
    )
    if not r.ok or not r.json():
        raise HTTPException(403, "Session is not available")
    _patch(token, "tutor_sessions", {"id": f"eq.{data.session_id}"}, {"ended_at": datetime.now(timezone.utc).isoformat()})
    return {"ok": True}


@app.post("/api/tts")
def tts(data: TTSIn, authorization: Optional[str] = Header(default=None)):
    token = _token(authorization)
    _require_user(token)
    if not FISH_AUDIO_API_KEY or not FISH_AUDIO_VOICE_ID:
        raise HTTPException(503, "Custom tutor voice is not configured")
    text = re.sub(r"[#*_`]+", "", data.text or "")
    text = re.sub(r"\s*\n+\s*", ". ", text).strip()
    if not text:
        raise HTTPException(400, "Text is required")
    if len(text) > 1200:
        text = text[:1200]
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
    if data.curriculum not in ("maharashtra", "nios"):
        raise HTTPException(400, "Unknown curriculum")
    if data.activity not in ("teach", "practice", "quiz", "revision"):
        raise HTTPException(400, "Unknown activity")

    expected_subject = f"{data.curriculum}:{data.subject}"
    session_id = data.session_id
    if not session_id:
        session = _insert(token, "tutor_sessions", {"student_id": student["id"], "subject": expected_subject})
        session_id = session["id"]
    else:
        r = requests.get(
            f"{SUPABASE_URL}/rest/v1/tutor_sessions",
            headers=_headers(token),
            params={"id": f"eq.{session_id}", "student_id": f"eq.{student['id']}", "select": "id,subject"},
            timeout=10,
        )
        rows = r.json() if r.ok else []
        if not rows or rows[0].get("subject") != expected_subject:
            raise HTTPException(403, "Session does not match this lesson")

    history = _history(token, session_id)
    _insert(token, "tutor_messages", {"session_id": session_id, "role": "student", "message": data.message})
    answer = tutor.reply(
        data.message,
        student_name=student["full_name"],
        grade=int(student["grade"]),
        subject=data.subject,
        curriculum=data.curriculum,
        activity=data.activity,
        history=history,
    )
    if answer.get("text"):
        _insert(token, "tutor_messages", {"session_id": session_id, "role": "tutor", "message": answer["text"]})
    progress = _update_progress(token, student["id"], data.curriculum, data.subject, answer)
    if answer.get("topic"):
        _patch(token, "tutor_sessions", {"id": f"eq.{session_id}"}, {"chapter": answer["topic"]}, quiet=True)
    answer["session_id"] = session_id
    answer["progress"] = progress
    return answer


app.mount("/", StaticFiles(directory=ROOT / "frontend", html=True), name="frontend")
