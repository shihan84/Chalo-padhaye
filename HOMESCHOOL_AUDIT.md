# Chalo Padhaye — Homeschool Audit

Audit date: 17 September 2026

This audit checks whether the project works as a practical family homeschool companion rather than only an AI chat interface.

## Overall status

The core family workflow is now usable:

1. Parent signs in.
2. Selects a child.
3. Chooses School Study or NIOS OBE Homeschool.
4. Uses a short one-question-at-a-time tutor.
5. Gets a focused Today plan.
6. Uses a balanced Monday–Friday Week plan.
7. Tracks goals, assignments, reading, projects, physical activity and life skills.
8. Reviews mastery, weak topics, study time and recent lessons.
9. Prints a weekly parent report.
10. Resumes the child's most recent lesson.

## Implemented

### Child learning
- Separate private profiles for each child.
- Maharashtra State Board school-study track.
- NIOS OBE Level A / Level B homeschool track.
- Official vs supplemental source labels.
- Teach, Practice, Quiz, Revision, Reading and Project modes.
- Recent conversation memory in each lesson.
- One small concept and exactly one question per turn.
- Hint-first behavior for wrong answers.
- Topic mastery tracking.
- Custom tutor voice with replay, mute and adjustable speed.
- English / Hindi microphone choice.
- Continue-last-lesson action.

### Homeschool planning
- Focused Today plan: academic blocks are limited by age to reduce overload.
- Monday–Friday Week plan rotating available subjects.
- Reading and movement blocks in homeschool mode.
- Parent goals with target dates.
- Goals are stored through the private homeschool portfolio rather than browser-only storage.
- Roadmap shows source type, mastery and recommended next action.

### Parent records
- Parent dashboard with sessions, topics, mastered topics and average mastery.
- Last-7-days study time, active days, streak and offline activity.
- Portfolio / assignment records for reading, projects, field trips, physical activity, art, life skills and other work.
- Planned items can be marked complete.
- Printable weekly report through the browser Print / Save as PDF flow.

### Privacy and security
- Supabase parent authentication.
- Row Level Security for child learning data.
- Voice endpoint requires a valid parent session.
- Provider API keys stay server-side.
- No Supabase service-role key is required.
- Refresh-token support is used for longer sessions.

## Source audit

NIOS currently publishes English-medium OBE learning-material links for A Level EVS, Basic Computer Skills and Maths, and B Level EVS, Maths and Computer Skill on its official OBE material page. The app attempts to discover these official links at runtime.

Where an official NIOS source is not available through that page, Chalo Padhaye must either show the subject as unavailable or clearly label the fallback as supplemental. A Balbharati supplement must never be presented as an official NIOS textbook.

NIOS OBE Level A is equivalent to Class 3, Level B to Class 5 and Level C to Class 8. Formal enrollment, examination and certification remain NIOS / accredited-agency processes and are not performed by this app.

## Database requirement

Run this migration once in Supabase SQL Editor if it has not already been run:

`supabase/003_homeschool_records.sql`

It enables the portfolio, assignments, goals and planned offline work. Core tutoring still works without it, but the homeschool planning workflow is incomplete.

## Remaining gaps before calling it a full homeschool operating system

### High priority
- Chapter-by-chapter curriculum map and chapter completion state.
- More deterministic mathematics answer checking instead of relying only on model assessment.
- Reading-specific fluency / spelling assessment.
- Parent-defined recurring weekly timetable instead of only the generated balanced week.
- Private evidence uploads for photos, worksheets and PDFs.

### Medium priority
- Monthly report and term report.
- Printable worksheets / tests with parent-only answer keys.
- Rubrics for projects and writing work.
- Reading log with book title, pages and minutes.
- Exportable portfolio index.
- Optional reminders for scheduled parent assignments.

### Later
- Offline/PWA mode.
- More languages and pronunciation practice.
- Formal NIOS registration / accredited-agency information links.
- Expansion to Level C when needed.

## Product rules

- Do not turn homeschool into all-day screen time. Daily plans should remain short and include reading, movement and hands-on work.
- Mastery percentages are tutoring heuristics, not official exam marks.
- Never compare siblings.
- Do not expose private child data in GitHub or public URLs.
- Do not silently substitute a non-NIOS source for an official NIOS source.
- Keep child-facing questions short and wait for the learner's answer.

## Smoke test after deployment

1. Parent login works and only the parent's children appear.
2. School / Homeschool source labels are correct.
3. Tutor asks one question and waits.
4. Wrong answer produces a hint before the answer.
5. Today plan shows only a manageable number of academic blocks.
6. Week tab renders five days and lesson Start buttons work.
7. Add a goal with a date; reload and confirm it persists.
8. Mark the goal complete and confirm it updates the portfolio.
9. Roadmap opens the recommended activity.
10. Parent dashboard updates study activity.
11. Continue Last Lesson opens the previous subject in revision mode.
12. Print Report opens a clean printable parent report.
13. Custom voice still works at the selected playback speed.
