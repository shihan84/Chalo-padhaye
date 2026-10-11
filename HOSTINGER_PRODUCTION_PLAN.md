# Chalo Padhaye production plan

Approved scope: Hostinger only. Keep main and Vercel unchanged; separate feature branches and reviewable PRs. Deploy exact commits only after PHP, MySQL/API and mobile tests pass. No textbook PDFs, credentials or child recordings in Git.

## Release 1 — a reliable mathematics teaching loop

- Maharashtra Classes 3 and 5; 14 and 16 chapters from the reviewed, hash-pinned publisher books.
- Independently authored **selected skill lessons**, with explicit skills and textbook chapter/page references. These are not official textbook questions or proof that every exercise/exam objective is covered.
- One question at a time. Learn (3 guided questions), practise (6), checkpoint (5). Practice requires at least four independent answers before a checkpoint. A checkpoint needs four of five independent answers to pass.
- First wrong answer: hint and the same question. Second unsuccessful answer: answer feedback, then another step. Assisted answers never count towards independent checkpoint scores.
- Failure routes to revision prioritising weak skills, then fresh practice. Passing unlocks the next chapter. Explicit weak-skill revision retains earlier passes.
- Save question, attempts, stage, skill evidence and bounded checkpoint history privately outside public_html. Reload resumes; request identifiers prevent duplicate grading; every API call checks family ownership and grade.
- Parent sees separate skill-checkpoint counts and weak chapters, never an inflated claim of full syllabus mastery. Daily plan links to the saved course.
- Small diagrams, tables, pictographs and fraction bars. Optional browser read-aloud; no model/provider call required to teach or grade.

Acceptance: exact answer keys independently checked; no answer keys in unresolved API questions; forged scores, wrong grades, skipped questions, replayed requests, family/child crossover and assisted-test mastery rejected; both grades complete a chapter on mobile and resume after reload. Existing foundation practice and all four publisher libraries still pass.

## Release 2 — deepen the verified teaching coverage

Audit every chapter objective and problem type, not just the contents title. Add multi-step worked explanations, misconceptions, application/story problems, equivalent/different-denominator fractions, more complex conversions, maps, construction/net tasks and learner explanations. Review diagrams and ambiguity with a teacher. Schedule delayed retention checks with due dates; separate practice evidence from checkpoint/retention results. Offer parent-reviewed chapter selection when school teaching order differs. Expand other boards only after edition, child-track suitability and complete sources are verified. Telangana source access remains a blocker, not a completed import.

Acceptance: an objective-to-activity matrix for each chapter, reviewer sign-off, alternative-answer and misconception tests, first-error hints without answer leaks, and retention reporting that uses actual delayed answers.

## Release 3 — real-time tutoring and voice

Measure real provider latency and reliability using limited synthetic prompts. Keep one concept/one question, cite the correct chapter, and evaluate the child's last question rather than generic recent text. Add bounded child explanation/context and streaming only where it preserves grading/source checks. Test microphone permissions, speech ambiguity, provider failures, cancellation, stale child responses and voice/text equivalence on supported phones. Keep deterministic grading for scored exercises. Add explicit provider cost/time limits and a usable text fallback.

Acceptance: measured response-time targets, failure/cancellation tests, bounded costs and teacher-reviewed synthetic interactions. A configured API key or health endpoint is not proof that paid voice/AI works correctly.

## Release 4 — operational production gate

Verify automated database **and private runtime progress** backups, protected retention and restoration into a disposable environment. Existing deployment file rollback is not a database/progress backup. Add redacted error/latency monitoring, provider availability, source freshness and storage capacity alerts. Test simultaneous learners and concurrent duplicate answers, account/token expiry, deployment rollback and disaster recovery. Document ownership, support procedures and ongoing textbook/edition reviews.

Acceptance: recorded restoration drill, load/concurrency evidence, recovery checklist, monitoring evidence and a small family pilot with measured learning/usability outcomes. Broad production readiness remains pending until these gates pass.
