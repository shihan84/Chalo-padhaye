from pathlib import Path
from rank_bm25 import BM25Okapi
import re

ROOT = Path(__file__).resolve().parents[1]
EXTRACTED = ROOT / 'data' / 'extracted'

def tokenize(text: str):
    return re.findall(r"[A-Za-z0-9']+", text.lower())

class KnowledgeBase:
    def __init__(self):
        self.docs = []
        self.tokens = []
        if EXTRACTED.exists():
            for path in sorted(EXTRACTED.glob('*.txt')):
                text = path.read_text(encoding='utf-8', errors='ignore')
                self.docs.append({'source': path.name, 'text': text})
                self.tokens.append(tokenize(text))
        self.bm25 = BM25Okapi(self.tokens) if self.tokens else None

    def search(self, query: str, k: int = 4):
        if not self.bm25:
            return []
        scores = self.bm25.get_scores(tokenize(query))
        idxs = sorted(range(len(scores)), key=lambda i: scores[i], reverse=True)[:k]
        return [self.docs[i] for i in idxs if scores[i] > 0]
