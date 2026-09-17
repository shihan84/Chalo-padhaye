import os
import re
from datetime import date, datetime, timedelta, timezone
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

RECORD_TYPES = {"assignment", "reading", "project", "field_trip", "physical", "art", "life_skill", "other"}
RECORD_STATUS = {"planned", "completed"}
ACTIVITIES = {"teach", "practice", "quiz", "revision", "reading", "project"}


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


class PortfolioIn(BaseModel):
    student_id: str
    record_type: str = "other"
    title: str
    curriculum: str = "nios"
    subject: Optional[str] = None
    notes: Optional[str] = None
    minutes: int = 0
    status: str = "completed"
    occurred_on: Optional[str] = None
    due_date: Optional[str] = None


class PortfolioStatusIn(BaseModel):
    student_id: str
    status: str


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
    r = requests.patch(
        f"{SUPABASE_URL}/rest/v1/{table}",
        headers=_headers(token, "return=representation"),
        params=params,
        json=payload,
        timeout=10,
    )
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
            "limit": "100",
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
            "limit": "60",
        },
        timeout=10,
    )
    return r.json() if r.ok else []


def _get_records(token: str, student_id: str, limit: int = 80):
    r = requests.get(
        f"{SUPABASE_URL}/rest/v1/homeschool_records",
        headers=_headers(token),
        params={
            "student_id": f"eq.{student_id}",
            "select": "id,record_type,title,curriculum,subject,notes,minutes,status,occurred_on,due_date,created_at,updated_at",
            "order": "created_at.desc",
            "limit": str(limit),
        },
        timeout=10,
    )
    if r.status_code == 404:
        return False, []
    if not r.ok:
        return False, []
    return True, r.json()


def _as_dt(value):
    if not value:
        return None
    try:
        return datetime.fromisoformat(str(value).replace("Z", "+00:00"))
    except Exception:
        return None


def _as_day(value):
    if not value:
        return None
    try:
        return date.fromisoformat(str(value)[:10])
    except Exception:
        return None


def _week_metrics(sessions: list, records: list):
    now = datetime.now(timezone.utc)
    cutoff = now - timedelta(days=7)
    session_minutes = 0
    weekly_sessions = 0
    active_days = set()
    subjects = set()

    for row in sessions:
        started = _as_dt(row.get("started_at"))
        if not started or started < cutoff:
            continue
        weekly_sessions += 1
        active_days.add(started.date())
        if row.get("subject"):
            subjects.add(row["subject"])
        ended = _as_dt(row.get("ended_at"))
        if ended and ended >= started:
            session_minutes += min(180, max(0, round((ended - started).total_seconds() / 60)))

    offline_minutes = 0
    completed_records = 0
    cutoff_day = cutoff.date()
    for row in records:
        if row.get("status") != "completed":
            continue
        day = _as_day(row.get("occurred_on")) or _as_day(row.get("created_at"))
        if not day or day < cutoff_day:
            continue
        completed_records += 1
        offline_minutes += max(0, min(600, int(row.get("minutes") or 0)))
        active_days.add(day)
        if row.get("subject"):
            subjects.add(f"offline:{row['subject']}")

    today = now.date()
    cursor = today if today in active_days else today - timedelta(days=1)
    streak = 0
    while cursor in active_days:
        streak += 1
        cursor -= timedelta(days=1)

    return {
        "sessions": weekly_sessions,
        "study_minutes": session_minutes,
        "offline_minutes": offline_minutes,
        "total_minutes": session_minutes + offline_minutes,
        "active_days": len(active_days),
        "subjects": len(subjects),
        "portfolio_items": completed_records,
        "streak_days": streak,
    }


def _subject_progress(progress: list, curriculum: str):
    grouped = {}
    for row in progress:
        raw = row.get("subject", "")
        if ":" in raw:
            c, sid = raw.split(":", 1)
        else:
            c, sid = "maharashtra", raw
        if c != curriculum:
            continue
        grouped.setdefault(sid, []).append(row)
    return grouped


