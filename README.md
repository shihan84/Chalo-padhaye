# Chalo Padhaye

Local-first AI tutor for **Maharashtra State Board, Class 5, English Medium**.

The tutor explains in simple Hindi/Hinglish while keeping important school terms in English. It retrieves answers from locally downloaded Balbharati textbooks and uses a local Ollama model.

## Current MVP

- Official Balbharati Class 5 material manifest
- PDF downloader and text ingestion
- Local BM25 textbook retrieval (no cloud vector DB)
- Ollama-powered tutor with strict Class 5 instructions
- Browser chat UI
- Spoken tutor replies through system/browser TTS
- Optional browser microphone input when SpeechRecognition is supported

## Mac setup

Prerequisites: Python 3.10+ and Ollama.

```bash
git clone https://github.com/shihan84/Chalo-padhaye.git
cd Chalo-padhaye
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
```

Install/start Ollama, then pull the lightweight model:

```bash
ollama pull qwen2.5:3b
ollama serve
```

In another terminal:

```bash
cd Chalo-padhaye
source .venv/bin/activate
python scripts/download_books.py
python scripts/ingest_books.py
uvicorn app.main:app --host 127.0.0.1 --port 8000 --reload
```

Open `http://127.0.0.1:8000`.

Health check:

```bash
curl http://127.0.0.1:8000/api/health
```

## Environment variables

```bash
export OLLAMA_MODEL=qwen2.5:3b
export OLLAMA_URL=http://127.0.0.1:11434/api/generate
```

## Official source material

The initial manifest uses official Maharashtra State Bureau / eBalbharati PDFs for Class 5 English-medium Environmental Studies, Mathematics, and English Balbharati. PDFs are downloaded locally and are intentionally not committed to Git.

## Important privacy note

Tutor inference and textbook search are local. Browser `speechSynthesis` typically uses installed/system voices. Browser `SpeechRecognition` behavior depends on the browser and may use an online service, so it should not yet be considered fully on-premise speech-to-text. The next milestone is a local `whisper.cpp` microphone pipeline.

## Next milestones

1. Local whisper.cpp STT on Intel Mac
2. Student sessions + SQLite progress tracking
3. Subject/chapter picker
4. Question-at-a-time lesson state machine with hints and retries
5. Parent dashboard
6. Better RAG chunking and page/source citations
7. Math-specific answer checking

## Codex task

Clone this repository and run the MVP exactly as described above. Fix any Intel macOS compatibility issue you encounter without replacing the local-first architecture. Verify `/api/health`, download/ingest at least one textbook, and complete one tutor chat round-trip in the browser.
