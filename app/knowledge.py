from pathlib import Path
from io import BytesIO
from html.parser import HTMLParser
from urllib.parse import urljoin
import re
import requests
from pypdf import PdfReader
from rank_bm25 import BM25Okapi

ROOT = Path(__file__).resolve().parents[1]
EXTRACTED = ROOT / "data" / "extracted"
NIOS_MATERIAL_PAGE = "https://rcgangtok.nios.ac.in/open-basic-education-courses-material.html"

REMOTE_BOOKS = {
    ("maharashtra", 5, "evs1"): {"title": "Environmental Studies Part One - Standard Five", "url": "https://books.ebalbharati.in/pdfs/503000541.pdf", "official": True},
    ("maharashtra", 5, "evs2"): {"title": "Environmental Studies Part Two - Standard Five", "url": "https://books.ebalbharati.in/pdfs/503000542.pdf", "official": True},
    ("maharashtra", 5, "math"): {"title": "Mathematics - Standard Five", "url": "https://books.ebalbharati.in/pdfs/503020004.pdf", "official": True},
    ("maharashtra", 5, "english"): {"title": "English Balbharati - Standard Five", "url": "https://books.ebalbharati.in/pdfs/503020001.pdf", "official": True},
    ("maharashtra", 3, "math"): {"title": "Mathematics - Standard Three", "url": "https://books.ebalbharati.in/pdfs/303020004.pdf", "official": True},
    ("maharashtra", 3, "english"): {"title": "English Balbharati - Standard Three", "url": "https://books.ebalbharati.in/pdfs/303020001.pdf", "official": True},
    ("nios", 3, "evs"): {"title": "NIOS OBE Level A - Environmental Studies", "url": "https://cdn.nios.ac.in/cms/documents/2020/Jul/09/EVS_Level_A_english_medium.pdf", "official": True},
    ("nios", 3, "computer"): {"title": "NIOS OBE Level A - Basic Computer Skills", "url": "https://cdn.nios.ac.in/cms/documents/2020/Jul/09/Basic_Computer-skills_Level_A_english_medium.pdf", "official": True},
}

NIOS_SUPPLEMENTS = {
    (3, "math"): {"title": "Supplemental Mathematics - Balbharati Standard Three", "url": "https://books.ebalbharati.in/pdfs/303020004.pdf", "official": False},
    (3, "english"): {"title": "Supplemental English Practice - Balbharati Standard Three", "url": "https://books.ebalbharati.in/pdfs/303020001.pdf", "official": False},
    (5, "math"): {"title": "Supplemental Mathematics - Balbharati Standard Five", "url": "https://books.ebalbharati.in/pdfs/503020004.pdf", "official": False},
    (5, "english"): {"title": "Supplemental English Practice - Balbharati Standard Five", "url": "https://books.ebalbharati.in/pdfs/503020001.pdf", "official": False},
    (5, "evs"): {"title": "Supplemental EVS - Balbharati Standard Five Part One", "url": "https://books.ebalbharati.in/pdfs/503000541.pdf", "official": False},
}

LIFE_SKILLS = {
    3: [
        "Personal safety: know your full name, a trusted adult's phone number, and how to ask a safe adult for help. Never share passwords or private information with strangers.",
        "Healthy routines: wash hands before eating, drink clean water, brush teeth twice a day, sleep on time, move your body every day, and choose a variety of foods.",
        "Home responsibility: keep books and toys in their place, help with one age-appropriate household task, and finish a small task before starting another.",
        "Money basics: coins and notes have value. Needs are things we must have; wants are things we would like. Saving means keeping some money for later.",
        "Communication: listen without interrupting, speak politely, say when you do not understand, and ask for help when a task feels difficult.",
    ],
    5: [
        "Personal safety and digital safety: protect passwords, avoid sharing personal information publicly, check with a trusted adult before clicking unknown links, and tell an adult about uncomfortable online contact.",
        "Planning: break a large task into small steps, estimate how long each step may take, start with the first clear step, and review what is left.",
        "Money basics: distinguish needs from wants, compare prices, make a simple budget, save part of available money, and record spending.",
        "Health habits: balanced food, daily movement, enough sleep, clean water, hygiene, and screen breaks support learning and wellbeing.",
        "Communication and problem solving: state the problem calmly, listen to the other person, suggest more than one solution, choose a fair option, and review the result.",
    ],
}

