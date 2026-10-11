# Hostinger functional validation — 11 October 2026

## Result

The deployed foundation is responding correctly, but the application is not feature-complete or fully validated for live teaching.

Production release checked: `e6805930a9fed92432544d85d8ccec810ca176b5` at https://chalopadhaye.com/. No production accounts, child records, goals, lesson attempts or provider charges were created for this validation. Main, hostinger-php and Vercel remain unchanged.

## Verified checks

- 47 production HTTP checks passed. Raw non-sensitive results are in `validation/hostinger-live-2026-10-11.json`: health/database/schema, PHP/MySQL configuration, four-board catalog, Telangana classes 1–10, invalid class/board rejection, authentication requirements for family and lesson endpoints, invalid registration/login requests, private-file protection, unknown routes and nine frontend asset hashes matching the deployed commit.
- Two original expectations in the smoke harness were corrected after source review: empty `/lessons/start` returns 400 for invalid curriculum and empty `/chat` returns 422 for invalid message before authentication. These are validation responses, not exposed student data. Properly formed unauthenticated requests were separately checked and return 401.
- Seven Node browser contract/regression tests, one archive test and six deployment helper tests passed on rerun.
- PHP lint/helper and isolated MySQL integration jobs passed on a fresh rerun of https://github.com/shihan84/Chalo-padhaye/actions/runs/38118145412 . Existing tests cover registration, child creation, student invite linking, family access isolation, session/curriculum isolation, lesson progression/review, relational child isolation, payload limits and logout.
- Browser interaction verified parent/student tab switching and first-time student setup controls. No application JavaScript error appeared during these public-page interactions; browser-extension errors were unrelated to the application.
- Additional CI tests added in this branch cover correct/incorrect parent login, duplicate registration, profile listing/editing, portfolio creation/read/completion, planned activities in the daily plan, dashboard minutes, all four roadmaps/plans, family isolation and expired sessions. Their result is recorded in this PR's CI checks.

## Confirmed failures and gaps

1. **Teaching, practice, quizzes and academic daily plans are unavailable.** Every catalog subject is unavailable and `groq_tutor` returns `textbook_retrieval_unavailable`. The service intentionally refuses to invent textbook-grounded lessons.
2. **The weekly planner hides API failures.** `loadWeek` does not check `rr.ok`/`pr.ok`. A Node VM reproduction with both APIs returning 401 and `Session expired` renders `No active goals.` and an empty academic week instead of the error. This can mislead a parent into thinking their saved goals are absent.
3. **Fish Audio is configured, but custom voice is not connected to the frontend.** The frontend calls browser `speechSynthesis`; no frontend request calls `/api/tts`. Configuration readiness does not prove the provider works. No paid-provider request or microphone/browser playback was tested.
4. **The syllabus is partial and edition-specific.** Class 10 has reference outlines for Maths, English and Social Studies. Other outlines and the current exam year's full syllabus still need verification. No book-link availability guarantee is possible after the SCERT fetch timeouts documented in `TELANGANA_SYLLABUS.md`.
5. **Mastery scores are not authoritative.** The lesson step/test APIs still accept client-supplied assessments and correct/question counts. Existing integration fixtures demonstrate that a client can advance and pass a chapter by posting counts without server-verified answers. Teaching must remain unavailable until server-side grading is implemented.
6. Password recovery is not implemented in the current frontend/API. Live authenticated browser journeys, invite delivery on a child's device, actual voice playback, microphone input, mobile layout, print appearance and current textbook downloads have not been end-to-end verified.

## Scope of confidence

Authenticated backend tests use a disposable MySQL fixture. They do not prove a complete signed-in production browser journey. Live public UI and HTTP checks confirm availability, configuration and access rejection, not the complete learning experience. This is functional validation, not a load test or a claim that all security findings from the earlier audit are resolved.
