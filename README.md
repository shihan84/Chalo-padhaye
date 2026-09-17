# Chalo Padhaye

Private family AI tutor and homeschool companion for children studying in English medium.

The app currently supports two learning tracks:

- **School Study** — Maharashtra State Board / Balbharati material.
- **Homeschool Learning** — NIOS Open Basic Education (OBE) Level A / Level B plus clearly labelled supplemental English and life-skills material.

The tutor keeps textbook terms in English and can explain in simple Hindi/Hinglish. It teaches one small concept at a time, asks one question, waits for the learner, gives a hint before revealing an answer, and stores progress privately for the parent.

## Current architecture

- **Frontend:** static responsive web UI served by FastAPI.
- **Backend:** FastAPI / Python.
- **Tutor model:** Groq cloud model, with optional local Ollama fallback.
- **Retrieval:** BM25 over official textbook PDFs fetched at runtime. PDFs are not committed to Git.
- **Student data:** Supabase Auth + Postgres + Row Level Security.
- **Voice:** Fish Audio custom tutor voice through a server-side authenticated endpoint.
- **Speech input:** browser SpeechRecognition when available.
- **Hosting:** works on Vercel with environment variables configured server-side.

## Homeschool features

- NIOS Level A mapping for Grade 3 and Level B mapping for Grade 5.
- Environmental Studies, Mathematics, Basic Computer Skills, English/Reading supplement, and Life Skills/Projects tracks.
- Runtime discovery of official NIOS OBE material links from an official NIOS course-material page where possible.
- Supplemental sources are labelled as supplemental and are never presented as official NIOS textbooks.
- Teach, Practice, Quick Quiz, and Revision modes.
- Conversation memory inside each lesson session.
- Answer assessment: correct / partial / incorrect / not-an-answer.
- Topic mastery tracking in Supabase.
- Parent dashboard with sessions, topics, mastered topics, average mastery, weak topics, and recent lessons.
- Daily home-learning plan that prioritizes weak subjects.
- Adjustable tutor voice playback speed, replay, mute, English/Hindi microphone mode.
- Private parent login and per-child RLS isolation.

## Important source policy

Official Balbharati and NIOS PDFs are fetched from their public official sources at runtime. Full textbook PDFs are intentionally not stored in this repository.

NIOS material availability can change. The app attempts to discover current official NIOS OBE links at runtime and falls back only to sources explicitly labelled as supplemental. It must not silently present a supplemental source as an official NIOS book.

## Privacy and security

- Parent authentication is required for student data and tutor voice generation.
- Child profiles are protected by Supabase Row Level Security.
- No Supabase service-role key is required by the application.
- Provider API keys belong only in Vercel/server environment variables.
- Do not commit API keys, child recordings, private documents, or downloaded textbook PDFs.

## Environment variables

```bash
NEXT_PUBLIC_SUPABASE_URL=
NEXT_PUBLIC_SUPABASE_ANON_KEY=
GROQ_API_KEY=
GROQ_MODEL=openai/gpt-oss-20b
FISH_AUDIO_API_KEY=
FISH_AUDIO_VOICE_ID=
FISH_AUDIO_MODEL=s2.1-pro-free
```

Optional local fallback:

```bash
OLLAMA_URL=http://127.0.0.1:11434/api/generate
OLLAMA_MODEL=qwen2.5:3b
```

## Local development

```bash
git clone https://github.com/shihan84/Chalo-padhaye.git
cd Chalo-padhaye
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
uvicorn app.main:app --host 127.0.0.1 --port 8000 --reload
```

Health check:

```bash
curl http://127.0.0.1:8000/api/health
```

## Product rules

1. Ground syllabus claims in the selected lesson source.
2. Keep official and supplemental sources visibly distinct.
3. Ask exactly one learner question at a time.
4. Give a hint before the answer when a learner is wrong.
5. Use recent session history so short answers such as “solid” are interpreted in context.
6. Never compare siblings or use scores as punishment.
7. Keep parent data private and child-facing language age appropriate.

## Near-term roadmap

- Persistent weekly homeschool planner / assignment completion.
- Printable worksheets and parent-selected tests.
- Reading and pronunciation practice with progress tracking.
- Project / portfolio log for homeschool work.
- Better chapter navigation and pre-indexed textbook metadata.
- Optional Whisper-based speech-to-text for more consistent voice input.
- Improved custom tutor voice/accent and lower-latency streaming playback.
