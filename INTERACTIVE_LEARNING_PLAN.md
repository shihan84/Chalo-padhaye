# Chalo Padhaye — Interactive Learning + Student Login Plan

Updated: 2026-09-18

## Goal
Turn Chalo Padhaye from a chat-first tutor into a guided digital textbook + tutor experience, while giving each child a private student login that exposes only that child's learning area.

## Phase 1 — Reliable tutor output
- [x] Enforce structured JSON responses when supported by the LLM provider.
- [x] Retry incomplete/truncated tutor responses automatically.
- [x] Prevent raw partial JSON such as `{"reply":"...` from being shown to students.
- [x] Return an explicit interaction object with every tutor turn.

## Phase 2 — Interactive lesson controls
- [x] Add interaction types: choice, true/false, short answer, number, voice, none.
- [x] Add large tap-to-answer buttons for choice questions.
- [x] Add quick learner controls: Explain simpler, Show example, I understand, I need help.
- [x] Prefer tap/voice/number interactions for Class 1.
- [x] Use short-answer/number/next-step reasoning more often for Classes 9–10.
- [ ] Add image-based tap targets and diagram questions.
- [ ] Add drag/order/match interactions where useful.

## Phase 3 — Digital textbook interaction
- [x] Display mapped official NCERT/Balbharati/NIOS PDFs in the learning screen.
- [x] Add previous/next page controls and official PDF fallback.
- [x] Retrieve exact selected NCERT chapter PDF page text on the backend.
- [x] Bind CBSE tutor retrieval to current chapter + current PDF page when the textbook-page action is used.
- [ ] Add the same exact-page retrieval path for Balbharati and NIOS full-book PDFs.
- [ ] Render page image snapshots when browser PDF embedding is unreliable.
- [ ] Add Read with me mode for primary students.
- [ ] Add diagram/image-aware questions and Look here guidance.

## Phase 4 — Student login
- [x] Add `student_user_id` linkage to student profiles.
- [x] Add parent-generated, short-lived invite codes.
- [x] Student account uses normal Supabase Auth — no service-role key.
- [x] Add Student Login tab and first-time student setup UI.
- [x] Limit student RLS access to the linked child's learning records.
- [x] Hide Parent Dashboard from student accounts.
- [x] Students can read parent-created assignments; parent remains owner of portfolio editing.
- [ ] Run `supabase/006_student_login.sql` on production Supabase.
- [ ] Add optional trusted-device PIN unlock after the main Supabase login.
- [ ] Add parent revoke/relink control.

## Phase 5 — Server-owned chapter tests
- [x] Server recalculates score from recorded correct/question counts rather than trusting a browser percentage.
- [ ] Generate and store each test question server-side.
- [ ] Store question IDs, submitted answers and correctness server-side.
- [ ] Prevent the browser from submitting its own `correct_count`.
- [ ] Unlock the next chapter only from the server-owned final result.

## Phase 6 — Learner adaptation
- [x] Tutor observes only conversation evidence such as confidence/hesitation/struggle.
- [x] Adaptive browser speech speed and delivery.
- [ ] Persist recent learning preferences and support needs per student.
- [ ] Add spaced revision scheduling from weak topics and chapter mastery.
- [ ] Add parent-visible lesson summaries without sibling comparison.

## Required database action
Run `supabase/006_student_login.sql` after `005_cbse_support.sql` to enable separate student accounts and the student-specific RLS policies.
