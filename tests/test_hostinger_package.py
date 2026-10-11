import importlib.util
from pathlib import Path
from tempfile import TemporaryDirectory
from zipfile import ZipFile
import unittest

spec=importlib.util.spec_from_file_location('builder',Path(__file__).parents[1]/'scripts/build_hostinger.py')
builder=importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)

class PackageTests(unittest.TestCase):
    def test_public_private_separation(self):
        with TemporaryDirectory() as temp:
            package=builder.build(Path(temp)/'release.zip')
            with ZipFile(package) as z:
                names=z.namelist()
                self.assertEqual(len(names),len(set(names)))
                self.assertIn('public_html/index.php',names)
                self.assertIn('chalo-private/php/api/index.php',names)
                self.assertIn('chalo-private/.env.example',names)
                self.assertIn('chalo-private/data/telangana_syllabus.json',names)
                self.assertIn('chalo-private/data/curriculum_sources.json',names)
                self.assertIn('chalo-private/php/curriculum_library.php',names)
                self.assertIn('chalo-private/php/textbook.php',names)
                self.assertIn('chalo-private/data/textbook_manifest.json',names)
                self.assertFalse(any('textbook-corpus/' in x for x in names))
                self.assertIn('public_html/practice.js',names)
                self.assertIn('chalo-private/php/fractions.php',names)
                self.assertLess(names.index('chalo-private/php/fractions.php'),names.index('chalo-private/php/practice.php'))
                self.assertFalse(any(x.endswith('/.env') or '.git/' in x or x.endswith('.sql') or x.endswith('.pdf') for x in names))
                self.assertEqual([x for x in names if x.startswith('public_html/') and x.endswith('.php')],['public_html/index.php'])
                self.assertIn("dirname(__DIR__).'/chalo-private/php/api/index.php'",z.read('public_html/index.php').decode())

if __name__=='__main__':
    unittest.main()
