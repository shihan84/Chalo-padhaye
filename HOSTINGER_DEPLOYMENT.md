# Hostinger deployment

Deploy the Hostinger feature branch only. Do not merge it into main or use the Vercel deployment.

Build the upload archive with `python scripts/build_hostinger.py --output /tmp/chalo-hostinger.zip`. The package uses an explicit file list and excludes real environment files, repository metadata, Python/Supabase code, databases and textbooks.

Extract at the domain directory, one level above its public document root. The resulting `public_html` contains the browser and API entry point; `chalo-private` is its sibling and contains the PHP implementation and lesson catalog. Never put `chalo-private` inside `public_html`. The public root must serve this domain only. Back up existing web files and database before replacing anything; preserve that backup outside the web root. Do not overwrite an existing production site until its domain and rollback have been verified.

Use PHP 8.2 or newer with PDO MySQL and cURL, HTTPS and production error logging (display_errors disabled). Create `chalo-private/.env` securely from `.env.example` with the site's MySQL details. Do not upload credentials to GitHub or chat. Create/use the dedicated application database. Import `database/mysql/schema.sql` only into a verified fresh empty database; for existing databases review migrations and back up first. The archive itself excludes SQL. The SSH installer may initialize the verified empty application database, as described below.

Check `/`, `/core.js`, `/api/config` and `/api/health`. Health must report `ok:true` and `database:true`. Verify login, parent/profile ownership, first-child creation, full student invite enrollment and logout revocation on the actual domain. Verify `/.env`, `/.git/config`, `/chalo-private/.env` and `/database/mysql/schema.sql` are not accessible. If any check fails, restore the prior public files; leave database rollback to the backed-up migration plan.

This is an account/profile release. Trusted textbook tutoring remains unavailable and server-owned assessments, rate limits, account recovery and hosted browser verification remain open. Prefer a separate staging domain for first deployment.

Hostinger references: https://www.hostinger.com/support/1583307-how-to-create-an-htaccess-file-at-hostinger/ and https://support.hostinger.com/en/articles/6152127-how-to-deploy-laravel-8-at-hostinger (public-root separation guidance).

## SSH workflow

`.github/workflows/hostinger-deploy.yml` deploys only on pushes to `deploy/hostinger-release`. It uses the existing `HOSTINGER_SSH_HOST`, `HOSTINGER_SSH_PORT`, `HOSTINGER_SSH_USER`, `HOSTINGER_SSH_PASSWORD`, database and model secrets. The authorized target domain is `chalopadhaye.com`, configured in the workflow. The workflow pins the Ed25519 host fingerprint supplied from the user’s saved Mac SSH host entry for `82.112.232.137:65002`, matching their active `in-mum-web1641` session: `SHA256:CTI5B54rLm/lNqrGkxMkE2PUXS1UPZiqGU17/hXAPWw`. The script checks it before sending the SSH password. No secret values are printed.

The SSH script selects the domain directory under the authenticated home, retains previous versions of replaced files in a private backup folder, writes environment configuration outside `public_html`, and restores replaced files if upload/HTTPS database health fails. It imports the fresh schema only after a server-side check confirms the target database has zero tables. An advisory lock and a second empty-database check guard initialization. Nonempty databases require reviewed migrations; existing tables are never automatically altered. MySQL DDL cannot be transactionally rolled back, so a failed bootstrap leaves schema state for inspection. Website rollback does not delete database tables. DNS is unchanged. Directory-layout, SFTP extension, PHP permissions and Apache routing compatibility still require a first staging run; an SSH fingerprint or passing unit tests alone do not establish deployment success.

The original DB_HOST secret failed hostname resolution. Read-only server testing verified the configured database credentials against local MySQL on `127.0.0.1`, with zero existing tables; the workflow now uses that verified local endpoint.
