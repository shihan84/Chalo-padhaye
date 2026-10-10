# Security and code audit — Chalo Padhaye

Audit date: 10 October 2026. Repository: `shihan84/Chalo-padhaye`.

## Outcome and scope

All three original branch snapshots were inspected. This is a source and local
regression audit, not a production penetration test or a claim of complete security.
The connected GitHub account `Saad123safa` has write permission, not admin permission.
No production database, DNS, hosting, provider credentials or original branch was
modified. No branch was merged.

| Original branch | Audited commit | Architecture and difference |
| --- | --- | --- |
| `main` | `6bac80c82e835a0c9ea1041277e1274dcb3c57b1` | FastAPI/static JS/Supabase/Groq; intended production application |
| `deployment/hostinger-handoff` | `633a24969e6f82e4341df9cc637cdb447e11db9a` | Same executable code as main; adds only `HOSTINGER_HANDOFF.md`, describing a Python VPS deployment |
| `hostinger-php` | `bdbb7cdbf7b6a45689b91fdfb8486fb0d4db8c69` | Adds PHP/MySQL and Telangana lesson definitions; retains the original Python application and Supabase frontend |

The shared Python, SQL, browser code, curriculum JSON, scripts, dependencies and
configuration were reviewed, together with all PHP/MySQL additions and deployment
documentation. Branch ancestry/diffs, repository permissions, open PRs and workflow
history were checked. Initially there were no open PRs and no workflow runs. All
three branch API records reported `protected: false`; rulesets and hosting settings
were not independently inspected.

Changes are proposed on `audit/security-code-2026-10-10` targeting `main`, and on
`audit/hostinger-php-security-2026-10-10` targeting `hostinger-php`. PHP files never
enter the main PR. The handoff branch needs the reviewed shared fixes carried
forward separately after acceptance; its code is not silently updated by a PR to
main. The inherited Python code on the PHP branch likewise remains the original
snapshot; it must not be used as a substitute Python deployment without the main
security fixes.

## Confirmed findings and proposed fixes

“Tested” means the Python/JS behavior was executed locally. SQL and PHP were initially
checked by grammar parsing, then exercised by isolated GitHub CI databases/runtime.
No SQL was applied to production. See the CI evidence and the remaining staging
gates below; a passing fixture suite is not a production security guarantee.

