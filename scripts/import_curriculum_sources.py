#!/usr/bin/env python3
"""Import publisher HTML snapshots, never generate textbook filenames.
Usage: python scripts/import_curriculum_sources.py /tmp/chalo-
Snapshots are temporary research inputs, not shipped books. Validate and review
publisher changes before re-running; this is not a production network scraper.
"""
import sys, json, re, html, hashlib
from pathlib import Path
from urllib.parse import urljoin
ROOT=Path(__file__).resolve().parents[1]
prefix=sys.argv[1]
def read(name): return Path(prefix+name+'.html').read_text()
def text(s): return html.unescape(re.sub('<[^>]*>','',s)).strip()
def link(title,url,kind='textbook',**kw): return dict(title=title,url=url,kind=kind,**({'status':'publisher_listing'}|kw))
checked='2026-10-11'
boards={}
for key,label,n,directory in [('cbse','CBSE / NCERT',12,'https://www.ncert.nic.in/textbook.php?ln=en'),('maharashtra','Maharashtra / Balbharati',12,'https://books.ebalbharati.in/'),('telangana','Telangana / SCERT',10,'https://www.scert.telangana.gov.in/DisplayContent.aspx?encry=ammkNW4%2Fgx%2BNeApstGPX%2BA%3D%3D'),('nios','NIOS',12,'https://www.nios.ac.in/online-course-material.aspx')]:
 boards[key]=dict(label=label,grades={str(g):dict(label=f'Class {g}',sources=[link('Official textbook directory',directory,'directory')],books=[],syllabi=[],outlines=[],pending=['Chapter outlines and school-prescribed edition need verification.','Textbook-grounded tutoring is not connected.']) for g in range(1,n+1)})
# NCERT publishes the actual class/subject/book selector in its HTML.
s=read('ncert');s=re.sub(r'/\*.*?\*/','',s,flags=re.S);s=re.sub(r'//[^\n]*','',s)
pattern=r'(?:else\s+)?if\(\(document.test.tclass.value\s*==\s*(\d+)\)\s*&&\s*\(document.test.tsubject.options\[sind\].text\s*==\s*"([^"]+)"\)\)\s*\{(.*?)\}'
for grade,subject,body in re.findall(pattern,s,re.S):
 if grade not in boards['cbse']['grades']: continue
 rows={}
 for i,field,val in re.findall(r'options\[(\d+)\].(text|value)\s*=\s*"([^"]+)"',body):rows.setdefault(i,{})[field]=val
 for row in rows.values():
  val=row.get('value',''); title=row.get('text','')
  if not title or not re.fullmatch(r'textbook\.php\?[a-z0-9]+=0-\d+',val):continue
  entry=boards['cbse']['grades'][grade]
  book=link(title,urljoin('https://www.ncert.nic.in/',val),subject=subject,format='chapter_catalogue',medium='See book title / publisher',edition='Listed in current NCERT selector; confirm school edition')
  # Prefer English textbooks without excluding language subjects or translated books.
  code=val.split('?')[1].split('=')[0]
  book['primary']=len(code)>1 and code[1]=='e'
  if book['url'] not in {b['url'] for b in entry['books']}:entry['books'].append(book)
# Import every linked CBSE subject, with separate Class IX, X and XI-XII sections.
s=read('cbse');starts=[s.index('Secondary Curriculum: Part - 1 (Class IX)'),s.index('Secondary Curriculum: Part - 1 (Class X)'),s.index('Secondary Curriculum: Part - 2 (XI-XII)')]
for idx,grades in enumerate([(9,),(10,),(11,12)]):
 block=s[starts[idx]:starts[idx+1] if idx<2 else len(s)]
 links=[]
 for href,title in re.findall(r'<a\b[^>]*href="([^"]+)"[^>]*>(.*?)</a>',block,re.S):
  if 'CurriculumMain27/' in href and href.endswith('.pdf'):links.append(link(text(title),urljoin('https://cbseacademic.nic.in/',href),'syllabus',edition='2026–27'))
 for g in grades:
  boards['cbse']['grades'][str(g)]['syllabi']=links
  boards['cbse']['grades'][str(g)]['sources'].append(link('CBSE curriculum 2026–27','https://cbseacademic.nic.in/curriculum_2027.html','syllabus_directory'))
