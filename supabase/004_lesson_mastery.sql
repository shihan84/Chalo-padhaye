-- Structured lesson/chapter progress for Chalo Padhaye.
-- Run once in Supabase SQL Editor after 003_homeschool_records.sql.

create table if not exists lesson_progress (
  id uuid primary key default gen_random_uuid(),
  student_id uuid not null references students(id) on delete cascade,
  curriculum text not null check (curriculum in ('maharashtra','nios')),
  subject text not null,
  lesson_id text not null,
  lesson_order integer not null default 1,
  lesson_title text not null,
  status text not null default 'locked' check (status in ('locked','available','in_progress','test_ready','mastered','parent_unlocked')),
  current_step text,
  percent_complete integer not null default 0 check (percent_complete between 0 and 100),
  test_score integer check (test_score between 0 and 100),
  test_attempts integer not null default 0,
  started_at timestamptz,
  mastered_at timestamptz,
  updated_at timestamptz not null default now(),
  unique(student_id, curriculum, subject, lesson_id)
);

create table if not exists lesson_step_records (
  id uuid primary key default gen_random_uuid(),
  lesson_progress_id uuid not null references lesson_progress(id) on delete cascade,
  student_id uuid not null references students(id) on delete cascade,
  step_id text not null,
  step_order integer not null,
  step_title text not null,
  status text not null default 'locked' check (status in ('locked','available','in_progress','completed')),
  attempts integer not null default 0,
  correct_count integer not null default 0,
  score integer check (score between 0 and 100),
  started_at timestamptz,
  completed_at timestamptz,
  updated_at timestamptz not null default now(),
  unique(lesson_progress_id, step_id)
);

create table if not exists lesson_test_attempts (
  id uuid primary key default gen_random_uuid(),
  student_id uuid not null references students(id) on delete cascade,
  lesson_progress_id uuid not null references lesson_progress(id) on delete cascade,
  attempt_no integer not null,
  score integer not null check (score between 0 and 100),
  passed boolean not null default false,
  correct_count integer not null default 0,
  question_count integer not null default 0,
  summary jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now()
);

create index if not exists lesson_progress_student_idx on lesson_progress(student_id, curriculum, subject, lesson_order);
create index if not exists lesson_step_records_student_idx on lesson_step_records(student_id, lesson_progress_id, step_order);
create index if not exists lesson_test_attempts_student_idx on lesson_test_attempts(student_id, lesson_progress_id, created_at desc);

alter table lesson_progress enable row level security;
alter table lesson_step_records enable row level security;
alter table lesson_test_attempts enable row level security;

drop policy if exists "parents manage own lesson progress" on lesson_progress;
create policy "parents manage own lesson progress"
on lesson_progress for all to authenticated
using (
  exists (select 1 from students s where s.id = lesson_progress.student_id and s.parent_id = auth.uid())
)
with check (
  exists (select 1 from students s where s.id = lesson_progress.student_id and s.parent_id = auth.uid())
);

drop policy if exists "parents manage own lesson steps" on lesson_step_records;
create policy "parents manage own lesson steps"
on lesson_step_records for all to authenticated
using (
  exists (select 1 from students s where s.id = lesson_step_records.student_id and s.parent_id = auth.uid())
)
with check (
  exists (select 1 from students s where s.id = lesson_step_records.student_id and s.parent_id = auth.uid())
);

drop policy if exists "parents manage own lesson tests" on lesson_test_attempts;
create policy "parents manage own lesson tests"
on lesson_test_attempts for all to authenticated
using (
  exists (select 1 from students s where s.id = lesson_test_attempts.student_id and s.parent_id = auth.uid())
)
with check (
  exists (select 1 from students s where s.id = lesson_test_attempts.student_id and s.parent_id = auth.uid())
);