def _daily_plan_for(student: dict, curriculum: str, progress: list):
    grade = int(student["grade"])
    catalog = tutor.kb.catalog()
    subjects = [x for x in (catalog.get(curriculum, {}).get(str(grade), [])) if x.get("available", True)]
    durations = {
        3: {"math": 20, "english": 15, "evs": 20, "computer": 15, "life": 15, "evs1": 20, "evs2": 20},
        5: {"math": 30, "english": 20, "evs": 25, "computer": 20, "life": 15, "evs1": 25, "evs2": 20},
    }
    grouped = _subject_progress(progress, curriculum)

    scored = []
    for item in subjects:
        sid = item["id"]
        rows = grouped.get(sid, [])
        attempts = sum(int(x.get("attempts") or 0) for x in rows)
        avg = round(sum(int(x.get("score") or 0) for x in rows) / len(rows)) if rows else None
        priority = avg if avg is not None else 101
        scored.append((priority, sid, item, attempts, avg))
    scored.sort(key=lambda x: x[0])

    items = []
    for _, sid, item, attempts, avg in scored:
        if avg is None:
            act, reason = "teach", "New learning block"
        elif avg < 45:
            act, reason = "revision", "Needs revision"
        elif avg < 80:
            act, reason = "practice", "Build confidence"
        else:
            act, reason = "quiz", "Quick mastery check"
        items.append({
            "kind": "lesson",
            "subject": sid,
            "title": item.get("label", sid),
            "minutes": durations.get(grade, {}).get(sid, 20),
            "activity": act,
            "reason": reason,
            "supplemental": bool(item.get("supplemental")),
        })

    if curriculum == "nios":
        items.append({"kind": "offline_suggestion", "record_type": "reading", "title": "Independent reading / read aloud", "minutes": 15 if grade == 3 else 20, "reason": "Daily reading habit"})
        items.append({"kind": "offline_suggestion", "record_type": "physical", "title": "Movement / outdoor play", "minutes": 20 if grade == 3 else 25, "reason": "Daily physical activity"})
    return items


def _roadmap_for(student: dict, curriculum: str, progress: list):
    grade = int(student["grade"])
    grouped = _subject_progress(progress, curriculum)
    catalog = tutor.kb.catalog().get(curriculum, {}).get(str(grade), [])
    out = []
    for item in catalog:
        rows = grouped.get(item["id"], [])
        attempts = sum(int(x.get("attempts") or 0) for x in rows)
        scores = [int(x.get("score") or 0) for x in rows]
        mastery = round(sum(scores) / len(scores)) if scores else 0
        weak = sorted(rows, key=lambda x: int(x.get("score") or 0))[:3]
        if not item.get("available", True):
            action, reason = "unavailable", "Source is not currently available"
        elif not rows:
            action, reason = "teach", "Start this subject"
        elif mastery < 45:
            action, reason = "revision", "Review weak topics"
        elif mastery < 80:
            action, reason = "practice", "Keep practising"
        else:
            action, reason = "quiz", "Check retention"
        out.append({
            **item,
            "mastery": mastery,
            "attempts": attempts,
            "topics": len(rows),
            "weak_topics": [{"topic": x.get("topic"), "score": int(x.get("score") or 0)} for x in weak],
            "recommended_activity": action,
            "recommendation": reason,
        })
    return out


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
    portfolio_available, records = _get_records(token, student_id)
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
        "week": _week_metrics(sessions, records),
        "progress": progress,
        "weak_topics": weak,
        "recent_sessions": sessions[:8],
        "last_session": sessions[0] if sessions else None,
        "portfolio_available": portfolio_available,
        "recent_portfolio": records[:8],
    }


@app.get("/api/roadmap")
def roadmap(student_id: str, curriculum: str = "nios", authorization: Optional[str] = Header(default=None)):
    token = _token(authorization)
    student = _student(token, student_id)
    if curriculum not in ("maharashtra", "nios"):
        raise HTTPException(400, "Unknown curriculum")
    progress = _get_progress(token, student_id)
    return {
        "curriculum": curriculum,
        "level": "NIOS Level A" if curriculum == "nios" and int(student["grade"]) == 3 else "NIOS Level B" if curriculum == "nios" else f"Grade {student['grade']}",
        "subjects": _roadmap_for(student, curriculum, progress),
    }


