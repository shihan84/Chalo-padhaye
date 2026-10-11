#!/usr/bin/env python3
"""Build a private, hash-pinned page index. PDFs and extracted text never enter git."""
import argparse, concurrent.futures, hashlib, json, re, subprocess, tempfile
from pathlib import Path
from urllib.parse import urlparse
from urllib.request import Request, build_opener, HTTPRedirectHandler
ROOT=Path(__file__).resolve().parents[1]
MAX_BYTES=64*1024*1024
class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl):
        raise ValueError('Publisher redirect requires review')
def validate_url(url):
    u=urlparse(url)
    if u.scheme!='https' or u.hostname not in {'ebooks.ebalbharati.in','cdn.nios.ac.in'} or u.username or u.password or u.port not in (None,443) or u.query or u.fragment or not u.path.endswith('.pdf'):
        raise ValueError('Unapproved textbook source')
def extract_pages(pdf):
    result=subprocess.run(['pdftotext','-layout',str(pdf),'-'],capture_output=True,check=True,timeout=90)
    text=result.stdout.decode('utf-8',errors='replace');pages=text.split('\f')
    if not pages[-1].strip():pages.pop()
    if not 1<=len(pages)<=600:raise ValueError('Invalid page count')
    return [re.sub(r'[\x00-\x08\x0b\x0e-\x1f]','',p).strip()[:16000] for p in pages]
def build_one(book,output,cache=None):
    validate_url(book['url'])
    with tempfile.TemporaryDirectory() as tmp:
        pdf=Path(tmp)/'source.pdf';cached=Path(cache)/book['url'].split('/')[-1] if cache else None
        if cached and cached.is_file():data=cached.read_bytes()
        else:
            with build_opener(NoRedirect).open(Request(book['url']),timeout=60) as response:data=response.read(MAX_BYTES+1)
        if len(data)>MAX_BYTES or not data.startswith(b'%PDF-'):raise ValueError('Invalid or oversized publisher PDF')
        if hashlib.sha256(data).hexdigest()!=book['sha256']:raise ValueError('Publisher PDF changed; review its new edition before release')
        pdf.write_bytes(data);pages=extract_pages(pdf)
        if len(pages)!=book['page_count'] or sum(len(p)>80 for p in pages)<book['min_text_pages']:raise ValueError('Text extraction requires review')
        payload={'version':1,'id':book['id'],'sha256':book['sha256'],'pages':pages}
        (Path(output)/(book['id']+'.json')).write_text(json.dumps(payload,ensure_ascii=False),encoding='utf-8')
        return book['id'],len(pages)
def build_corpus(output,cache=None):
    Path(output).mkdir(parents=True,exist_ok=True)
    books=json.loads((ROOT/'data/textbook_manifest.json').read_text())['books']
    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
        results=list(pool.map(lambda b:build_one(b,output,cache),books))
    return results
if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('--output',required=True);p.add_argument('--cache');a=p.parse_args()
    for book,pages in build_corpus(a.output,a.cache):print(f'Verified {book}: {pages} PDF pages')
