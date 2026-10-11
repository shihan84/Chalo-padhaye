#!/usr/bin/env python3
"""Deploy only the Hostinger package through authenticated, pinned SSH/SFTP."""
import base64
import hashlib
import io
import json
import os
from pathlib import Path
import re
import socket
import shlex
import stat
import sys
import tempfile
import time
from urllib.request import urlopen
from urllib.error import HTTPError
from zipfile import ZipFile
from build_hostinger import build


PROGRESS={'phase':'configuration'}

def stage(name):
    PROGRESS['phase']=name
    print(name,flush=True)

def configuration(env):
    required=('HOSTINGER_DOMAIN','HOSTINGER_SSH_FINGERPRINT','HOSTINGER_SSH_HOST',
              'HOSTINGER_SSH_PORT','HOSTINGER_SSH_USER','HOSTINGER_SSH_PASSWORD',
              'DB_HOST','DB_NAME','DB_USER','DB_PASSWORD')
    missing=[key for key in required if not env.get(key)]
    if missing:
        raise ValueError('Missing deployment configuration: '+', '.join(missing))
    domain=env['HOSTINGER_DOMAIN']
    if not re.fullmatch(r'(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}',domain):
        raise ValueError('HOSTINGER_DOMAIN must be a lowercase domain, without a URL or path')
    if not re.fullmatch(r'SHA256:[A-Za-z0-9+/]{43}',env['HOSTINGER_SSH_FINGERPRINT']):
        raise ValueError('A trusted SHA256 SSH host fingerprint is required')
    port=int(env['HOSTINGER_SSH_PORT'])
    if not 1<=port<=65535:
        raise ValueError('Invalid SSH port')
    values={key:env.get(key,'') for key in ('DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASSWORD',
          'GROQ_API_KEY','GROQ_MODEL','FISH_AUDIO_API_KEY','FISH_AUDIO_MODEL','FISH_AUDIO_VOICE_ID')}
    values['DB_PORT']=values['DB_PORT'] or '3306'
    values['APP_ENV']='production'
    values['APP_URL']='https://'+domain
    # The application's simple .env parser cannot round-trip these characters.
    for value in values.values():
        if any(char in value for char in '\r\n\x00') or value!=value.strip() or value.startswith(('"',"'")) or value.endswith(('"',"'")):
            raise ValueError('An environment value cannot be represented safely by the application parser')
    return domain,port,('\n'.join(f'{key}={value}' for key,value in values.items())+'\n').encode()


def fingerprint(key):
    return 'SHA256:'+base64.b64encode(hashlib.sha256(key.asbytes()).digest()).decode().rstrip('=')


def verify_host_key(key,expected):
    if fingerprint(key)!=expected:
        raise ValueError('SSH server identity does not match the trusted fingerprint; no password was sent')


def ensure_dir(sftp,path):
    try:
        info=sftp.lstat(path)
        if not stat.S_ISDIR(info.st_mode):
            raise ValueError('Deployment directory is not a real directory')
    except FileNotFoundError:
        ensure_dir(sftp,str(Path(path).parent))
        sftp.mkdir(path,0o700)


def health(domain):
    # System HTTPS trust remains enabled; reject cross-domain redirects.
    try:
        response=urlopen('https://'+domain+'/api/health',timeout=20)
    except HTTPError as error:
        print('Live health HTTP status: '+str(error.code),flush=True)
        try:
            status=json.loads(error.read(65536))
            print('Live readiness: '+json.dumps({key:status.get(key) for key in ('ok','database','schema','runtime')}),flush=True)
        except Exception:
            print('Live health did not return application JSON',flush=True)
        raise
    with response:
        if response.url!='https://'+domain+'/api/health':
            raise ValueError('Health check redirected away from the deployment URL')
        data=json.loads(response.read(65536))
    if data.get('ok') is not True or data.get('database') is not True or data.get('schema') is not True or data.get('runtime')!='php':
        raise ValueError('Hostinger PHP database health check failed')