| ID | Severity | Branch / exact code location | Evidence and impact | Remediation |
| --- | --- | --- | --- | --- |
| A01 | High | Shared: `app/lesson_routes.py::lesson_override`, migration 006 | `_student` accepts a linked student under RLS; override did not compare the token's user to `parent_id`. A student can call the API to unlock/master their chapter. Local denial regression failed before the fix. | Main PR checks authenticated parent ownership before any override write; portfolio mutations also require the parent explicitly. Tested. Direct database mastery edits remain A20. |
| A02 | High | Shared: `supabase/004_lesson_mastery.sql`, `006_student_login.sql`; PHP MySQL schema | Steps/tests independently reference `student_id` and a progress ID. A row owned by one child can point to another child's known progress UUID; RLS checks only the row's student ID. | Matching `(lesson_progress_id, student_id)` foreign keys in main migration 007 and PHP schema/migration. PostgreSQL RLS and integrity tests pass in isolated CI; PHP schema loads in MySQL CI. |
| A03 | High | Shared: `supabase/006_student_login.sql::claim_student_invite` | Claim updates `student_user_id` unconditionally. A still-valid invite can replace an already linked account; profile ownership is not rechecked against invite creator. Original codes have only 32 bits and no app throttle. | Migration 007 locks the profile, refuses replacement/stale ownership and issues 128-bit codes. Browser accepts longer codes. SQL and sequential invite checks pass in isolated PostgreSQL CI; parallel claims still require tests. PHP already guarded linking; its creation path now refuses linked profiles and uses stronger codes. |
| A04 | High | Shared: `requirements.txt`, `app/main.py` static mount, PDF readers | Pinned PDF parser and resolved Starlette have known advisories. Starlette range-processing DoS applies to the app's static file serving. PDF parsing is used at runtime. | Main PR updates FastAPI 0.143.0, Starlette 1.3.1, pypdf 6.19.0, Requests 2.33.0 and python-multipart 0.0.31. Compatibility suite passes; rescan has zero known advisories. See dependency details below. |
| A05 | Medium | Shared: `lesson_routes.py::lesson_test`; PHP `/lessons/test` | Sending one correct answer with `question_count=1` yields 100%, bypassing the configured five-question test. Zero counts also fall back rather than fail. | Both PRs require exactly the configured question count and a correct count in range. Python tests cover 0/1/4/6 counts. This does not make client counts trustworthy; see A20. |
| A06 | Medium | Shared: `lesson_routes.py::lesson_step`; PHP `/lessons/step` | Reposting an already completed Learn event resets a test-ready chapter to Practice and 25%. | Completed/mastered step events return existing state; only the current unfinished step may advance. Python replay regression passes. PHP API integration CI passes; inspect the latest PR checks for added chapter-state cases. |
| A07 | Medium | Shared: `lesson_routes.py::lesson_test`; PHP `/lessons/test` | Failing a review of an already mastered chapter clears mastery and `mastered_at`. | Preserve prior mastery, best chapter score and mastery date while recording the failed attempt. Python regression passes; PHP API integration CI passes; inspect the latest PR checks for added chapter-state cases. |
| A08 | Medium | Shared: `combined_knowledge.py::CBSEKnowledgeBase.search` | If a requested page is missing, search falls through to the whole book. `re.search` picks an old marker before the child's newer page marker. This can teach from the wrong chapter/page. | Main PR returns no context for an unavailable explicit page and uses the latest marker. Both regressions pass. |
| A09 | Medium | Shared: `main.py::_daily_plan_for`; PHP `daily_plan_data` | Code emits every available subject although README promises limited blocks. Grade 3 NIOS gets five academic blocks plus reading/movement. | Limit academic blocks to two for grade <=3, three otherwise, retaining weak-topic priority and offline blocks. Python regression passes. PHP also respects unavailable source state. |
| A10 | Medium | Shared: `main.py::_week_metrics`; PHP `week_metrics` | Future completed portfolio dates count as study now. A seven-day rolling cutoff plus dates can cover eight calendar days. Naive timestamps can crash Python comparisons. | Count today and the previous six UTC calendar dates; exclude future records and normalize naive Python timestamps. Python checks pass. Local timezone conversion remains a product gap. |
| A11 | Medium | Shared: `frontend/lessons.js::endSession` / student change handler | After child selection changes, `endSession` reads the new child ID for the old session; backend rejects closure and study time remains missing. | Main PR remembers each session's child ID. Executed JS regression passes. |
| A12 | Medium | Shared: browser `sendText`, `resetChat`, `logout` | A reply started under one child can render after the selected context changes. Response handling uses mutable browser state. | Main PR uses a generation check before accepting late responses. JS test proves stale response is suppressed. Full browser race/E2E tests still needed. |
| A13 | Medium | Shared: `frontend/book.js::showBookForLesson`, `sourceForLesson` | An unsupported subject clears the iframe but leaves the previous URL/link/state. Static NIOS supplements can differ from the official source discovered by the backend. | Main PR clears stale state/link and uses the catalog's NIOS source URL/type. Browser function regressions pass. |
| A14 | Low | Shared: `frontend/interaction.js::renderInteraction`, chapter-test completion | A choice question following a number question leaves a numeric input. Finished tests keep showing the generated next interaction even though the chapter is closed. | Reset input type and hide completed-test interaction. Input regression passes. |
| A15 | Medium | Shared: `frontend/student-auth.js::loadStudents`, `login` | Session restoration defaults to Parent mode and rejects a valid saved student session. A failed login clears access token but leaves refresh token. | Infer Student mode from the authenticated linkage; clear both tokens on failed login. Syntax checked; full real-Supabase-auth restoration remains an E2E test gap. |
| A16 | Medium | Shared: `main.py` input/upstream handling, `tutor.py::reply`; PHP `request_json` | Unbounded JSON/messages permit excessive resource consumption. Python upstream exceptions produce 500s, and fallback errors expose upstream URLs/internal exception details. | Main caps bodies at 64 KiB including chunked bodies, bounds chat/TTS fields, uses a generic 502 and provider error; private API responses are no-store. Tests pass. PHP caps body/message/profile fields; runtime pending. |
| A17 | Medium | Shared: `main.py::chat`, `end_session`; PHP `/chat`, `/end-session` | Python resumes ended sessions; repeated close rewrites `ended_at`. PHP verifies only student/session, permitting curriculum/subject changes; new subjects lack curriculum prefix. | Reject ended sessions; close only open sessions; PHP stores/validates `curriculum:subject`. Python checks pass. Existing PHP legacy sessions must be ended and replaced, not guessed/relabelled. |
| A18 | High | PHP: `php/tutor.php::groq_tutor`, `learning.php::curriculum_catalog` | No textbook context/retrieval or interaction contract is supplied. Catalog reports all lesson-map entries available. Tutor claims a selected curriculum without sources and can invent content. | PHP PR fails closed with a material-unavailable, non-assessment response and marks source availability false. Actual textbook retrieval is still missing. This intentionally blocks production tutoring until a grounded provider is implemented. |
| A19 | Medium | PHP: `auth.php::register_parent`, student register, `bootstrap.php::db`, `/health` | Default bcrypt silently truncates >72-byte passwords; DB connection does not set UTC though auth/learning SQL uses UTC; health reports `ok:true` when DB fails. | Reject >72-byte passwords, set DB UTC and return 503/unhealthy for database failure. PHP lint/helper and MySQL HTTP integration checks pass in isolated CI. |