@app.get("/api/daily-plan")
def daily_plan(student_id: str, curriculum: str = "nios", authorization: Optional[str] = Header(default=None)):
    token = _token(authorization)
    student = _student(token, student_id)
    if curriculum not in ("maharashtra", "nios"):
        raise HTTPException(400, "Unknown curriculum")
    progress = _get_progress(token, student_id)
    items = _daily_plan_for(student, curriculum, progress)
    portfolio_available, records = _get_records(token, student_id)
    today = date.today()
    planned = []
    if portfolio_available:
        for row in records:
            if row.get("status") != "planned":
                continue
            due = _as_day(row.get("due_date"))
            if due and due > today:
                continue
            planned.append({
                "kind": "portfolio",
                "record_id": row.get("id"),
                "record_type": row.get("record_type"),
                "title": row.get("title"),
                "minutes": int(row.get("minutes") or 0),
                "reason": "Parent-planned activity",
                "subject": row.get("subject"),
            })
            if len(planned) >= 3:
                break
    items = planned + items
    return {
        "curriculum": curriculum,
        "level": "NIOS Level A" if curriculum == "nios" and int(student["grade"]) == 3 else "NIOS Level B" if curriculum == "nios" else f"Grade {student['grade']}",
        "items": items,
        "total_minutes": sum(max(0, int(x.get("minutes") or 0)) for x in items),
        "portfolio_available": portfolio_available,
        "note": "This is a Chalo Padhaye home-study plan, not an official NIOS timetable. Include breaks and adjust the pace to the child.",
    }


@app.get("/api/portfolio")
def portfolio(student_id: str, authorization: Optional[str] = Header(default=None)):
    token = _token(authorization)
    _student(token, student_id)
    available, records = _get_records(token, student_id)
    return {
        "available": available,
        "records": records,
        "migration": "supabase/003_homeschool_records.sql" if not available else None,
    }


@app.post("/api/portfolio")
def create_portfolio(data: PortfolioIn, authorization: Optional[str] = Header(default=None)):
    token = _token(authorization)
    _student(token, data.student_id)
    if data.record_type not in RECORD_TYPES:
        raise HTTPException(400, "Unknown record type")
    if data.status not in RECORD_STATUS:
        raise HTTPException(400, "Unknown record status")
    if data.curriculum not in ("maharashtra", "nios", "general"):
        raise HTTPException(400, "Unknown curriculum")
    title = data.title.strip()[:160]
    if not title:
        raise HTTPException(400, "Title is required")
    occurred_on = data.occurred_on or date.today().isoformat()
    try:
        date.fromisoformat(occurred_on)
        if data.due_date:
            date.fromisoformat(data.due_date)
    except Exception:
        raise HTTPException(400, "Use YYYY-MM-DD for dates")
    payload = {
        "student_id": data.student_id,
        "record_type": data.record_type,
        "title": title,
        "curriculum": data.curriculum,
        "subject": (data.subject or "")[:80] or None,
        "notes": (data.notes or "")[:1500] or None,
        "minutes": max(0, min(600, int(data.minutes or 0))),
        "status": data.status,
        "occurred_on": occurred_on,
        "due_date": data.due_date or None,
    }
    r = requests.post(
        f"{SUPABASE_URL}/rest/v1/homeschool_records",
        headers=_headers(token, "return=representation"),
        json=payload,
        timeout=10,
    )
    if r.status_code == 404:
        raise HTTPException(503, "Portfolio storage is not installed yet. Run supabase/003_homeschool_records.sql once in Supabase.")
    if not r.ok:
        raise HTTPException(502, "Could not save homeschool record")
    rows = r.json()
    return rows[0] if rows else payload


@app.patch("/api/portfolio/{record_id}")
def update_portfolio(record_id: str, data: PortfolioStatusIn, authorization: Optional[str] = Header(default=None)):
    token = _token(authorization)
    _student(token, data.student_id)
    if data.status not in RECORD_STATUS:
        raise HTTPException(400, "Unknown record status")
    r = requests.get(
        f"{SUPABASE_URL}/rest/v1/homeschool_records",
        headers=_headers(token),
        params={"id": f"eq.{record_id}", "student_id": f"eq.{data.student_id}", "select": "id,status"},
        timeout=10,
    )
    if r.status_code == 404:
        raise HTTPException(503, "Portfolio storage is not installed yet")
    rows = r.json() if r.ok else []
    if not rows:
        raise HTTPException(404, "Record not found")
    payload = {"status": data.status, "updated_at": datetime.now(timezone.utc).isoformat()}
    if data.status == "completed":
        payload["occurred_on"] = date.today().isoformat()
    saved = _patch(token, "homeschool_records", {"id": f"eq.{record_id}", "student_id": f"eq.{data.student_id}"}, payload)
    return saved or {"id": record_id, **payload}


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
    if data.activity not in ACTIVITIES:
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
