# Hostinger PHP implementation status

This file applies only to the permanent `hostinger-php` branch. **Do not merge this branch into `main`.**

## Phase 1 implemented

- PHP bootstrap with safe environment loading.
- PDO MySQL connection using prepared-statement-safe defaults.
- PHP API entry point.
- `GET /api/health` and `GET /api/config` foundation.
- MySQL schema for users, students, progress, tutor sessions/messages, homeschool records, lesson mastery, tests, student invites and server-side auth sessions.
- Environment variable template without credentials.

## Important

The existing frontend still uses Supabase directly for authentication/profile/invite operations. It has intentionally not been switched yet. Deploying Phase 1 alone is for backend/database validation, not production cutover.

## Next phases

1. Implement PHP authentication: parent/student registration, login, logout, session validation and invite claiming.
2. Implement student/profile APIs with ownership checks replacing Supabase RLS.
3. Port portfolio/dashboard/roadmap/daily-plan APIs.
4. Port lesson mastery endpoints.
5. Port tutor chat, Groq integration, TTS and curriculum retrieval.
6. Change the frontend from direct Supabase calls to the PHP API.
7. Add Hostinger web-root routing and GitHub Actions SSH deployment after the target document root/path is confirmed.
8. Perform compatibility and migration testing before Hostinger cutover.

## Security model

Supabase RLS is replaced by server-side ownership checks. Every student-scoped API must verify that the authenticated user is either the student's parent or that exact linked student before reading or changing learning data.