for g in range(1,9):boards['cbse']['grades'][str(g)]['pending'].append('No separate annual CBSE primary/middle examination syllabus imported; use school-prescribed NCERT books.')
# Balbharati English-medium textbook filter: all 20 pages (239 entries).
paths=[Path(prefix+'bal-filter.html')]+[Path(prefix+f'bal-page{i}.html') for i in range(2,21)]
assert all(p.exists() for p in paths),'Incomplete Balbharati pagination'
seen=set()
for p in paths:
 s=p.read_text()
 for title,url in re.findall(r"class=divbooknm title='([^']+)'[^>]*>.*?SaveToDisk\(\"([^\"]+)\"",s,re.S):
  if url in seen:continue
  seen.add(url);title=text(title)
  match=re.match(r'(\d+)',title.translate(str.maketrans('०१२३४५६७८९','0123456789')))
  if not match or match[1] not in boards['maharashtra']['grades']:continue
  labels=[('गणित','Mathematics'),('बालभारती','Balbharati'),('परिसर','Environmental Studies'),('आपल्या सभोवतालचे जग','The World Around Us'),('आ.स.ज.','The World Around Us (district edition)'),('इतिहास','History'),('भूगोल','Geography'),('विज्ञान','Science'),('कला शिक्षण','Arts'),('शारीरिक','Physical Education'),('कार्यशिक्षण','Work Education'),('मराठी','Marathi language')]
  subject=next((en for mr,en in labels if mr in title),'Publisher textbook')
  display=f'Class {match[1]} · {subject} · Book {url.rsplit("/",1)[1][:-4]}'
  boards['maharashtra']['grades'][match[1]]['books'].append(link(display,url,source_title=title,subject=subject,medium='English-medium catalogue (language books may differ)',format='pdf',edition='Syllabus-year 2026 catalogue; confirm cover edition',primary=True))
assert len(seen)==239, f'Balbharati pagination lost books: {len(seen)}'
for entry in boards['maharashtra']['grades'].values():entry['pending'].append('Separate current examination syllabus and prescribed subject set need verification; catalogue year is not an exam syllabus.')
# Keep archived Telangana outlines labelled exactly as reviewed previously.
ts=json.loads((ROOT/'data/telangana_syllabus.json').read_text())
for grade,old in ts['grades'].items():
 entry=boards['telangana']['grades'][grade];entry['label']=old['label'];entry['subjects']=[x['label'] for x in old['subjects']]
 entry['sources'].append(link('SCERT syllabus directory',ts['syllabus_url'],'syllabus_directory'))
 entry['pending'].append('SCERT directory lists 2025–26; current 2026–27 editions and direct textbook mappings remain pending. Directory returned gateway errors during this review.')
 for sub in old['subjects']:
  if sub['chapters']:
   entry['outlines'].append(dict(label=sub['label'],chapters=sub['chapters'],edition=sub['source_edition'],url=sub['source_url'],status='archived_reference'))
   entry['books'].append(link(sub['label'],sub['source_url'],subject=sub['label'],format='pdf',medium='English',edition=sub['source_edition'],status='archived_reference',primary=True))
# NIOS OBE uses levels rather than a separate textbook for each year.
obe='https://rcpune.nios.ac.in/open-basic-education-courses-material.html';curr='https://rcpatna.nios.ac.in/open-basic-educationobe.html'
s=read('nios');english=s[s.index('<h2><strong>English Medium'):s.index('<h2><strong>Hindi Medium')]
materials={'A':[],'B':[],'C':[]};missing={'A':[],'B':[],'C':[]}
for row in re.findall(r'<tr\b[^>]*>(.*?)</tr>',english,re.S):
 cells=re.findall(r'<td\b[^>]*>(.*?)</td>',row,re.S)
 if not cells:continue
 title=text(cells[0]);m=re.match(r'([ABC]) level\s*-\s*(.+)',title,re.I)
 if not m:continue
 urls=re.findall(r'href="([^"]+\.pdf)"',row)
 if urls:materials[m[1].upper()].append(link(m[2],urls[0],subject=m[2],format='pdf',medium='English',edition='Publisher resource uploaded 2020; confirm with accredited agency',primary=True))
 else:missing[m[1].upper()].append(m[2]+' textbook link missing from publisher table.')
