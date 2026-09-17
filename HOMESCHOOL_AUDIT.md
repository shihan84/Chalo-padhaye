# Chalo Padhaye — Homeschool Audit

This audit tracks whether the app is useful as a day-to-day family homeschool companion, not just an AI chat demo.

## Implemented and usable

### Child learning
- Parent-authenticated child profiles.
- Separate progress and lesson history per child.
- Maharashtra State Board school-study mode.
- NIOS OBE homeschool mode with official-vs-supplemental source labels.
- Teach, Practice, Quick Quiz, Revision, Reading, and Project modes.
- One-concept / one-question tutoring loop.
- Recent lesson conversation memory, so short answers are understood in context.
- Hint-first behavior for wrong answers.
- Topic-level mastery tracking.
- Custom tutor voice, replay, mute, adjustable speed, and English/Hindi microphone modes.

### Homeschool planning
- Today's learning plan per child.
- Weak-topic prioritization.
- Parent-planned activities can appear in Today's plan.
- Independent reading and movement/offline activity suggestions in homeschool mode.
- Source-aware Roadmap with official, supplemental, unavailable, mastery, and recommended-next-action states.

### Parent oversight
- Parent dashboard.
- Sessions, topics, mastered topics, and average mastery.
- Weak-topic list and recent lesson history.
- Last-7-days study minutes, tutor sessions, active days, streak, offline minutes, and portfolio count.
- Homeschool portfolio / assignments for reading, projects, field trips, physical activity, art, life skills, and other work.
- Planned portfolio work can be marked completed and included in weekly activity totals.

### Privacy / security
- Supabase parent authentication.
- Row Level Security for child learning records.
- No service-role key required in the browser.
- Provider API keys stay server-side.
- Tutor voice endpoint requires a valid parent session.
- Supabase refresh-token support keeps longer study sessions signed in.

## Required database step

Run this once in Supabase SQL Editor:

`supabase/003_homeschool_records.sql`

The rest of the app continues to work if the migration has not been run, but Portfolio / Assignments will show an installation notice and cannot save records.

## Important current limitations

1. NIOS source availability is source-aware but still depends on official links being reachable. When an official source cannot be resolved, the app must either show it as unavailable or use a visibly labelled supplement.
2. Topic mastery is a tutoring heuristic, not a formal examination result.
3. Browser SpeechRecognition availability and quality vary by device/browser.
4. The current portfolio stores text records only; photo/PDF evidence upload is not implemented yet.
5. Printable worksheets/tests and parent-defined weekly calendars are not implemented yet.
6. Chapter-by-chapter curriculum sequencing is not yet fully pre-indexed; the current roadmap is subject/topic mastery based.
7. This app is a family learning tool. Any formal enrollment, examination, certification, or attendance requirements must be handled through the relevant school/open-school process separately.

## Recommended next development phases

### Phase A — curriculum depth
- Pre-index exact NIOS Level A and Level B chapter metadata.
- Add chapter picker and chapter completion status.
- Add official source health checks.
- Add spelling/reading-specific scoring.

### Phase B — parent planning
- Weekly calendar with drag/drop or day assignment.
- Parent goals and target dates.
- Printable weekly report.
- Planned assignments with optional rubric/checklist.

### Phase C — portfolio evidence
- Private image/PDF evidence upload through Supabase Storage.
- Link evidence to projects, field trips, art, worksheets, and reading logs.
- Export portfolio summary for the parent.

### Phase D — assessment
- Parent-selected tests.
- Printable worksheets with answer keys stored separately from the child view.
- More deterministic math answer checking.
- Monthly progress summary based on multiple attempts, not a single response.

## Smoke test after deployment

1. Login as parent.
2. Select each child and confirm only that parent's profiles appear.
3. Switch School Study / Homeschool Learning and confirm source labels are correct.
4. Start Teach mode, answer the tutor's question, intentionally answer one question incorrectly, and verify hint-first behavior.
5. Open Today and start one recommended lesson.
6. Open Roadmap and confirm mastery + recommended action display.
7. Open Parent Dashboard and confirm recent lesson + weekly stats.
8. Add one completed Reading record and one Planned Assignment record.
9. Confirm the planned assignment appears in Today when due.
10. Mark it complete and confirm weekly/portfolio totals update.
11. Let the app remain open long enough for a token refresh and confirm the parent stays signed in.
