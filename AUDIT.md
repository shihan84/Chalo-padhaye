# Chalo Padhaye audit — homeschool readiness

## Audit scope

Reviewed the production tutor flow, parent authentication, Supabase persistence, textbook retrieval, custom TTS endpoint, homeschool curriculum handling, student progress model, and browser UI.

## High-priority issues found and addressed

### 1. TTS endpoint accepted any bearer-shaped token
Previously `/api/tts` only checked that an Authorization header started with `Bearer`. A random token could therefore reach the paid/free-quota TTS provider endpoint.

**Fix:** `/api/tts` now validates the token against Supabase Auth before generating audio.

### 2. Short child answers were retrieved without the previous tutor question
A reply such as `solid` or `yes` was used alone for textbook retrieval, even though its meaning depends on the tutor's previous question.

**Fix:** retrieval now combines the latest tutor turn with the child's new message, while the model also receives recent session history.

### 3. No actual answer assessment or mastery update
Messages were stored, but `student_progress` was not updated.

**Fix:** the tutor returns a structured assessment (`correct`, `partial`, `incorrect`, `not_answer`) and topic. Correct/partial/incorrect attempts now update topic mastery and attempts in `student_progress`.

### 4. No parent-facing learning dashboard
The database contained sessions but parents could not see useful learning information.

**Fix:** added `/api/dashboard` and a Parent Dashboard showing sessions, topics, mastered topics, average mastery, weak topics, and recent lessons.

### 5. No homeschool routine
The app only offered chat; it did not guide a child through a day of learning.

**Fix:** added `/api/daily-plan` and a Today screen. Plans differ by grade/curriculum and move weaker subjects earlier.

### 6. Homeschool NIOS support was incomplete
Only one NIOS Level A EVS PDF was configured.

**Fix:** the knowledge layer now exposes NIOS Level A / Level B EVS, Mathematics and Computer tracks, attempts to discover current official course-material links at runtime from an official NIOS resource page, and uses explicitly labelled supplemental material where an official source cannot be resolved. English/Reading and Life Skills are intentionally labelled supplemental.

### 7. Cross-subject fallback could return unrelated local material
When a remote subject index was missing, the old search code could fall back to all local documents without curriculum/subject isolation.

**Fix:** missing material now returns no context instead of silently searching unrelated local content.

### 8. Voice pace was hard-coded
The user could not tune the tutor's pace.

**Fix:** voice speed is adjustable and remembered per browser. Replay, mute, and English/Hindi microphone modes remain available.

## Homeschool product features now present

- School Study and Homeschool Learning modes.
- Grade 3 → NIOS Level A and Grade 5 → NIOS Level B mapping.
- Teach, Practice, Quick Quiz, Revision modes.
- One-question-at-a-time tutoring loop with hint-first behaviour.
- Recent-session conversational memory.
- Answer assessment and mastery tracking.
- Daily learning plan.
- Parent dashboard and weak-topic visibility.
- Official-vs-supplemental source labelling.
- NIOS EVS / Maths / Computer tracks plus supplemental English/Reading and Life Skills.
- Custom TTS with authenticated backend access and adjustable speed.
- Child data isolation through Supabase RLS.

## Remaining gaps before calling it a full homeschool management system

These are product additions rather than blockers for daily tutoring:

1. Persistent weekly planner and assignment completion.
2. Printable worksheets/tests and answer sheets.
3. Reading log and pronunciation scoring.
4. Project/portfolio evidence (photos/files/parent notes).
5. Attendance/time reports based on closed lesson sessions.
6. Parent-defined goals and target dates.
7. Chapter-level curriculum map rather than search-only navigation.
8. Automated regression tests for RLS ownership, subject isolation, NIOS source discovery, progress updates and TTS auth.
9. More reliable server-side caching/pre-indexing for large PDF sources on serverless deployments.
10. Re-cloned tutor voice with a cleaner accent and lower-latency streaming playback.

## Source integrity rule

Never claim a supplemental source is an official NIOS textbook. If an official NIOS material link cannot be resolved, the UI/API must label the fallback as supplemental or report the material unavailable.
