-- Apply after 006_student_login.sql. Transactional, repeatable hardening.
-- NOT VALID leaves existing data untouched while enforcing new/updated links.
-- Inspect and repair legacy mismatches before VALIDATE CONSTRAINT.
begin;
create unique index if not exists lesson_progress_id_student_uidx
  on lesson_progress(id, student_id);
do $$
begin
  if not exists (select 1 from pg_constraint where conrelid = 'public.lesson_step_records'::regclass and conname = 'lesson_steps_same_student_fk') then
    alter table lesson_step_records add constraint lesson_steps_same_student_fk
      foreign key (lesson_progress_id, student_id) references lesson_progress(id, student_id)
      on delete cascade not valid;
  end if;
  if not exists (select 1 from pg_constraint where conrelid = 'public.lesson_test_attempts'::regclass and conname = 'lesson_tests_same_student_fk') then
    alter table lesson_test_attempts add constraint lesson_tests_same_student_fk
      foreign key (lesson_progress_id, student_id) references lesson_progress(id, student_id)
      on delete cascade not valid;
  end if;
end;
$$;

create or replace function public.create_student_invite(p_student_id uuid)
returns table(invite_code text, expires_at timestamptz)
language plpgsql
security definer
set search_path = public, extensions
as $$
declare
  v_code text;
  v_expires timestamptz := now() + interval '24 hours';
begin
  if auth.uid() is null then
    raise exception 'Parent login required';
  end if;

  if not exists (
    select 1 from students s
    where s.id = p_student_id
      and s.parent_id = auth.uid()
  ) then
    raise exception 'Student is not available to this parent';
  end if;

  -- Serialize invite creation per child and refuse implicit relinking.
  perform 1 from students s where s.id = p_student_id for update;
  if exists (select 1 from students s where s.id = p_student_id and s.student_user_id is not null) then
    raise exception 'Student profile already has a login';
  end if;

  delete from student_invites
  where student_id = p_student_id
    and used_at is null;

  v_code := upper(encode(gen_random_bytes(16), 'hex'));

  insert into student_invites(student_id, code_hash, created_by, expires_at)
  values (
    p_student_id,
    encode(digest(v_code, 'sha256'), 'hex'),
    auth.uid(),
    v_expires
  );

  return query select v_code, v_expires;
end;
$$;

revoke all on function public.create_student_invite(uuid) from public;
grant execute on function public.create_student_invite(uuid) to authenticated;

create or replace function public.claim_student_invite(p_code text)
returns table(student_id uuid, full_name text, grade integer, board text)
language plpgsql
security definer
set search_path = public, extensions
as $$
declare
  v_invite student_invites%rowtype;
  v_uid uuid := auth.uid();
begin
  if v_uid is null then
    raise exception 'Student login required';
  end if;

  select * into v_invite
  from student_invites i
  where i.code_hash = encode(digest(upper(trim(p_code)), 'sha256'), 'hex')
    and i.used_at is null
    and i.expires_at > now()
  order by i.created_at desc
  limit 1
  for update;

  if v_invite.id is null then
    raise exception 'Invite code is invalid or expired';
  end if;

  if exists (
    select 1 from students s
    where s.student_user_id = v_uid
      and s.id <> v_invite.student_id
  ) then
    raise exception 'This student account is already linked to another profile';
  end if;

  -- Lock the profile as well as the invite; ownership must still match its creator.
  perform 1 from students s where s.id = v_invite.student_id for update;
  if not exists (select 1 from students s where s.id = v_invite.student_id
      and s.parent_id = v_invite.created_by and s.student_user_id is null) then
    raise exception 'Student profile already linked or invite no longer valid';
  end if;

  update students
  set student_user_id = v_uid
  where id = v_invite.student_id and student_user_id is null;

  update student_invites
  set used_at = now()
  where id = v_invite.id;

  return query
    select s.id, s.full_name, s.grade, s.board
    from students s
    where s.id = v_invite.student_id;
end;
$$;

revoke all on function public.claim_student_invite(text) from public;
grant execute on function public.claim_student_invite(text) to authenticated;


commit;
