import json
import os
import re
from datetime import datetime, timezone
from pathlib import Path
from typing import Optional

import requests
from fastapi import APIRouter, Header, HTTPException
from pydantic import BaseModel

ROOT = Path(__file__).resolve().parents[1]
PLAN_FILE = ROOT / "data" / "lesson_plans.json"
SUPABASE_URL = os.getenv("NEXT_PUBLIC_SUPABASE_URL") or os.getenv("SUPABASE_URL", "")
SUPABASE_ANON_KEY = os.getenv("NEXT_PUBLIC_SUPABASE_ANON_KEY") or os.getenv("SUPABASE_ANON_KEY", "")

router = APIRouter(prefix="/api", tags=["lessons"])


def _load_plan():
    try:
        return json.loads(PLAN_FILE.read_text(encoding="utf-8"))
    except Exception:
        return {"mastery": {"pass_score": 70, "steps": []}, "curricula": {}}


PLAN = _load_plan()


def _headers(token: str, prefer: Optional[str] = None):
    out = {
        "apikey": SUPABASE_ANON_KEY,
        "Authorization": f"Bearer {token}",
        "Content-Type": "application/json",
    }
    if prefer:
        out["Prefer"] = prefer
    return out


def _token(authorization: Optional[str]):
    if not authorization or not authorization.lower().startswith("bearer "):
        raise HTTPException(401, "Parent login required")
    return authorization.split(" ", 1)[1].strip()


def _student(token: str, student_id: str):
    if not SUPABASE_URL or not SUPABASE_ANON_KEY:
        raise HTTPException(503, "Supabase is not configured")
    r = requests.get(
        f"{SUPABASE_URL}/rest/v1/students",
        headers=_headers(token),
        params={"id": f"eq.{student_id}", "select": "id,full_name,grade"},
        timeout=10,
    )
    if not r.ok:
        raise HTTPException(401, "Could not verify student")
    rows = r.json()
    if not rows:
        raise HTTPException(403, "Student is not available to this parent")
    return rows[0]


def _slug(value: str):
    s = re.sub(r"[^a-z0-9]+", "-", value.lower()).strip("-")
    return s[:42] or "lesson"


def _subject_lessons(curriculum: str, grade: int, subject: str):
    titles = (((PLAN.get("curricula") or {}).get(curriculum) or {}).get(str(grade)) or {}).get(subject) or []
    return [
        {
            "id": f"{subject}-{idx:02d}-{_slug(title)}",
            "order": idx,
            "title": title,
        }
        for idx, title in enumerate(titles, start=1)
    ]


def _step_template():
    return list((PLAN.get("mastery") or {}).get("steps") or [])


def _progress_rows(token: str, student_id: str, curriculum: str, subject: str):
    r = requests.get(
        f"{SUPABASE_URL}/rest/v1/lesson_progress",
        headers=_headers(token),
        params={
            "student_id": f"eq.{student_id}",
            "curriculum": f"eq.{curriculum}",
            "subject": f"eq.{subject}",
            "select": "id,lesson_id,lesson_order,lesson_title,status,current_step,percent_complete,test_score,test_attempts,started_at,mastered_at,updated_at",
            "order": "lesson_order.asc",
        },
        timeout=10,
    )
    if r.status_code == 404:
        return False, []
    if not r.ok:
        return False, []
    return True, r.json()


def _step_rows(token: str, student_id: str, progress_ids: list[str]):
    if not progress_ids:
        return []
    r = requests.get(
        f"{SUPABASE_URL}/rest/v1/lesson_step_records",
        headers=_headers(token),
        params={
            "student_id": f"eq.{student_id}",
            "lesson_progress_id": f"in.({','.join(progress_ids)})",
            "select": "id,lesson_progress_id,step_id,step_order,step_title,status,attempts,correct_count,score,started_at,completed_at,updated_at",
            "order": "step_order.asc",
        },
        timeout=10,
    )
    return r.json() if r.ok else []


def _post(token: str, table: str, payload: dict):
    r = requests.post(
        f"{SUPABASE_URL}/rest/v1/{table}",
        headers=_headers(token, "return=representation"),
        json=payload,
        timeout=10,
    )
    if r.status_code == 404:
        raise HTTPException(503, "Lesson tracking is not installed. Run supabase/004_lesson_mastery.sql once.")
    if not r.ok:
        raise HTTPException(502, f"Could not save {table}")
    rows = r.json()
    return rows[0] if rows else payload