NIOS_EXPECTED = {
    3: {
        "evs": "Environmental Studies — NIOS Level A",
        "math": "Mathematics — NIOS Level A",
        "computer": "Basic Computer Skills — NIOS Level A",
        "english": "English / Reading — Supplemental",
        "life": "Life Skills & Projects — Supplemental",
    },
    5: {
        "evs": "Environmental Studies — NIOS Level B",
        "math": "Mathematics — NIOS Level B",
        "computer": "Basic Computer Skills — NIOS Level B",
        "english": "English / Reading — Supplemental",
        "life": "Life Skills & Projects — Supplemental",
    },
}

MAHARASHTRA_SUBJECTS = {
    3: {"math": "Mathematics", "english": "English"},
    5: {"evs1": "EVS Part 1", "evs2": "EVS Part 2", "math": "Mathematics", "english": "English"},
}


def tokenize(text: str):
    return re.findall(r"[A-Za-z0-9']+", text.lower())


def chunks(text: str, size: int = 1800, overlap: int = 250):
    text = re.sub(r"\s+", " ", text).strip()
    if not text:
        return []
    out, start = [], 0
    while start < len(text):
        out.append(text[start:start + size])
        if start + size >= len(text):
            break
        start += size - overlap
    return out


class _LinkParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.links = []
        self._href = None
        self._text = []

    def handle_starttag(self, tag, attrs):
        if tag.lower() == "a":
            self._href = dict(attrs).get("href")
            self._text = []

    def handle_data(self, data):
        if self._href is not None:
            self._text.append(data)

    def handle_endtag(self, tag):
        if tag.lower() == "a" and self._href is not None:
            text = re.sub(r"\s+", " ", " ".join(self._text)).strip()
            self.links.append((text, self._href))
            self._href = None
            self._text = []


