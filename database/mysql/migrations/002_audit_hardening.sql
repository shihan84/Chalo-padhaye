-- Existing PHP databases only: review on staging before applying once.
-- Fresh databases already have these keys in schema.sql.
-- No destructive cleanup is performed. Legacy inconsistent rows must be repaired first.
ALTER TABLE lesson_progress ADD UNIQUE KEY uq_lesson_progress_id_student (id, student_id);
ALTER TABLE lesson_step_records ADD CONSTRAINT fk_steps_progress_student
  FOREIGN KEY (lesson_progress_id, student_id) REFERENCES lesson_progress(id, student_id) ON DELETE CASCADE;
ALTER TABLE lesson_test_attempts ADD CONSTRAINT fk_tests_progress_student
  FOREIGN KEY (lesson_progress_id, student_id) REFERENCES lesson_progress(id, student_id) ON DELETE CASCADE;
