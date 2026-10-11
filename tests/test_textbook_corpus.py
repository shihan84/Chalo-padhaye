import hashlib,importlib.util,json,unittest
from pathlib import Path
from tempfile import TemporaryDirectory
from unittest.mock import patch
spec=importlib.util.spec_from_file_location('corpus',Path(__file__).parents[1]/'scripts/build_textbook_corpus.py');mod=importlib.util.module_from_spec(spec);spec.loader.exec_module(mod)
class CorpusTests(unittest.TestCase):
 def test_source_allowlist(self):
  for u in ['http://cdn.nios.ac.in/x.pdf','https://evil.test/x.pdf','https://cdn.nios.ac.in.evil.test/x.pdf','https://user@cdn.nios.ac.in/x.pdf','https://cdn.nios.ac.in:8080/x.pdf','https://cdn.nios.ac.in/x.pdf?a=b']:
   with self.assertRaises(ValueError):mod.validate_url(u)
  mod.validate_url('https://ebooks.ebalbharati.in/pdfs/303060004.pdf')
 def test_changed_publisher_pdf_is_rejected_before_extraction(self):
  with TemporaryDirectory() as tmp:
   Path(tmp,'book.pdf').write_bytes(b'%PDF-1.7\nfixture')
   book={'url':'https://cdn.nios.ac.in/book.pdf','sha256':'0'*64}
   with patch.object(mod,'extract_pages') as extract:
    with self.assertRaisesRegex(ValueError,'changed'):mod.build_one(book,tmp,tmp)
    extract.assert_not_called()
 def test_manifest_has_real_pin_and_valid_chapter_pages(self):
  books=json.loads((mod.ROOT/'data/textbook_manifest.json').read_text())['books'];self.assertEqual(len(books),11)
  for b in books:
   mod.validate_url(b['url']);self.assertRegex(b['sha256'],r'^[0-9a-f]{64}$');self.assertEqual(b['id'],'book-'+b['sha256'][:24]);self.assertTrue(b['chapters'])
   self.assertTrue(all(1<=c['pdf_page']<=b['page_count'] for c in b['chapters']))
 def test_no_redirects(self):
  with self.assertRaises(ValueError):mod.NoRedirect().redirect_request(None,None,302,'',{},'https://evil.test/')
if __name__=='__main__':unittest.main()
