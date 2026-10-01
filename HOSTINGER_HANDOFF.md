# Chalo Padhaye — Hostinger VPS Deployment Handoff

Updated: 2026-10-01
Source repository: `shihan84/Chalo-padhaye`
Source branch: `main`
Current production: Vercel
Target: Hostinger VPS
Migration principle: keep Vercel live until Hostinger passes all smoke tests.

## 1. Application architecture

Chalo Padhaye is a single Python/FastAPI web application. FastAPI serves both the API and the static frontend.

- Entry point: `app.main:app`
- Backend: FastAPI + Python
- Frontend: static HTML/CSS/JavaScript under `frontend/`
- Database/auth: hosted Supabase Auth + Postgres + RLS
- AI: Groq API; optional local Ollama fallback
- TTS: Fish Audio API; browser speech synthesis is also used
- Retrieval: runtime HTTP download/extraction + BM25 for curriculum sources
- Persistent user learning data: Supabase, not local VPS disk
- Local/generated textbook files are intentionally ignored by Git.

There is no Node build step and no local SQL database required.

## 2. Hostinger hosting requirement

Use a Hostinger VPS with an Ubuntu LTS image. Ordinary static/shared hosting is not the intended target because the application needs a persistent Python ASGI process.

Recommended production topology:

Internet -> HTTPS 443 -> Nginx -> Uvicorn/FastAPI on 127.0.0.1:8000

Run Uvicorn under systemd so it starts at boot and restarts on failure. Do not expose port 8000 publicly.

## 3. Environment variables

Create a server-owned environment file outside Git, for example `/etc/chalo-padhaye.env`:

```env
NEXT_PUBLIC_SUPABASE_URL=
NEXT_PUBLIC_SUPABASE_ANON_KEY=
GROQ_API_KEY=
GROQ_MODEL=openai/gpt-oss-20b
FISH_AUDIO_API_KEY=
FISH_AUDIO_VOICE_ID=
FISH_AUDIO_MODEL=s2.1-pro-free
# Optional only when Ollama is installed on this VPS:
OLLAMA_URL=http://127.0.0.1:11434/api/generate
OLLAMA_MODEL=qwen2.5:3b
```

Notes:
- The Supabase anon key is intentionally exposed to the browser by `/api/config`; RLS is the data security boundary.
- GROQ and Fish Audio keys must remain server-side.
- A Supabase service-role key is not used or required.
- Never commit the production environment file.

## 4. Database state

Keep the existing Supabase project during the hosting migration. Do not create a new database unless a separate database migration is explicitly planned.

Expected SQL order:
1. `supabase/schema.sql`
2. `supabase/002_parent_auth_rls.sql`
3. `supabase/003_homeschool_records.sql`
4. `supabase/004_lesson_mastery.sql`
5. `supabase/005_cbse_support.sql`
6. `supabase/006_student_login.sql`

Production must be checked for migration 006. The repository plan still marks it as not yet confirmed/applied. Verify before enabling student-login workflows.

## 5. Initial VPS installation

Example Ubuntu setup:

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git python3 python3-venv python3-pip nginx
sudo mkdir -p /opt/chalo-padhaye
sudo chown "$USER":"$USER" /opt/chalo-padhaye
git clone https://github.com/shihan84/Chalo-padhaye.git /opt/chalo-padhaye
cd /opt/chalo-padhaye
python3 -m venv .venv
source .venv/bin/activate
python -m pip install --upgrade pip
pip install -r requirements.txt
```

Do not run `scripts/bootstrap_mac.sh` as the production deployment procedure; it was designed for local/macOS bootstrap and also downloads/indexes books.

## 6. systemd service

Create `/etc/systemd/system/chalo-padhaye.service`:

```ini
[Unit]
Description=Chalo Padhaye FastAPI
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/opt/chalo-padhaye
EnvironmentFile=/etc/chalo-padhaye.env
ExecStart=/opt/chalo-padhaye/.venv/bin/uvicorn app.main:app --host 127.0.0.1 --port 8000 --workers 2
Restart=always
RestartSec=5
TimeoutStopSec=30

[Install]
WantedBy=multi-user.target
```

Ensure `www-data` can read the repository and environment file, then:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now chalo-padhaye
sudo systemctl status chalo-padhaye
curl http://127.0.0.1:8000/api/health
```

Start with 2 workers. Increase only after observing memory/CPU use. In-memory retrieval caches are per worker.

## 7. Nginx reverse proxy

Create a site config with the real domain replacing `YOUR_DOMAIN`:

