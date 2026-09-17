-- Add CBSE support and allow student profiles from Grade 1 through Grade 12.
-- Run once after 004_lesson_mastery.sql.

alter table if exists students
  drop constraint if exists students_grade_check;

alter table if exists students
  add constraint students_grade_check check (grade between 1 and 12);

-- Portfolio records can also belong to the CBSE curriculum.
alter table if exists homeschool_records
  drop constraint if exists homeschool_records_curriculum_check;

alter table if exists homeschool_records
  add constraint homeschool_records_curriculum_check
  check (curriculum in ('maharashtra','nios','cbse','general'));

-- Structured chapter mastery now supports CBSE too.
alter table if exists lesson_progress
  drop constraint if exists lesson_progress_curriculum_check;

alter table if exists lesson_progress
  add constraint lesson_progress_curriculum_check
  check (curriculum in ('maharashtra','nios','cbse'));
