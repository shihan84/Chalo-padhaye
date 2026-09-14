from pathlib import Path
import json, requests

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'data' / 'textbooks'
OUT.mkdir(parents=True, exist_ok=True)
manifest = json.loads((ROOT/'data'/'materials.json').read_text())
for item in manifest['sources']:
    p = OUT / f"{item['id']}.pdf"
    if p.exists():
        print('exists', p)
        continue
    print('downloading', item['title'])
    r = requests.get(item['url'], timeout=60)
    r.raise_for_status()
    p.write_bytes(r.content)
    print('saved', p)
