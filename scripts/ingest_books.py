from pathlib import Path
import json
from pypdf import PdfReader

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / 'data' / 'textbooks'
OUT = ROOT / 'data' / 'extracted'
OUT.mkdir(parents=True, exist_ok=True)

records = []
for pdf in sorted(SRC.glob('*.pdf')):
    reader = PdfReader(str(pdf))
    for i, page in enumerate(reader.pages, start=1):
        text = (page.extract_text() or '').strip()
        if not text:
            continue
        out = OUT / f'{pdf.stem}_p{i:03d}.txt'
        out.write_text(text, encoding='utf-8')
        records.append({'book': pdf.stem, 'page': i, 'path': str(out.relative_to(ROOT))})

(ROOT/'data'/'index.json').write_text(json.dumps(records, indent=2), encoding='utf-8')
print(f'Indexed {len(records)} pages')
