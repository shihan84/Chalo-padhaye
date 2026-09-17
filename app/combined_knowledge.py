from io import BytesIO

import requests
from pypdf import PdfReader
from rank_bm25 import BM25Okapi

from .knowledge import KnowledgeBase as LegacyKnowledgeBase, chunks, tokenize


CBSE_SUBJECTS = {
    10: {
        "math": {
            "label": "Mathematics — NCERT Class 10",
            "title": "NCERT Mathematics - Class X",
            "prefix": "jemh1",
            "chapters": 14,
        },
        "science": {
            "label": "Science — NCERT Class 10",
            "title": "NCERT Science - Class X",
            "prefix": "jesc1",
            "chapters": 13,
        },
        "english": {
            "label": "English — First Flight",
            "title": "NCERT First Flight - Class X",
            "prefix": "jeff1",
            "chapters": 11,
        },
        "history": {
            "label": "Social Science — History",
            "title": "NCERT India and the Contemporary World II - Class X",
            "prefix": "jess3",
            "chapters": 5,
        },
        "geography": {
            "label": "Social Science — Geography",
            "title": "NCERT Contemporary India II - Class X",
            "prefix": "jess1",
            "chapters": 7,
        },
        "economics": {
            "label": "Social Science — Economics",
            "title": "NCERT Understanding Economic Development - Class X",
            "prefix": "jess2",
            "chapters": 5,
        },
    }
}


class CBSEKnowledgeBase:
    def __init__(self):
        self.indexes = {}

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
                {
                    "id": sid,
                    "label": meta["label"],
                    "official": True,
                    "supplemental": False,
                    "available": True,
                    "source_title": meta["title"],
                }
                for sid, meta in subjects.items()
            ]
        return result

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
                    docs.append(
                        {
                            "source": f"{meta['title']} — chapter {chapter_no}, page {page_no}",
                            "text": part,
                            "page": page_no,
                            "url": url,
                            "supplemental": False,
                        }
                    )

        tokens = [tokenize(d["text"]) for d in docs]
        index = {"docs": docs, "bm25": BM25Okapi(tokens) if tokens else None}
        self.indexes[key] = index
        return index

    def search(self, query: str, k: int = 4, grade: int = 10, subject: str = "math"):
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

    def search(self, query: str, k: int = 4, grade: int = 5, subject: str = "evs1", curriculum: str = "maharashtra"):
        if curriculum == "cbse":
            return self.cbse.search(query, k=k, grade=grade, subject=subject)
        return self.legacy.search(query, k=k, grade=grade, subject=subject, curriculum=curriculum)