def deploy(env):
    domain,port,dotenv=configuration(env)
    import paramiko
    with tempfile.TemporaryDirectory() as temp:
        archive_path=build(Path(temp)/'release.zip')
        stage('Connecting to SSH server')
        sock=socket.create_connection((env['HOSTINGER_SSH_HOST'],port),timeout=20)
        transport=paramiko.Transport(sock)
        sftp=None
        try:
            stage('Negotiating SSH')
            transport.start_client(timeout=20)
            verify_host_key(transport.get_remote_server_key(),env['HOSTINGER_SSH_FINGERPRINT'])
            stage('SSH host identity verified; authenticating')
            transport.auth_password(env['HOSTINGER_SSH_USER'],env['HOSTINGER_SSH_PASSWORD'],fallback=False)
            stage('SSH authentication succeeded; opening SFTP')
            sftp=paramiko.SFTPClient.from_transport(transport)
            sftp.get_channel().settimeout(30)
            stage('Checking domain directory')
            home=sftp.normalize('.')
            root=home+'/domains/'+domain
            # Do not invent a domain directory or deploy into another site's root.
            for directory in (root,root+'/public_html'):
                if not stat.S_ISDIR(sftp.lstat(directory).st_mode):
                    raise ValueError('Target domain directory is unavailable')
            backup=root+'/chalo-backups/'+time.strftime('%Y%m%dT%H%M%S',time.gmtime())+'-'+env.get('GITHUB_RUN_ID','local')
            stage('Creating private rollback backup')
            ensure_dir(sftp,backup)
            with ZipFile(archive_path) as archive:
                files={name:archive.read(name) for name in archive.namelist() if name.startswith(('public_html/','chalo-private/')) and not name.endswith('.env.example')}
            files['chalo-private/.env']=dotenv
            changed=[]
            try:
                stage('Uploading Hostinger package')
                for name,data in files.items():
                    target=root+'/'+name
                    ensure_dir(sftp,str(Path(target).parent))
                    old=None
                    try:
                        info=sftp.lstat(target)
                        if not stat.S_ISREG(info.st_mode):
                            raise ValueError('Refusing to replace a symlink or non-file')
                        old=backup+'/'+name
                        ensure_dir(sftp,str(Path(old).parent))
                        with sftp.open(target,'rb') as source:
                            sftp.putfo(source,old)
                        sftp.chmod(old,0o600)
                    except FileNotFoundError:
                        pass
                    candidate=target+'.upload-'+env.get('GITHUB_RUN_ID','local')
                    sftp.putfo(io.BytesIO(data),candidate)
                    sftp.chmod(candidate,0o600 if name=='chalo-private/.env' else 0o644)
                    sftp.posix_rename(candidate,target)
                    changed.append((target,old))
                stage('Checking server PHP/database readiness')
                channel=transport.open_session(timeout=20)
                channel.settimeout(30)
                channel.exec_command('php '+shlex.quote(root+'/chalo-private/php/deployment_check.php'))
                output=channel.makefile('rb').read(16384)
                if channel.recv_exit_status()==0:
                    try:
                        readiness=json.loads(output)
                        print('Server readiness: '+json.dumps({key:readiness.get(key) for key in ('php','pdo_mysql','curl','database','schema','missing_tables','existing_tables','database_error_code','database_error_kind','configured_host_is_localhost','local_tcp_database','local_tcp_existing_tables','local_tcp_error_code')}),flush=True)
                    except Exception:
                        print('Server CLI check did not return readiness JSON',flush=True)
                else:
                    print('Server CLI check could not run',flush=True)
                channel.close()
                stage('Checking live HTTPS PHP and database health')
                health(domain)
            except Exception as failure:
                print('Failed phase: '+PROGRESS['phase']+'; error type: '+type(failure).__name__,flush=True)
                stage('Restoring replaced files')
                for target,old in reversed(changed):
                    if old:
                        with sftp.open(old,'rb') as source:
                            sftp.putfo(source,target+'.rollback')
                        sftp.chmod(target+'.rollback',0o600 if target.endswith('/.env') else 0o644)
                        sftp.posix_rename(target+'.rollback',target)
                    else:
                        sftp.remove(target)
                stage('Rollback completed')
                raise
            print('Hostinger upload completed; PHP/database health check passed. Prior files retained privately for rollback.')
        finally:
            if sftp:
                sftp.close()
            transport.close()


if __name__=='__main__':
    try:
        deploy(os.environ)
    except ValueError as exc:
        print(str(exc),file=sys.stderr)
        sys.exit(1)
    except Exception as exc:
        print('Error type: '+type(exc).__name__+'; phase: '+PROGRESS['phase'],file=sys.stderr)
        # Exceptions from SSH and providers may contain private host/user information.
        print('Hostinger deployment failed. Check configuration and server availability; secret values are not logged.',file=sys.stderr)
        sys.exit(1)