s=read('patna');curricula={}
for href,title in re.findall(r'<a\b[^>]*href="([^"]+)"[^>]*>(.*?)</a>',s,re.S):
 title=text(title);m=re.search(r'Education ([ABC]) Level - Curriculum in English',title)
 if m:curricula[m[1]]=link('Level '+m[1]+' curriculum (reference; confirm child/adult track)',href,'syllabus',edition='Official archived reference; document includes adult-track content')
assert set(curricula)=={'A','B','C'}
for g in range(1,9):
 level='A' if g<=3 else 'B' if g<=5 else 'C';entry=boards['nios']['grades'][str(g)]
 entry['label']=f'OBE Level {level} · equivalent classes '+('1–3' if level=='A' else '4–5' if level=='B' else '6–8')
 entry['sources']=[link('Official OBE textbooks',obe,'directory'),link('Official OBE curriculum and levels',curr,'syllabus_directory')];entry['books']=materials[level];entry['syllabi']=[curricula[level]];entry['pending']+=missing[level]+['Language books, child-track curriculum and agency-prescribed subject choices need verification. No Balbharati supplements are labelled official NIOS.']
for kind,grades in [('secondary',(9,10)),('senior',(11,12))]:
 directory=f'https://rcpune.nios.ac.in/page/{"secondary" if kind=="secondary" else "senior-secondary"}-courses-material.html'
 page=read('nios-'+kind);page=page[page.index('<table'):page.index('Awards & Achievement')]
 books=[];syllabi=[];seen=set()
 for row in re.findall(r'<tr\b[^>]*>(.*?)</tr>',page,re.S):
  cells=re.findall(r'<td\b[^>]*>(.*?)</td>',row,re.S)
  urls=re.findall(r'href="([^"]+)"',row)
  if not cells or not urls:continue
  title=text(cells[0]);url=urljoin('https://rcpune.nios.ac.in/',urls[0])
  if title.startswith('CURRICUL'):
   # Senior table currently points to seccurriculum.pdf; do not assume it is senior-specific.
   if kind=='secondary':syllabi.append(link(title,url,'syllabus',edition='Archived 2020 curriculum; current agency/exam syllabus needs confirmation'))
  elif re.search(r'\b[23]\d\d\b',title) and url not in seen:
   seen.add(url);books.append(link(title,url,subject=title,format='chapter_catalogue',medium='Choose medium on publisher page',edition='Publisher subject material; confirm current syllabus/edition',primary=True))
 for g in grades:
  e=boards['nios']['grades'][str(g)];e['label']='NIOS '+('Secondary · Class 10 equivalent' if g<=10 else 'Senior Secondary · Class 12 equivalent');e['books']=books;e['syllabi']=syllabi;e['sources']=[link('Official subject textbooks and curriculum',directory,'directory')]
  e['pending'].append('Current exam syllabus, chosen subject set and chapter outlines need verification; these courses are not OBE Levels A/B/C.')
  if kind=='senior':e['pending'].append('Senior curriculum table links to a file named seccurriculum.pdf; verify its contents before importing it as Senior Secondary syllabus.')
for g in range(9,13):
 boards['maharashtra']['grades'][str(g)]['sources'].append(link('Official SSC/HSC board website and syllabus','https://www.mahahsscboard.in/','syllabus_directory'))

registry=dict(version=1,reviewed_on=checked,notice='Browse official publisher sources for all boards. Choose your school’s medium and edition. Pending items are shown explicitly. Source links do not enable AI textbook lessons.',boards=boards)
registry['source_snapshots']={p.name:hashlib.sha256(p.read_bytes()).hexdigest() for p in paths+[Path(prefix+k+'.html') for k in ['ncert','cbse','nios','patna','nios-secondary','nios-senior']]}
(ROOT/'data/curriculum_sources.json').write_text(json.dumps(registry,ensure_ascii=False,indent=2)+'\n')
print({k:dict(classes=len(v['grades']),books=sum(len(e['books']) for e in v['grades'].values()),syllabi=sum(len(e['syllabi']) for e in v['grades'].values())) for k,v in boards.items()})
