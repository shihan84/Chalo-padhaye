"""Synthetic CI-only page text; no publisher fetches or paid provider requests."""
import json
from pathlib import Path
root=Path(__file__).parents[1]
b=next(b for b in json.loads((root/'data/textbook_manifest.json').read_text())['books'] if b['url'].endswith('/303060004.pdf'))
folder=root/'data/textbook-corpus';folder.mkdir(exist_ok=True)
text='A square is a shape with four equal sides. We can identify different geometric shapes around us.'
(folder/(b['id']+'.json')).write_text(json.dumps({'id':b['id'],'sha256':b['sha256'],'pages':[text]*b['page_count']}))
print('Created synthetic CI textbook fixture')
