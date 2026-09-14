#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if ! command -v python3 >/dev/null 2>&1; then
  echo "python3 is required"
  exit 1
fi

if [ ! -d .venv ]; then
  python3 -m venv .venv
fi

source .venv/bin/activate
python -m pip install --upgrade pip
pip install -r requirements.txt

mkdir -p data/textbooks data/extracted runtime

python scripts/download_books.py
python scripts/ingest_books.py

echo
if command -v ollama >/dev/null 2>&1; then
  echo "Ollama detected. Make sure the server is running and qwen2.5:3b is installed:"
  echo "  ollama pull qwen2.5:3b"
else
  echo "Ollama is not installed yet. Install it, then run: ollama pull qwen2.5:3b"
fi

echo
echo "Textbooks and index are ready locally."
echo "Start the tutor with:"
echo "  source .venv/bin/activate"
echo "  uvicorn app.main:app --host 127.0.0.1 --port 8000 --reload"
