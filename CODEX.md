# Codex execution brief

Goal: keep Chalo Padhaye reliable as a private family school + homeschool learning platform.

## Current production architecture

- FastAPI backend and static web frontend.
- Supabase Auth/Postgres/RLS for private family data.
- Groq tutor inference with optional Ollama fallback.
- Runtime BM25 retrieval over official Balbharati / NIOS material.
- Fish Audio custom tutor voice through `/api/tts`.
- Vercel deployment.

## Non-negotiable product rules

- English-medium school terms; simple Hindi/Hinglish explanations when helpful.
- Ground syllabus facts only in supplied lesson context.
- Never silently label supplemental material as official NIOS content.
- One small concept and exactly one learner question per turn.
- Use recent session history so the tutor can evaluate short child answers correctly.
- First wrong attempt gets a hint, not the final answer.
- Grade 3 / NIOS Level A must be simpler and shorter than Grade 5 / Level B.
- Parent login and Supabase RLS remain mandatory for child data.
- Never commit secrets, child recordings, private documents, or textbook PDFs.
- No service-role key in browser or frontend code.

## Current homeschool product surface

- School / Homeschool modes.
- NIOS Level A and B subject catalog plus clearly marked supplemental tracks.
- Teach / Practice / Quiz / Revision activity modes.
- Supabase session history and mastery tracking.
- Daily learning plan.
- Parent progress dashboard.
- Custom voice replay, mute, speed control, and microphone language selection.

## Engineering priorities

1. Keep `/api/chat`, `/api/tts`, `/api/dashboard`, `/api/daily-plan`, and `/api/catalog` backward compatible.
2. Improve NIOS official material discovery and cache/index performance without committing the PDFs.
3. Add persistent weekly planning, assignments, and homeschool portfolio records using RLS-protected tables.
4. Add printable worksheets/tests with parent controls.
5. Add reading/pronunciation tracking and optional Whisper STT.
6. Add automated tests for auth isolation, session ownership, curriculum isolation, progress updates, and source labelling.

Before major refactors, preserve the working production flow and make incremental commits.
