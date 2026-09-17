from io import BytesIO
import re

import requests
from pypdf import PdfReader
from rank_bm25 import BM25Okapi

from .knowledge import KnowledgeBase as LegacyKnowledgeBase, chunks, tokenize


# Official NCERT textbook PDF prefixes. Class 9 was substantially refreshed for
# the 2026-27 session, so keep these mappings aligned with the current books.
CBSE_SUBJECTS = {
    1: {
        "math": {"label": "Mathematics — Joyful Mathematics", "title": "NCERT Joyful Mathematics - Class I", "prefix": "aejm1", "chapters": 13},
        "english": {"label": "English — Mridang", "title": "NCERT Mridang - Class I", "prefix": "aemr1", "chapters": 9},
    },
    9: {
        "math": {"label": "Mathematics — Ganita Manjari", "title": "NCERT Ganita Manjari - Grade 9 Part I", "prefix": "iemh1", "chapters": 8},
        "science": {"label": "Science — Exploration", "title": "NCERT Exploration - Grade 9", "prefix": "iesc1", "chapters": 13},
        "english": {"label": "English — Kaveri", "title": "NCERT Kaveri - Grade 9", "prefix": "iebe1", "chapters": 8},
        "social_science": {"label": "Social Science — Understanding Society", "title": "NCERT Understanding Society: India and Beyond - Grade 9 Part I", "prefix": "iest1", "chapters": 9},
    },
    10: {
        "math": {"label": "Mathematics — NCERT Class 10", "title": "NCERT Mathematics - Class X", "prefix": "jemh1", "chapters": 14},
        "science": {"label": "Science — NCERT Class 10", "title": "NCERT Science - Class X", "prefix": "jesc1", "chapters": 13},
        "english": {"label": "English — First Flight", "title": "NCERT First Flight - Class X", "prefix": "jeff1", "chapters": 11},
        "history": {"label": "Social Science — History", "title": "NCERT India and the Contemporary World II - Class X", "prefix": "jess3", "chapters": 5},
        "geography": {"label": "Social Science — Geography", "title": "NCERT Contemporary India II - Class X", "prefix": "jess1", "chapters": 7},
        "civics": {"label": "Social Science — Political Science", "title": "NCERT Democratic Politics II - Class X", "prefix": "jess4", "chapters": 5},
        "economics": {"label": "Social Science — Economics", "title": "NCERT Understanding Economic Development - Class X", "prefix": "jess2", "chapters": 5},
    },
}


class CBSEKnowledgeBase:
    def __init__(self):
        self.indexes = {}
        self.page_cache = {}

    @property
    def docs(self):
        out = []
        for value in self.indexes.values():
            out.extend(value.get("docs", []))
        return out

    def catalog(self):
        result = {}
        for grade, subjects in CBSE_SUBJECTS.items():
            result[str(grade)] = [
                {"id": sid, "label": meta["label"], "official": True, "supplemental": False, "available": True, "source_title": meta["title"]}
                for sid, meta in subjects.items()
            ]
        return result

    def _chapter_url(self, grade: int, subject: str, chapter_no: int):
        meta = CBSE_SUBJECTS.get(grade, {}).get(subject)
        if not meta:
            return None, None
        if chapter_no < 1 or chapter_no > int(meta["chapters"]):
            return meta, None
        return meta, f"https://www.ncert.nic.in/textbook/pdf/{meta['prefix']}{chapter_no:02d}.pdf"

    def page_context(self, grade: int, subject: str, chapter_no: int, page_no: int):
        """Return chunks from one exact page of one NCERT chapter PDF."""
        key = (grade, subject, chapter_no, page_no)
        if key in self.page_cache:
            return self.page_cache[key]
        meta, url = self._chapter_url(grade, subject, chapter_no)
        if not meta or not url:
            return []
        try:
            r = requests.get(url, timeout=20)
            if not r.ok or not r.content.startswith(b"%PDF"):
                return []
            reader = PdfReader(BytesIO(r.content))
            if page_no < 1 or page_no > len(reader.pages):
                return []
            text = reader.pages[page_no - 1].extract_text() or ""
        except Exception:
            return []
        docs = [
            {
                "source": f"{meta['title']} — chapter {chapter_no}, page {page_no}",
                "text": part,
                "page": page_no,
                "chapter": chapter_no,
                "url": url,
                "supplemental": False,
                "exact_page": True,
            }
            for part in chunks(text)
        ]
        self.page_cache[key] = docs
        return docs

    def _build_index(self, grade: int, subject: str):
        key = (grade, subject)
        if key in self.indexes:
            return self.indexes[key]
        meta = CBSE_SUBJECTS.get(grade, {}).get(subject)
        if not meta:
            return {"docs": [], "bm25": None}
        docs = []
        for chapter_no in range(1, int(meta["chapters"]) + 1):
            url = f"https://www.ncert.nic.in/textbook/pdf/{meta['prefix']}{chapter_no:02d}.pdf"
            try:
                r = requests.get(url, timeout=20)
                if not r.ok or not r.content.startswith(b"%PDF"):
                    continue
                reader = PdfReader(BytesIO(r.content))
            except Exception:
                continue
            for page_no, page in enumerate(reader.pages, start=1):
                try:
                    text = page.extract_text() or ""
                except Exception:
                    text = ""
                for part in chunks(text):
                    docs.append({"source": f"{meta['title']} — chapter {chapter_no}, page {page_no}", "text": part, "page": page_no, "chapter": chapter_no, "url": url, "supplemental": False})
        tokens = [tokenize(d["text"]) for d in docs]
        index = {"docs": docs, "bm25": BM25Okapi(tokens) if tokens else None}
        self.indexes[key] = index
        return index

    def search(self, query: str, k: int = 4, grade: int = 10, subject: str = "math"):
        marker = re.search(r"\[Textbook page:\s*(\d+)\s*;\s*Chapter number:\s*(\d+)\]", query or "", flags=re.I)
        if marker:
            page_no, chapter_no = int(marker.group(1)), int(marker.group(2))
            exact = self.page_context(grade, subject, chapter_no, page_no)
            if exact:
                return exact[:k]
        try:
            index = self._build_index(grade, subject)
        except Exception:
            index = {"docs": [], "bm25": None}
        docs, bm25 = index["docs"], index["bm25"]
        if not bm25 or not docs:
            return []
        scores = bm25.get_scores(tokenize(query))
        idxs = sorted(range(len(scores)), key=lambda i: scores[i], reverse=True)[:k]
        return [docs[i] for i in idxs if scores[i] > 0]


class KnowledgeBase:
    """One knowledge interface for Maharashtra, NIOS and CBSE/NCERT."""

    def __init__(self):
        self.legacy = LegacyKnowledgeBase()
        self.cbse = CBSEKnowledgeBase()

    @property
    def docs(self):
        return list(self.legacy.docs) + list(self.cbse.docs)

    def catalog(self):
        out = self.legacy.catalog()
        out["cbse"] = self.cbse.catalog()
        return out

    def page_context(self, grade: int, subject: str, curriculum: str, chapter_no: int, page_no: int):
        if curriculum == "cbse":
            return self.cbse.page_context(grade, subject, chapter_no, page_no)
        return []

    def search(self, query: str, k: int = 4, grade: int = 5, subject: str = "evs1", curriculum: str = "maharashtra"):
        if curriculum == "cbse":
            return self.cbse.search(query, k=k, grade=grade, subject=subject)
        return self.legacy.search(query, k=k, grade=grade, subject=subject, curriculum=curriculum)
