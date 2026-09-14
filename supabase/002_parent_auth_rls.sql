-- Run after creating the parent account in Supabase Authentication.
-- This migration is generic and contains no family names or private identifiers.

alter table students
  add column if not exists parent_id uuid references auth.users(id) on delete cascade;

alter table students enable row level security;
alter table student_progress enable row level security;
alter table tutor_sessions enable row level security;
alter table tutor_messages enable row level security;

drop policy if exists "parents read own students" on students;
create policy "parents read own students"
on students for select
to authenticated
using (parent_id = auth.uid());

drop policy if exists "parents insert own students" on students;
create policy "parents insert own students"
on students for insert
to authenticated
with check (parent_id = auth.uid());

drop policy if exists "parents update own students" on students;
create policy "parents update own students"
on students for update
to authenticated
using (parent_id = auth.uid())
with check (parent_id = auth.uid());

drop policy if exists "parents delete own students" on students;
create policy "parents delete own students"
on students for delete
to authenticated
using (parent_id = auth.uid());

drop policy if exists "parents manage own progress" on student_progress;
create policy "parents manage own progress"
on student_progress for all
to authenticated
using (
  exists (
    select 1 from students s
    where s.id = student_progress.student_id
      and s.parent_id = auth.uid()
  )
)
with check (
  exists (
    select 1 from students s
    where s.id = student_progress.student_id
      and s.parent_id = auth.uid()
  )
);

drop policy if exists "parents manage own sessions" on tutor_sessions;
create policy "parents manage own sessions"
on tutor_sessions for all
to authenticated
using (
  exists (
    select 1 from students s
    where s.id = tutor_sessions.student_id
      and s.parent_id = auth.uid()
  )
)
with check (
  exists (
    select 1 from students s
    where s.id = tutor_sessions.student_id
      and s.parent_id = auth.uid()
  )
);

drop policy if exists "parents manage own messages" on tutor_messages;
create policy "parents manage own messages"
on tutor_messages for all
to authenticated
using (
  exists (
    select 1
    from tutor_sessions ts
    join students s on s.id = ts.student_id
    where ts.id = tutor_messages.session_id
      and s.parent_id = auth.uid()
  )
)
with check (
  exists (
    select 1
    from tutor_sessions ts
    join students s on s.id = ts.student_id
    where ts.id = tutor_messages.session_id
      and s.parent_id = auth.uid()
  )
);