def _patch(token: str, table: str, params: dict, payload: dict):
    r = requests.patch(
        f"{SUPABASE_URL}/rest/v1/{table}",
        headers=_headers(token, "return=representation"),
        params=params,
        json=payload,
        timeout=10,
    )
    if r.status_code == 404:
        raise HTTPException(503, "Lesson tracking is not installed. Run supabase/004_lesson_mastery.sql once.")
    if not r.ok:
        raise HTTPException(502, f"Could not update {table}")
    try:
        rows = r.json()
        return rows[0] if rows else None
    except Exception:
        return None


def _get_one(token: str, table: str, params: dict, select: str = "*"):
    q = {**params, "select": select, "limit": "1"}
    r = requests.get(f"{SUPABASE_URL}/rest/v1/{table}", headers=_headers(token), params=q, timeout=10)
    if r.status_code == 404:
        raise HTTPException(503, "Lesson tracking is not installed. Run supabase/004_lesson_mastery.sql once.")
    rows = r.json() if r.ok else []
    return rows[0] if rows else None


def _lesson_definition(student: dict, curriculum: str, subject: str, lesson_id: str):
    lessons = _subject_lessons(curriculum, int(student["grade"]), subject)
    found = next((x for x in lessons if x["id"] == lesson_id), None)
    if not found:
        raise HTTPException(404, "Lesson is not defined for this student and subject")
    return found, lessons


def _can_open(order: int, lessons: list, progress_by_id: dict):
    if order <= 1:
        return True
    previous = lessons[order - 2]
    row = progress_by_id.get(previous["id"])
    return bool(row and row.get("status") in {"mastered", "parent_unlocked"})


def _ensure_progress(token: str, student: dict, curriculum: str, subject: str, lesson: dict, lessons: list):
    row = _get_one(
        token,
        "lesson_progress",
        {
            "student_id": f"eq.{student['id']}",
            "curriculum": f"eq.{curriculum}",
            "subject": f"eq.{subject}",
            "lesson_id": f"eq.{lesson['id']}",
        },
    )
    if row:
        return row

    available, rows = _progress_rows(token, student["id"], curriculum, subject)
    if not available:
        raise HTTPException(503, "Lesson tracking is not installed. Run supabase/004_lesson_mastery.sql once.")
    progress_by_id = {x["lesson_id"]: x for x in rows}
    if not _can_open(lesson["order"], lessons, progress_by_id):
        raise HTTPException(403, "Complete the previous chapter test before opening this lesson")

    now = datetime.now(timezone.utc).isoformat()
    row = _post(
        token,
        "lesson_progress",
        {
            "student_id": student["id"],
            "curriculum": curriculum,
            "subject": subject,
            "lesson_id": lesson["id"],
            "lesson_order": lesson["order"],
            "lesson_title": lesson["title"],
            "status": "in_progress",
            "current_step": "learn",
            "percent_complete": 0,
            "started_at": now,
            "updated_at": now,
        },
    )
    for step in _step_template():
        _post(
            token,
            "lesson_step_records",
            {
                "lesson_progress_id": row["id"],
                "student_id": student["id"],
                "step_id": step["id"],
                "step_order": int(step["order"]),
                "step_title": step["title"],
                "status": "available" if int(step["order"]) == 1 else "locked",
            },
        )
    return row


class StartLessonIn(BaseModel):
    student_id: str
    curriculum: str
    subject: str
    lesson_id: str


class StepEventIn(StartLessonIn):
    step_id: str
    assessment: str = "not_answer"
    score: Optional[int] = None


class TestAttemptIn(StartLessonIn):
    score: int
    correct_count: int = 0
    question_count: int = 5
    summary: dict = {}


class OverrideIn(StartLessonIn):
    action: str = "unlock"


@router.get("/lessons")
def lessons(student_id: str, curriculum: str, subject: str, authorization: Optional[str] = Header(default=None)):
    token = _token(authorization)
    student = _student(token, student_id)
    if curriculum not in {"maharashtra", "nios"}:
        raise HTTPException(400, "Unknown curriculum")
    definitions = _subject_lessons(curriculum, int(student["grade"]), subject)
    available, rows = _progress_rows(token, student_id, curriculum, subject)
    by_id = {x["lesson_id"]: x for x in rows}
    steps = _step_rows(token, student_id, [x["id"] for x in rows]) if available else []
    steps_by_progress = {}
    for step in steps:
        steps_by_progress.setdefault(step["lesson_progress_id"], []).append(step)

    result = []
    for item in definitions:
        row = by_id.get(item["id"])
        if row:
            status = row.get("status") or "in_progress"
            record_steps = steps_by_progress.get(row["id"], [])
        else:
            status = "available" if _can_open(item["order"], definitions, by_id) else "locked"
            record_steps = [
                {**s, "status": "available" if s["order"] == 1 and status == "available" else "locked", "attempts": 0, "correct_count": 0, "score": None}
                for s in _step_template()
            ]
        result.append({
            **item,
            "status": status,
            "percent_complete": int((row or {}).get("percent_complete") or 0),
            "test_score": (row or {}).get("test_score"),
            "test_attempts": int((row or {}).get("test_attempts") or 0),
            "current_step": (row or {}).get("current_step") or ("learn" if status == "available" else None),
            "steps": record_steps,
        })

    mastered = sum(1 for x in result if x["status"] == "mastered")
    return {
        "student": student,
        "curriculum": curriculum,
        "subject": subject,
        "storage_available": available,
        "migration": None if available else "supabase/004_lesson_mastery.sql",
        "pass_score": int((PLAN.get("mastery") or {}).get("pass_score") or 70),
        "mastered": mastered,
        "total": len(result),
        "percent": round(mastered * 100 / len(result)) if result else 0,
        "lessons": result,
    }


