import json, re, unittest
from pathlib import Path
from urllib.parse import urlparse
DATA=json.loads((Path(__file__).parents[1]/'data/curriculum_sources.json').read_text())
HOSTS={'ncert.nic.in','www.ncert.nic.in','cbseacademic.nic.in','books.ebalbharati.in','ebooks.ebalbharati.in','www.mahahsscboard.in','scert.telangana.gov.in','www.scert.telangana.gov.in','www.nios.ac.in','nios.ac.in','cdn.nios.ac.in','rcpune.nios.ac.in','rcpatna.nios.ac.in'}
class SourcesTests(unittest.TestCase):
 def test_every_board_class_has_sources_and_explicit_pending_work(self):
  for board,max_grade in [('maharashtra',12),('cbse',12),('telangana',10),('nios',12)]:
   grades=DATA['boards'][board]['grades'];self.assertEqual(set(grades),{str(i) for i in range(1,max_grade+1)})
   for grade,row in grades.items():
    self.assertTrue(row['sources'],(board,grade));self.assertTrue(row['pending'],(board,grade))
    for source in row['sources']+row['books']+row['syllabi']:
     u=urlparse(source['url']);self.assertEqual(u.scheme,'https');self.assertIn(u.hostname,HOSTS);self.assertFalse(u.username or u.password)
     self.assertIn(source['status'],['publisher_listing','archived_reference'])
 def test_ncert_books_use_actual_publisher_catalogue_not_guessed_chapters(self):
  for grade,row in DATA['boards']['cbse']['grades'].items():
   self.assertTrue(row['books'],grade)
   for book in row['books']:
    self.assertEqual(book['format'],'chapter_catalogue');self.assertRegex(book['url'],r'/textbook\.php\?[a-z0-9]+=0-\d+$')
  for grade in ['3','5']:
   titles={b['title'] for b in DATA['boards']['cbse']['grades'][grade]['books']};self.assertIn('Santoor',titles);self.assertNotIn('Marigold',titles);self.assertNotIn('Math-Magic',titles)
 def test_balbharati_pagination_and_current_download_host(self):
  books=[b for r in DATA['boards']['maharashtra']['grades'].values() for b in r['books']]
  self.assertEqual(len(books),239);self.assertEqual(len({b['url'] for b in books}),239)
  self.assertTrue(all(b['url'].startswith('https://ebooks.ebalbharati.in/pdfs/') for b in books))
  self.assertIn('https://ebooks.ebalbharati.in/pdfs/303060004.pdf',{b['url'] for b in books})
  self.assertTrue(all(b['source_title'] for b in books))
 def test_nios_equivalence_and_no_cross_board_supplements(self):
  for grade,level in [('3','A'),('5','B'),('8','C')]:
   row=DATA['boards']['nios']['grades'][grade];self.assertIn('Level '+level,row['label']);self.assertTrue(row['syllabi']);self.assertTrue(row['books']);self.assertTrue(all('nios.ac.in' in b['url'] for b in row['books']))
  self.assertTrue(DATA['boards']['nios']['grades']['10']['syllabi']);self.assertFalse(DATA['boards']['nios']['grades']['12']['syllabi'])
  for grade in ['10','12']:self.assertGreater(len(DATA['boards']['nios']['grades'][grade]['books']),20)
 def test_telangana_archives_are_not_current_exam_syllabus(self):
  grades=DATA['boards']['telangana']['grades'];self.assertEqual(len(grades['10']['outlines']),3)
  self.assertTrue(all(b['status']=='archived_reference' for b in grades['10']['books']))
  self.assertTrue(all(not g['syllabi'] for g in grades.values()))
if __name__=='__main__':unittest.main()