class KnowledgeBase:
    def __init__(self):
        self.local_docs = []
        if EXTRACTED.exists():
            for path in sorted(EXTRACTED.glob("*.txt")):
                text = path.read_text(encoding="utf-8", errors="ignore")
                for i, part in enumerate(chunks(text)):
                    self.local_docs.append({"source": f"{path.name} chunk {i+1}", "text": part})
        self.remote_indexes = {}
        self._nios_discovered = None

    @property
    def docs(self):
        total = list(self.local_docs)
        for value in self.remote_indexes.values():
            total.extend(value["docs"])
        return total

    def _discover_nios(self):
        if self._nios_discovered is not None:
            return self._nios_discovered
        found = {}
        try:
            r = requests.get(NIOS_MATERIAL_PAGE, timeout=8)
            r.raise_for_status()
            parser = _LinkParser()
            parser.feed(r.text)
            aliases = {
                "a level evs": (3, "evs", "NIOS OBE Level A - Environmental Studies"),
                "a level environmental studies": (3, "evs", "NIOS OBE Level A - Environmental Studies"),
                "a level basic computer skills": (3, "computer", "NIOS OBE Level A - Basic Computer Skills"),
                "a level computer": (3, "computer", "NIOS OBE Level A - Basic Computer Skills"),
                "a level maths": (3, "math", "NIOS OBE Level A - Mathematics"),
                "a level mathematics": (3, "math", "NIOS OBE Level A - Mathematics"),
                "b level evs": (5, "evs", "NIOS OBE Level B - Environmental Studies"),
                "b level environmental studies": (5, "evs", "NIOS OBE Level B - Environmental Studies"),
                "b level maths": (5, "math", "NIOS OBE Level B - Mathematics"),
                "b level mathematics": (5, "math", "NIOS OBE Level B - Mathematics"),
                "b level computer skill": (5, "computer", "NIOS OBE Level B - Basic Computer Skills"),
                "b level basic computer skills": (5, "computer", "NIOS OBE Level B - Basic Computer Skills"),
                "b level computer": (5, "computer", "NIOS OBE Level B - Basic Computer Skills"),
            }
            for text, href in parser.links:
                norm = re.sub(r"[^a-z0-9]+", " ", text.lower()).strip()
                for phrase, (grade, subject, title) in aliases.items():
                    if phrase in norm and href:
                        found[(grade, subject)] = {"title": title, "url": urljoin(NIOS_MATERIAL_PAGE, href), "official": True}
        except Exception:
            pass
        self._nios_discovered = found
        return found

    def _local_life_index(self, grade: int):
        docs = []
        for i, text in enumerate(LIFE_SKILLS.get(grade, []), start=1):
            docs.append({"source": f"Chalo Padhaye Life Skills — activity {i}", "text": text, "supplemental": True})
        tokens = [tokenize(d["text"]) for d in docs]
        return {"docs": docs, "bm25": BM25Okapi(tokens) if tokens else None}

    def _book_for(self, curriculum: str, grade: int, subject: str):
        key = (curriculum, grade, subject)
        if key in REMOTE_BOOKS:
            return REMOTE_BOOKS[key]
        if curriculum == "nios":
            discovered = self._discover_nios().get((grade, subject))
            if discovered:
                return discovered
            return NIOS_SUPPLEMENTS.get((grade, subject))
        return None

    def catalog(self):
        maharashtra = {
            str(g): [
                {"id": sid, "label": label, "official": True, "supplemental": False, "available": True, "source_title": REMOTE_BOOKS.get(("maharashtra", g, sid), {}).get("title")}
                for sid, label in subjects.items()
            ]
            for g, subjects in MAHARASHTRA_SUBJECTS.items()
        }
        nios = {}
        for grade, subjects in NIOS_EXPECTED.items():
            rows = []
            for sid, label in subjects.items():
                if sid == "life":
                    rows.append({"id": sid, "label": label, "official": False, "supplemental": True, "available": bool(LIFE_SKILLS.get(grade)), "source_title": "Chalo Padhaye Life Skills"})
                    continue
                book = self._book_for("nios", grade, sid)
                rows.append({
                    "id": sid,
                    "label": label,
                    "official": bool(book and book.get("official")),
                    "supplemental": bool(book and not book.get("official")),
                    "available": bool(book),
                    "source_title": book.get("title") if book else None,
                })
            nios[str(grade)] = rows
        return {"maharashtra": maharashtra, "nios": nios}

    def _build_remote_index(self, grade: int, subject: str, curriculum: str = "maharashtra"):
        key = (curriculum, grade, subject)
        if key in self.remote_indexes:
            return self.remote_indexes[key]
        if curriculum == "nios" and subject == "life":
            index = self._local_life_index(grade)
            self.remote_indexes[key] = index
            return index

        book = self._book_for(curriculum, grade, subject)
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
            for part in chunks(text):
                docs.append({
                    "source": f"{book['title']} — page {page_no}",
                    "text": part,
                    "page": page_no,
                    "url": book.get("url"),
                    "supplemental": curriculum == "nios" and not bool(book.get("official")),
                })
        tokens = [tokenize(d["text"]) for d in docs]
        index = {"docs": docs, "bm25": BM25Okapi(tokens) if tokens else None}
        self.remote_indexes[key] = index
        return index

    def search(self, query: str, k: int = 4, grade: int = 5, subject: str = "evs1", curriculum: str = "maharashtra"):
        try:
            index = self._build_remote_index(grade, subject, curriculum)
        except Exception:
            index = {"docs": [], "bm25": None}
        docs, bm25 = index["docs"], index["bm25"]
        if not bm25 or not docs:
            return []
        scores = bm25.get_scores(tokenize(query))
        idxs = sorted(range(len(scores)), key=lambda i: scores[i], reverse=True)[:k]
        return [docs[i] for i in idxs if scores[i] > 0]
