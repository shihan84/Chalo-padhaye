from pathlib import Path
from fastapi import FastAPI
from fastapi.staticfiles import StaticFiles
from pydantic import BaseModel
from .tutor import Tutor

ROOT = Path(__file__).resolve().parents[1]
app = FastAPI(title="Chalo Padhaye")
tutor = Tutor()


class ChatIn(BaseModel):
    message: str
    student_name: str = "Usman"
    grade: int = 5
    subject: str = "evs1"


@app.get("/api/health")
def health():
    return {"ok": True, "indexed_chunks": len(tutor.kb.docs)}


@app.post("/api/chat")
def chat(data: ChatIn):
    return tutor.reply(
        data.message,
        student_name=data.student_name,
        grade=data.grade,
        subject=data.subject,
    )


app.mount("/", StaticFiles(directory=ROOT / "frontend", html=True), name="frontend")
