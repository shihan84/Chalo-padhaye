-- Isolated test database only. Never run against production.
\set ON_ERROR_STOP on
create role anon;
create role authenticated;
create schema auth;
create table auth.users(id uuid primary key);
create function auth.uid() returns uuid language sql stable as
  $$ select nullif(current_setting('request.jwt.claim.sub', true), '')::uuid $$;
\ir ../supabase/schema.sql
\ir ../supabase/002_parent_auth_rls.sql
\ir ../supabase/003_homeschool_records.sql
\ir ../supabase/004_lesson_mastery.sql
\ir ../supabase/005_cbse_support.sql
\ir ../supabase/006_student_login.sql
\ir ../supabase/007_audit_hardening.sql
-- Verify the new migration is repeatable.
\ir ../supabase/007_audit_hardening.sql

grant usage on schema public, auth to authenticated;
grant execute on function auth.uid() to authenticated;
grant select,insert,update,delete on students,student_progress,tutor_sessions,
  tutor_messages,homeschool_records,lesson_progress,lesson_step_records,
  lesson_test_attempts to authenticated;

insert into auth.users values
 ('00000000-0000-0000-0000-000000000001'),
 ('00000000-0000-0000-0000-000000000002'),
 ('00000000-0000-0000-0000-000000000003');
insert into students(id,full_name,grade,parent_id,student_user_id) values
 ('10000000-0000-0000-0000-000000000001','Fixture A',3,'00000000-0000-0000-0000-000000000001','00000000-0000-0000-0000-000000000003'),
 ('10000000-0000-0000-0000-000000000002','Fixture B',3,'00000000-0000-0000-0000-000000000002',null),
 ('10000000-0000-0000-0000-000000000003','Fixture C',3,'00000000-0000-0000-0000-000000000001',null);
insert into lesson_progress(id,student_id,curriculum,subject,lesson_id,lesson_title) values
 ('20000000-0000-0000-0000-000000000002','10000000-0000-0000-0000-000000000002','maharashtra','math','math-01','Fixture');
insert into homeschool_records(id,student_id,record_type,title) values
 ('30000000-0000-0000-0000-000000000001','10000000-0000-0000-0000-000000000001','assignment','Fixture assignment');

set role authenticated;
set request.jwt.claim.sub='00000000-0000-0000-0000-000000000003';
do $$
declare n integer;
begin
 if (select count(*) from students) <> 1 then raise exception 'Student profile isolation failed'; end if;
 if (select count(*) from lesson_progress) <> 0 then raise exception 'Lesson isolation failed'; end if;
 if (select count(*) from homeschool_records) <> 1 then raise exception 'Student assignment read failed'; end if;
 update homeschool_records set title='Unauthorized edit';
 get diagnostics n=row_count;
 if n <> 0 then raise exception 'Student edited parent portfolio'; end if;
 begin
  insert into lesson_step_records(lesson_progress_id,student_id,step_id,step_order,step_title)
  values('20000000-0000-0000-0000-000000000002','10000000-0000-0000-0000-000000000001','learn',1,'Learn');
  raise exception 'Cross-child step reference accepted';
 exception when foreign_key_violation then null;
 end;
 begin
  insert into lesson_test_attempts(lesson_progress_id,student_id,attempt_no,score)
  values('20000000-0000-0000-0000-000000000002','10000000-0000-0000-0000-000000000001',1,100);
  raise exception 'Cross-child test reference accepted';
 exception when foreign_key_violation then null;
 end;
end;
$$;

set request.jwt.claim.sub='00000000-0000-0000-0000-000000000001';
do $$
declare code text;
begin
 if (select count(*) from students) <> 2 then raise exception 'Parent profile isolation failed'; end if;
 begin
  perform public.create_student_invite('10000000-0000-0000-0000-000000000001');
  raise exception 'Linked profile received replacement invite';
 exception when raise_exception then
  if sqlerrm <> 'Student profile already has a login' then raise; end if;
 end;
 select invite_code into code from public.create_student_invite('10000000-0000-0000-0000-000000000003');
 if length(code) <> 32 then raise exception 'Invite entropy check failed'; end if;
end;
$$;
reset role;
-- Stale pre-migration invite must not overwrite an existing linked account.
insert into student_invites(student_id,code_hash,created_by,expires_at)
 values('10000000-0000-0000-0000-000000000001',encode(digest('STALECODE','sha256'),'hex'),
 '00000000-0000-0000-0000-000000000001',now()+interval '1 hour');
set role authenticated;
set request.jwt.claim.sub='00000000-0000-0000-0000-000000000002';
do $$
begin
 begin
  perform public.claim_student_invite('STALECODE');
  raise exception 'Stale invite replaced linked student';
 exception when raise_exception then
  if sqlerrm <> 'Student profile already linked or invite no longer valid' then raise; end if;
 end;
end;
$$;
reset role;
select 'RLS and invite regression checks passed' as result;
