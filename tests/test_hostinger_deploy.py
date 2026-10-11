import base64
import hashlib
from pathlib import Path
import sys
import unittest
sys.path.insert(0,str(Path(__file__).parents[1]/'scripts'))
from deploy_hostinger import configuration,verify_host_key

class Key:
    def asbytes(self):return b'test host public key'

class DeployTests(unittest.TestCase):
    def setUp(self):
        self.env=dict(HOSTINGER_DOMAIN='school.example.com',HOSTINGER_SSH_FINGERPRINT='SHA256:'+base64.b64encode(hashlib.sha256(Key().asbytes()).digest()).decode().rstrip('='),HOSTINGER_SSH_HOST='ssh.example.com',HOSTINGER_SSH_PORT='65002',HOSTINGER_SSH_USER='fixture',HOSTINGER_SSH_PASSWORD='fixture-only',DB_HOST='localhost',DB_NAME='fixture',DB_USER='fixture',DB_PASSWORD='fixture-only')
    def test_correct_host_identity(self):verify_host_key(Key(),self.env['HOSTINGER_SSH_FINGERPRINT'])
    def test_wrong_host_identity(self):
        with self.assertRaises(ValueError):verify_host_key(Key(),'SHA256:'+'A'*43)
    def test_domain_path_injection_rejected(self):
        for bad in ('../other','https://example.com','school.example.com/../other','Example.com'):
            self.env['HOSTINGER_DOMAIN']=bad
            with self.assertRaises(ValueError):configuration(self.env)
    def test_secrets_only_in_private_env(self):
        domain,port,content=configuration(self.env)
        self.assertEqual(port,65002)
        self.assertIn(b'DB_PASSWORD=fixture-only',content)
        self.assertNotIn(b'HOSTINGER_SSH_PASSWORD',content)
    def test_env_injection_rejected(self):
        self.env['DB_PASSWORD']='value\nOTHER=bad'
        with self.assertRaises(ValueError):configuration(self.env)
    def test_missing_config_does_not_expose_secret(self):
        del self.env['HOSTINGER_DOMAIN']
        with self.assertRaisesRegex(ValueError,'HOSTINGER_DOMAIN') as result:configuration(self.env)
        self.assertNotIn('fixture-only',str(result.exception))

if __name__=='__main__':unittest.main()