## Confirmed risks not fully remediated by these PRs

| ID | Severity | Evidence | Required follow-up |
| --- | --- | --- | --- |
| A20 | High: learning integrity | Both test endpoints trust `correct_count`; step endpoints trust `assessment`. Supabase migration 006 grants linked students ALL operations on progress, steps, tests and tutor messages. A student can fabricate mastery directly through REST even after A01/A05. | Server-owned questions, answers and consumed assessment events, with atomic scoring and student read-only result tables. Use narrowly authorized RPC/server persistence; do not expose a service-role key. Counts are heuristics, not trustworthy exam marks. |
| A21 | High: abuse/cost | No app-level distributed throttling or provider budgets. Any valid Supabase user can access Python TTS; PHP public parent registration immediately grants a session and paid TTS access. No family/allowlist gate for TTS. Invite/login attempts lack custom rate limits. | Persist per-account/IP quotas, family enrollment checks and provider spending limits. Infrastructure/provider throttling may exist but was not inspected. A03's entropy increase is not a substitute for throttling. |
| A22 | Medium: concurrency/data loss | Read-modify-write progress/test counters and multi-request step creation have no transactional server operation. Concurrent tabs can lose increments, duplicate attempts or trigger uniqueness failures. Requests can partially save a message/step then fail. | Transactional, idempotent RPC/database operations with event IDs, unique attempt numbering and parallel-request tests. Replay guards address sequential replay only. |
| A23 | Medium: retrieval availability | PDF downloads buffer complete responses, parsing/indexing occurs synchronously on a cold request, CBSE loops up to 14 chapters at 20 seconds each, caches are per worker and cache failed discovery indefinitely. Redirect destinations are not constrained. | Bound download/parse resource use, validate redirects, isolate parsing, pre-index/cache by book version, retry transient failures with TTL and test cold starts. NIOS discovered initial links are now restricted to HTTPS official NIOS PDF hosts; redirect safety remains open. |
| A24 | Medium: silent incomplete data | Dashboard/progress/session/portfolio queries cap at 100/60/80 rows. Reported totals and weak-topic selection can be incomplete; many upstream errors are converted to empty lists or migration-unavailable indicators. | Paginated totals/aggregates, explicit degraded-storage states, observability and fixture tests exceeding limits. |
| A25 | Medium: operational controls | Original branches report unprotected; no original CI runs. PHP repo-root `.env` could be served by an incorrectly chosen document root. Neither deployment has a tested backup/restore, security-header/CSP policy or incident process in code. | Enable branch rules/review/required checks with an administrator, stage deployments, use a private env path and deliberate public document root, verify HTTPS/backup restoration. No admin settings were changed. |
| A26 | Medium: browser/tutor boundary | Structured chapter is a user-message marker, not a server-validated lesson field; whole-subject retrieval is common. LLM-only assessment/hint/one-question rules are prompts, not deterministic enforcement. Tokens are browser script-accessible. | Bind selected lesson/page and question ID server-side, deterministic answer checking where appropriate, prompt-injection/adversarial tests and a CSP compatible with the current dynamic styles. No exploitable stored XSS was found in the inspected escaped rendering paths. |