```nginx
server {
    listen 80;
    server_name YOUR_DOMAIN www.YOUR_DOMAIN;

    client_max_body_size 10m;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 180s;
        proxy_send_timeout 180s;
    }
}
```

Enable the site, test Nginx, and reload it. After DNS points to the VPS, install a Let's Encrypt certificate and force HTTPS.

## 8. Firewall and security

Public inbound access should normally be limited to:
- SSH
- HTTP 80
- HTTPS 443

Keep Uvicorn 8000 bound to `127.0.0.1`. Use SSH keys, disable unnecessary services, enable Hostinger backups/snapshots, and keep Ubuntu security updates current.

The application performs outbound HTTPS calls to Supabase, Groq, Fish Audio, NCERT, Balbharati and NIOS, so outbound HTTPS must work.

## 9. Runtime/storage considerations

The knowledge layer downloads official PDFs at runtime and extracts/indexes content in memory. This is more suitable to a persistent VPS than a serverless runtime, but it means:
- first access to uncached material may be slower;
- each Uvicorn worker has its own memory cache;
- external curriculum-source availability can affect retrieval;
- sufficient RAM should be monitored before increasing worker count.

The repo also contains optional download/ingestion scripts that write ignored files under `data/textbooks/` and `data/extracted/`. These are not required for the normal current runtime unless deliberately adopting a pre-indexed deployment.

## 10. Deployment/update procedure

Use a controlled update instead of editing production files manually:

```bash
cd /opt/chalo-padhaye
git fetch origin
git checkout main
git pull --ff-only origin main
source .venv/bin/activate
pip install -r requirements.txt
sudo systemctl restart chalo-padhaye
sudo systemctl status chalo-padhaye
curl http://127.0.0.1:8000/api/health
```

For safer releases, deploy/test a commit or release branch first and keep the previous known-good commit SHA for rollback.

Rollback:

```bash
cd /opt/chalo-padhaye
git checkout <KNOWN_GOOD_COMMIT>
sudo systemctl restart chalo-padhaye
```

## 11. Pre-cutover smoke test

Before moving production DNS, verify on Hostinger:

- `GET /api/health` returns healthy.
- Home UI loads over HTTPS.
- `/api/config` and `/api/catalog` load.
- Parent Supabase login works.
- Existing student profiles appear.
- Student login/invite flow works if migration 006 is installed.
- School/NIOS/CBSE curriculum selection works.
- Lesson catalog and structured lesson progress work.
- Tutor chat produces a Groq response.
- Wrong-answer/hint flow behaves correctly.
- Supabase session/progress writes succeed.
- Dashboard, roadmap, daily plan and portfolio load.
- Official textbook/PDF retrieval works from the VPS network.
- Browser microphone behavior is tested in a supported browser over HTTPS.
- Browser speech synthesis works.
- Fish Audio `/api/tts` works if configured.
- Logout/session refresh works.
- No provider secret appears in page source, browser JS, logs or Git.

## 12. Cutover plan

1. Leave Vercel production running.
2. Deploy Hostinger and test it using the VPS IP/temporary hostname or a staging subdomain.
3. Take a Hostinger snapshot/backup.
4. Confirm Supabase redirect/auth settings allow the final HTTPS domain where applicable.
5. Point the production DNS A record to the Hostinger VPS.
6. Confirm HTTPS certificate and redirects.
7. Run the smoke test again through the public domain.
8. Monitor Nginx and systemd logs plus CPU/RAM.
9. Keep Vercel available as rollback until Hostinger has been stable for an agreed observation period.
10. Only then remove the old Vercel deployment if desired.

Because Supabase remains the database, this is primarily an application-host migration rather than a user-data migration.

## 13. Known project risks / follow-up

- `INTERACTIVE_LEARNING_PLAN.md` says production migration 006 still needs to be run/confirmed.
- Chapter-test scoring is improved but not yet fully server-owned; the plan still lists server-stored questions/answers as future work.
- Exact-page retrieval is stronger for NCERT/CBSE than Balbharati/NIOS.
- Runtime PDF retrieval depends on third-party official education sites.
- Documentation still contains Vercel-specific wording and should be updated after Hostinger becomes the primary production host.
- No automated CI/CD or Hostinger deployment workflow is currently present in the repository.
- No dedicated production process/reverse-proxy files existed in `main` at audit time; this handoff documents the intended setup.

## 14. Handoff status

Repository audit completed against `main` at commit `6bac80c82e835a0c9ea1041277e1274dcb3c57b1`.

No production deployment, DNS, Supabase schema, or Vercel configuration was changed by this handoff.
