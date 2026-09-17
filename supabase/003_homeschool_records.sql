-- Homeschool portfolio / assignments / offline learning records.
-- Run once in Supabase SQL Editor after 002_parent_auth_rls.sql.

create table if not exists homeschool_records (
  id uuid primary key default gen_random_uuid(),
  student_id uuid not null references students(id) on delete cascade,
  record_type text not null check (record_type in ('assignment','reading','project','field_trip','physical','art','life_skill','other')),
  title text not null,
  curriculum text not null default 'nios' check (curriculum in ('maharashtra','nios','general')),
  subject text,
  notes text,
  minutes integer not null default 0 check (minutes between 0 and 600),
  status text not null default 'completed' check (status in ('planned','completed')),
  occurred_on date not null default current_date,
  due_date date,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create index if not exists homeschool_records_student_id_idx on homeschool_records(student_id);
create index if not exists homeschool_records_student_date_idx on homeschool_records(student_id, occurred_on desc);
create index if not exists homeschool_records_student_status_idx on homeschool_records(student_id, status);

alter table homeschool_records enable row level security;

drop policy if exists "parents manage own homeschool records" on homeschool_records;
create policy "parents manage own homeschool records"
on homeschool_records for all
to authenticated
using (
  exists (
    select 1 from students s
    where s.id = homeschool_records.student_id
      and s.parent_id = auth.uid()
  )
)
with check (
  exists (
    select 1 from students s
    where s.id = homeschool_records.student_id
      and s.parent_id = auth.uid()
  )
);
