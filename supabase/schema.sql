create table if not exists students (
  id uuid primary key default gen_random_uuid(),
  full_name text not null,
  grade integer not null check (grade in (3,5)),
  medium text not null default 'English',
  board text not null default 'Maharashtra State Board',
  parent_id uuid references auth.users(id) on delete cascade,
  created_at timestamptz not null default now()
);

create table if not exists student_progress (
  id uuid primary key default gen_random_uuid(),
  student_id uuid not null references students(id) on delete cascade,
  subject text not null,
  chapter text,
  topic text,
  score integer,
  attempts integer not null default 0,
  status text not null default 'started',
  updated_at timestamptz not null default now()
);

create table if not exists tutor_sessions (
  id uuid primary key default gen_random_uuid(),
  student_id uuid not null references students(id) on delete cascade,
  subject text,
  chapter text,
  started_at timestamptz not null default now(),
  ended_at timestamptz
);

create table if not exists tutor_messages (
  id uuid primary key default gen_random_uuid(),
  session_id uuid not null references tutor_sessions(id) on delete cascade,
  role text not null check (role in ('student','tutor')),
  message text not null,
  created_at timestamptz not null default now()
);

alter table students enable row level security;
alter table student_progress enable row level security;
alter table tutor_sessions enable row level security;
alter table tutor_messages enable row level security;
