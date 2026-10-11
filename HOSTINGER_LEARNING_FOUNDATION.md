# Hostinger learning foundation

Scope: Hostinger PHP only. Separate feature PR; no main/Vercel edits and no branch merges.

## Delivered in this release

- Login limited to 30 attempts per IP per 15 minutes and 60 per account per hour. Parent and student registration each limited to 30 per IP per hour. Forwarded headers are not trusted for identity.
- Paid voice requires access to a child profile. A family shares 60 calls and 36,000 text bytes per day, with three calls per minute. Oversized text is rejected, not truncated. Connection/request timeouts bound provider waits. Browser voice is the fallback.
- Original child identity is retained when ending a tutor session. Stale tutor replies cannot appear after changing the child or lesson. Expired sessions return the user to login. Weekly planner errors are displayed.
- Browser-supplied assessment and correct-count writes are disabled. They cannot grant board mastery.
- Independently authored Class 3 addition/subtraction practice: worked examples, visual tens/ones, ten generated questions, first-error hint, second-error explanation, explicit Next, server grading, saved resume and parent progress.
- New runs exclude the last 100 expressions. Identical retried answer/Next requests are idempotent; changed payloads cannot reuse a request ID. Runs expire after 24 hours. Mastery requires eight of ten independently correct answers. Hint-assisted answers do not count as independent mastery.
- Today's Plan includes a labelled 15-minute foundation block. Students cannot edit parent goals.

## Storage and operations

No production database DDL is required. Practice and quota state is kept in `chalo-private/runtime` outside the web root, with hashed filenames, private permissions, process locks and atomic replacement. Release archives exclude runtime state; deployment and rollback do not replace it. Include the private runtime directory in hosting backups along with MySQL. This implementation targets the existing single Hostinger server, not a distributed cluster.

Only the latest 20 completed practice runs and 100 question signatures are kept for each child. Counters reset on access after their windows expire. Recovery email, scheduled backup/retention jobs and multi-server state storage remain future work; they are not advertised as delivered.

## Verification

Local Node regressions cover session child binding, delayed replies, planner errors, syllabus rendering and account setup. Package/deploy checks verify private/public separation, pinned SSH identity and safe environment handling. GitHub Actions additionally runs PHP lint, helper properties, MySQL/API integration and a mobile Chromium journey with synthetic disposable accounts. Browser evidence includes mobile practice and a parent print view. Production checks are read-only; no family accounts are inspected.

Actual Fish Audio output and microphone recognition require testing on a supported device and a signed-in family account. CI verifies the browser fallback and provider authorization boundary; it does not spend production provider credits.

## Recommended next releases

1. Class 5 fractions diagnostic and practice, using the same server-owned assessment contract.
2. English reading/comprehension and EVS visual activities with fresh retries.
3. Parent profile editing, active-session management and account recovery with a configured mail service.
4. Adaptive revision and daily plans based on demonstrated skill gaps.
5. Official Telangana/CBSE/NIOS tutoring only after current source editions are verified and textbook retrieval is implemented. Syllabus metadata does not establish lesson readiness.