## PHP deployment and compatibility blockers

These are verified implementation gaps, not speculation about live Hostinger state:

- `/api/config` has no Supabase fields, while every frontend auth/profile/invite
  operation still calls Supabase. MySQL registration endpoints are not connected to
  the UI. The branch does not deliver its advertised end-to-end architecture.
- PHP portfolio mutation responses wrap `{record:...}`; Python/browser contracts
  expect the record directly. PHP errors use `error`; the browser generally reads
  `detail`. Curriculum availability is only a lesson-map listing until retrieval is
  ported; NIOS Grade 5 is empty in the PHP map.
- No PHP `/lessons/override` implementation. Parent revoke/relink controls are absent
  in both frontends. PHP session refresh/password reset/email verification and
  parent-controlled enrollment are absent.
- Existing MySQL `CREATE TABLE IF NOT EXISTS` does not migrate old enum definitions;
  previously created databases may still reject Telangana. A versioned upgrade is
  needed for those deployments; final schema text alone does not prove live state.
- No Hostinger public-root rewrite/routing/deployment workflow or tested cutover
  exists. The VPS handoff describes Python hosting; it is not a PHP installation guide.
- PHP TTS places `model` in JSON whereas the existing Python integration uses a
  `model` header; validate against the provider contract on staging before claiming
  custom-voice parity. No real provider call was made during this audit.
- Original PHP files are compressed into long lines, limiting reviewability. The
  bootstrap contained literal NUL/control bytes; the PHP PR converts them to text
  escapes and enables text diffs. This was a reviewability issue, not a proven parse bug.

## Missing product features

Verified from the lesson JSON, endpoints and the existing project plans:

1. Complete chapter maps for NIOS Level B and most NIOS subjects; CBSE Grades 2–8
   lack catalog sources/maps despite student profiles accepting Grades 1–12.
2. Server-owned test sessions and trustworthy results (A20); deterministic maths
   checks; persistent question bank with fresh questions on retry.
3. Exact-page retrieval for full Balbharati/NIOS books; reading/pronunciation
   assessment, page images and diagram/match/drag interactions.
4. A persistent recurring weekly timetable. The current Week screen generates a
   rotation; it does not save a parent-edited schedule.
5. Private evidence uploads, worksheet/test generation with parent-only answers,
   reading logs, exportable portfolios, monthly/term reports and reminders.
6. Trusted-device PIN, parent revoke/relink UI, spaced revision and durable learner
   preferences. No tested offline/PWA flow.

These are not all implemented by a security fix PR; they need separate acceptance
criteria. Older audit documents call some of these complete or list stale plans.
This report describes the inspected current code and is the audit baseline.

## Dependency evidence

`pip-audit -r requirements.txt --format json` against the original pins/resolution
reported 109 records across four packages, including duplicate advisory entries.
There were 61 distinct reported IDs; these are not 61 demonstrated exploitable
application paths. Requests' zipped-path utility and several parser/form advisories
are not used by the application; the static-file and PDF-reader exposure is relevant.