@router.post("/lessons/start")
def start_lesson(data: StartLessonIn, authorization: Optional[str] = Header(default=None)):
    token = _token(authorization)
    student = _student(token, data.student_id)
    lesson, definitions = _lesson_definition(student, data.curriculum, data.subject, data.lesson_id)
    progress = _ensure_progress(token, student, data.curriculum, data.subject, lesson, definitions)
    if progress.get("status") == "available":
        progress = _patch(token, "lesson_progress", {"id": f"eq.{progress['id']}"}, {"status": "in_progress", "started_at": datetime.now(timezone.utc).isoformat(), "updated_at": datetime.now(timezone.utc).isoformat()}) or progress
    return {"ok": True, "lesson": lesson, "progress": progress}


@router.post("/lessons/step")
def lesson_step(data: StepEventIn, authorization: Optional[str] = Header(default=None)):
    token = _token(authorization)
    student = _student(token, data.student_id)
    lesson, definitions = _lesson_definition(student, data.curriculum, data.subject, data.lesson_id)
    progress = _ensure_progress(token, student, data.curriculum, data.subject, lesson, definitions)
    template = next((x for x in _step_template() if x["id"] == data.step_id), None)
    if not template:
        raise HTTPException(400, "Unknown lesson step")
    if data.step_id == "test":
        raise HTTPException(400, "Use /api/lessons/test for the chapter test")

    step = _get_one(token, "lesson_step_records", {"lesson_progress_id": f"eq.{progress['id']}", "step_id": f"eq.{data.step_id}"})
    if not step:
        raise HTTPException(404, "Lesson step record not found")
    if step.get("status") == "locked":
        raise HTTPException(403, "Complete the previous lesson step first")

    attempts = int(step.get("attempts") or 0)
    correct = int(step.get("correct_count") or 0)
    if data.assessment in {"correct", "partial", "incorrect"}:
        attempts += 1
        if data.assessment == "correct":
            correct += 1
    minimum = int(template.get("minimum_checks") or 1)
    if data.step_id == "learn":
        completed = attempts >= minimum and correct >= 1
    else:
        completed = correct >= minimum

    now = datetime.now(timezone.utc).isoformat()
    payload = {
        "status": "completed" if completed else "in_progress",
        "attempts": attempts,
        "correct_count": correct,
        "score": max(0, min(100, int(data.score))) if data.score is not None else step.get("score"),
        "started_at": step.get("started_at") or now,
        "completed_at": now if completed else step.get("completed_at"),
        "updated_at": now,
    }
    saved_step = _patch(token, "lesson_step_records", {"id": f"eq.{step['id']}"}, payload) or {**step, **payload}

    if completed:
        templates = _step_template()
        current_index = next(i for i, x in enumerate(templates) if x["id"] == data.step_id)
        next_step = templates[current_index + 1] if current_index + 1 < len(templates) else None
        completed_count = current_index + 1
        percent = round(completed_count * 100 / len(templates)) if templates else 0
        lesson_status = "test_ready" if next_step and next_step["id"] == "test" else "in_progress"
        if next_step:
            next_record = _get_one(token, "lesson_step_records", {"lesson_progress_id": f"eq.{progress['id']}", "step_id": f"eq.{next_step['id']}"})
            if next_record and next_record.get("status") == "locked":
                _patch(token, "lesson_step_records", {"id": f"eq.{next_record['id']}"}, {"status": "available", "updated_at": now})
        progress = _patch(token, "lesson_progress", {"id": f"eq.{progress['id']}"}, {"status": lesson_status, "current_step": next_step["id"] if next_step else data.step_id, "percent_complete": percent, "updated_at": now}) or progress

    return {"ok": True, "step": saved_step, "progress": progress}


