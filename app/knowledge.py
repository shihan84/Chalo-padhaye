from pathlib import Path
from io import BytesIO
import re
import requests
from pypdf import PdfReader
from rank_bm25 import BM25Okapi

ROOT = Path(__file__).resolve().parents[1]
EXTRACTED = ROOT / "data" / "extracted"

REMOTE_BOOKS = {
    (5, "evs1"): {
        "title": "Environmental Studies Part One - Standard Five",
        "url": "https://books.ebalbharati.in/pdfs/503000541.pdf",
    },
    (5, "evs2"): {
        "title": "Environmental Studies Part Two - Standard Five",
        "url": "https://books.ebalbharati.in/pdfs/503000542.pdf",
    },
    (5, "math"): {
        "title": "Mathematics - Standard Five",
        "url": "https://books.ebalbharati.in/pdfs/503020004.pdf",
    },
    (5, "english"): {
        "title": "English Balbharati - Standard Five",
        "url": "https://books.ebalbharati.in/pdfs/503020001.pdf",
    },
    (3, "math"): {
        "title": "Mathematics - Standard Three",
        "url": "https://books.ebalbharati.in/pdfs/303020004.pdf",
    },
    (3, "english"): {
        "title": "English Balbharati - Standard Three",
        "url": "https://books.ebalbharati.in/pdfs/303020001.pdf",
    },
}


def tokenize(text: str):
    return re.findall(r"[A-Za-z0-9']+", text.lower())


def chunks(text: str, size: int = 1800, overlap: int = 250):
    text = re.sub(r"\s+", " ", text).strip()
    if not text:
        return []
    out = []
    start = 0
    while start < len(text):
        out.append(text[start : start + size])
        if start + size >= len(text):
            break
        start += size - overlap
    return out


class KnowledgeBase:
    def __init__(self):
        self.local_docs = []
        if EXTRACTED.exists():
            for path in sorted(EXTRACTED.glob("*.txt")):
                text = path.read_text(encoding="utf-8", errors="ignore")
                for i, part in enumerate(chunks(text)):
                    self.local_docs.append({"source": f"{path.name} chunk {i+1}", "text": part})
        self.remote_indexes = {}

    @property
    def docs(self):
        total = list(self.local_docs)
        for value in self.remote_indexes.values():
            total.extend(value["docs"])
        return total

    def _build_remote_index(self, grade: int, subject: str):
        key = (grade, subject)
        if key in self.remote_indexes:
            return self.remote_indexes[key]
        book = REMOTE_BOOKS.get(key)
        if not book:
            return {"docs": [], "bm25": None}

        r = requests.get(book["url"], timeout=35)
        r.raise_for_status()
        reader = PdfReader(BytesIO(r.content))
        docs = []
        for page_no, page in enumerate(reader.pages, start=1):
            try:
                text = page.extract_text() or ""
            except Exception:
                text = ""
            for i, part in enumerate(chunks(text)):
                docs.append({
                    "source": f"{book['title']} — page {page_no}",
                    "text": part,
                    "page": page_no,
                    "url": book["url"],
                })

        tokens = [tokenize(d["text"]) for d in docs]
        index = {"docs": docs, "bm25": BM25Okapi(tokens) if tokens else None}
        self.remote_indexes[key] = index
        return index

    def search(self, query: str, k: int = 4, grade: int = 5, subject: str = "evs1"):
        try:
            index = self._build_remote_index(grade, subject)
        except Exception:
            index = {"docs": [], "bm25": None}

        docs = index["docs"]
        bm25 = index["bm25"]
        if not bm25:
            if not self.local_docs:
                return []
            tokens = [tokenize(d["text"]) for d in self.local_docs]
            bm25 = BM25Okapi(tokens)
            docs = self.local_docs

        scores = bm25.get_scores(tokenize(query))
        idxs = sorted(range(len(scores)), key=lambda i: scores[i], reverse=True)[:k]
        return [docs[i] for i in idxs if scores[i] > 0]
