# Codex execution brief

Goal: run and harden the Chalo Padhaye MVP on an Intel MacBook Air.

## Required steps

1. Clone the repository.
2. Create a Python virtual environment and install `requirements.txt`.
3. Ensure Ollama is installed and available locally.
4. Pull `qwen2.5:3b` (or, only if incompatible/too slow, choose a smaller Ollama instruct model and document the change).
5. Run `python scripts/download_books.py`.
6. Run `python scripts/ingest_books.py`.
7. Start `uvicorn app.main:app --host 127.0.0.1 --port 8000`.
8. Verify `/api/health` reports indexed pages.
9. Open the browser UI and complete a chat round-trip.
10. Fix any runtime errors found on Intel macOS.

## Non-negotiable product rules

- Class 5 Maharashtra State Board, English medium first.
- Hindi/Hinglish explanations; preserve textbook terms in English.
- Syllabus answers must be grounded in locally indexed textbook context.
- Ask one question at a time and wait for the learner.
- Prefer local/offline components. Do not silently replace Ollama/RAG with a cloud API.
- Do not commit downloaded textbook PDFs.

## Next implementation after MVP is stable

Implement local `whisper.cpp` STT, SQLite student progress, subject/chapter selection, a lesson state machine with hints/retries, and a parent progress dashboard.

Before major refactors, keep the existing MVP working and make incremental commits.