@router.post("/lessons/test")
def lesson_test(data: TestAttemptIn, authorization: Optional[str] = Header(default=None)):
    token = _token(authorization)
    student = _student(token, data.student_id)
    lesson, definitions = _lesson_definition(student, data.curriculum, data.subject, data.lesson_id)
    progress = _ensure_progress(token, student, data.curriculum, data.subject, lesson, definitions)
    if progress.get("status") not in {"test_ready", "parent_unlocked", "mastered"}:
        raise HTTPException(403, "Complete Learn, Practice and Revision before the chapter test")

    score = max(0, min(100, int(data.score)))
    pass_score = int((PLAN.get("mastery") or {}).get("pass_score") or 70)
    passed = score >= pass_score
    attempt_no = int(progress.get("test_attempts") or 0) + 1
    now = datetime.now(timezone.utc).isoformat()
    _post(token, "lesson_test_attempts", {
        "student_id": student["id"],
        "lesson_progress_id": progress["id"],
        "attempt_no": attempt_no,
        "score": score,
        "passed": passed,
        "correct_count": max(0, int(data.correct_count or 0)),
        "question_count": max(1, int(data.question_count or 5)),
        "summary": data.summary or {},
    })
    test_step = _get_one(token, "lesson_step_records", {"lesson_progress_id": f"eq.{progress['id']}", "step_id": "eq.test"})
    if test_step:
        _patch(token, "lesson_step_records", {"id": f"eq.{test_step['id']}"}, {
            "status": "completed" if passed else "in_progress",
            "attempts": attempt_no,
            "correct_count": max(int(test_step.get("correct_count") or 0), int(data.correct_count or 0)),
            "score": score,
            "started_at": test_step.get("started_at") or now,
            "completed_at": now if passed else None,
            "updated_at": now,
        })
    progress_payload = {
        "status": "mastered" if passed else "test_ready",
        "current_step": "test",
        "percent_complete": 100 if passed else 75,
        "test_score": score,
        "test_attempts": attempt_no,
        "mastered_at": now if passed else None,
        "updated_at": now,
    }
    progress = _patch(token, "lesson_progress", {"id": f"eq.{progress['id']}"}, progress_payload) or {**progress, **progress_payload}

    next_lesson = None
    if passed and lesson["order"] < len(definitions):
        next_lesson = definitions[lesson["order"]]
        existing = _get_one(token, "lesson_progress", {
            "student_id": f"eq.{student['id']}",
            "curriculum": f"eq.{data.curriculum}",
            "subject": f"eq.{data.subject}",
            "lesson_id": f"eq.{next_lesson['id']}",
        })
        if not existing:
            _post(token, "lesson_progress", {
                "student_id": student["id"],
                "curriculum": data.curriculum,
                "subject": data.subject,
                "lesson_id": next_lesson["id"],
                "lesson_order": next_lesson["order"],
                "lesson_title": next_lesson["title"],
                "status": "available",
                "current_step": "learn",
                "percent_complete": 0,
                "updated_at": now,
            })

    return {"ok": True, "passed": passed, "pass_score": pass_score, "progress": progress, "next_lesson": next_lesson if passed else None}


@router.post("/lessons/override")
def lesson_override(data: OverrideIn, authorization: Optional[str] = Header(default=None)):
    token = _token(authorization)
    student = _student(token, data.student_id)
    lesson, definitions = _lesson_definition(student, data.curriculum, data.subject, data.lesson_id)
    if data.action not in {"unlock", "master"}:
        raise HTTPException(400, "Action must be unlock or master")
    progress = _ensure_progress(token, student, data.curriculum, data.subject, lesson, definitions) if lesson["order"] == 1 else _get_one(token, "lesson_progress", {
        "student_id": f"eq.{student['id']}",
        "curriculum": f"eq.{data.curriculum}",
        "subject": f"eq.{data.subject}",
        "lesson_id": f"eq.{data.lesson_id}",
    })
    now = datetime.now(timezone.utc).isoformat()
    if not progress:
        progress = _post(token, "lesson_progress", {
            "student_id": student["id"],
            "curriculum": data.curriculum,
            "subject": data.subject,
            "lesson_id": lesson["id"],
            "lesson_order": lesson["order"],
            "lesson_title": lesson["title"],
            "status": "parent_unlocked" if data.action == "unlock" else "mastered",
            "current_step": "learn" if data.action == "unlock" else "test",
            "percent_complete": 0 if data.action == "unlock" else 100,
            "mastered_at": now if data.action == "master" else None,
            "updated_at": now,
        })
    else:
        progress = _patch(token, "lesson_progress", {"id": f"eq.{progress['id']}"}, {
            "status": "parent_unlocked" if data.action == "unlock" else "mastered",
            "percent_complete": 0 if data.action == "unlock" else 100,
            "mastered_at": now if data.action == "master" else progress.get("mastered_at"),
            "updated_at": now,
        }) or progress
    return {"ok": True, "progress": progress}