| Package | Original resolution | Proposed main resolution |
| --- | --- | --- |
| FastAPI | 0.116.1 | 0.143.0 (allows patched Starlette) |
| Starlette | 0.47.3 | 1.3.1, explicitly pinned |
| pypdf | 6.0.0 | 6.19.0 |
| Requests | 2.32.5 | 2.33.0 |
| python-multipart | 0.0.20 | 0.0.31 |

Primary advisory examples: [Starlette static-file DoS](https://github.com/Kludex/starlette/security/advisories/GHSA-7f5h-v6xp-fcq8),
[pypdf parser resource exhaustion](https://github.com/py-pdf/pypdf/security/advisories/GHSA-jfx9-29x2-rv3j),
[FastAPI release](https://github.com/fastapi/fastapi/releases/tag/0.143.0).
The updated requirements rescan reports zero known vulnerabilities at audit time.
Transitive versions are resolved rather than fully locked; future resolution/advisory
changes must be checked by CI. The PHP branch's retained Python pins are unchanged
and carry the same original advisories if that Python application is run.

## Validation actually performed

- 13 targeted regression cases failed on the original main code before edits;
  all 13 subsequently pass.
- Final main suite: **44 Python cases passed** and **5 Node function-level browser
  regressions passed**. No provider account or live child data is needed.
- Python compilation, every browser script's Node syntax check, bootstrap shell
  syntax, `pip check`, YAML parsing and Git whitespace checks pass.
- All seven main PostgreSQL migration files parse. PHP files, including added test
  runners, parse with the PHP tree-sitter grammar. CI additionally executes PHP lint,
  helper regressions, and MySQL API tests. Parsing alone is not execution.
- 119 historical Git blobs were inspected for high-confidence GitHub/Groq/JWT/
  private-key patterns: no matching candidates. This limited pattern scan does not
  prove absence of every possible secret or private datum.
- No production auth, TTS, Groq, official source download, deployment or migration
  was executed. No hosted app URL was supplied for an authorized production test.
- Local PHP/PostgreSQL/MySQL runtime validation is **blocked**: automatic approval
  review rejected installation of server/runtime packages as a broad system mutation
  that could start services. No workaround installation was attempted afterward.
- All four initial CI jobs passed: Python/JS/dependency checks and PostgreSQL RLS/
  invites in [main audit run](https://github.com/shihan84/Chalo-padhaye/actions/runs/38071789110);
  PHP lint/helper and MySQL ownership/session HTTP checks in
  [PHP audit run](https://github.com/shihan84/Chalo-padhaye/actions/runs/38071798240).
  Further PHP chapter-state/integrity cases are included in the follow-up commit.
  Always inspect the current PR head checks before merge. Starlette/httpx emit
  test-client deprecation warnings; the Python tests pass.

## Required test gaps and release gates

Before accepting the SQL/PHP changes, run the isolated CI jobs and review results.
Add live staging tests for parent A/B/student/anonymous ownership, stale/replayed and
parallel invites, counters under concurrency, upgrades from older schemas, RLS
policy reruns, failure/timeout recovery and datasets exceeding pagination limits.
Run a real browser/iPhone flow for student session restoration, token refresh,
logout, rapid child/subject changes, numeric/choice input, chapter test retry,
source labels, speech/microphone permissions and print reports. Test prompt injection,
wrong-answer hints and maths correctness against a fixed evaluation set. Run actual
provider tests only with staging accounts/budgets and no child PII.

Main migration 007 is transactional/repeatable, protects new writes with NOT VALID
composite foreign keys, and leaves old inconsistent records untouched. Inspect/repair
legacy mismatches before validating those keys. Apply after all earlier migrations.
The PHP ALTER migration is a one-time migration for existing databases; fresh schemas
already include the constraints. MySQL DDL may partially apply if legacy data blocks
later constraints; stage it and inspect existing keys before retrying. Neither PR
applies migrations or changes production automatically.

Priority order: review tested main fixes; execute DB/PHP CI; stage migrations;
implement A20/A21 before treating results/cost boundaries as secure; complete PHP
frontend/retrieval/routing and migration parity before any cutover. Do not merge the
PHP deployment branch into main.
