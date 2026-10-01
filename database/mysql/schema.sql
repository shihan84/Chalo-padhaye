-- Chalo Padhaye - Hostinger MySQL schema
-- Hostinger PHP branch only. Do not run against the Supabase PostgreSQL database.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
  id CHAR(36) PRIMARY KEY,
  email VARCHAR(254) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('parent','student') NOT NULL DEFAULT 'parent',
  full_name VARCHAR(160) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS students (
  id CHAR(36) PRIMARY KEY,
  full_name VARCHAR(160) NOT NULL,
  grade TINYINT UNSIGNED NOT NULL,
  medium VARCHAR(80) NOT NULL DEFAULT 'English',
  board VARCHAR(120) NOT NULL DEFAULT 'Maharashtra State Board',
  parent_id CHAR(36) NOT NULL,
  student_user_id CHAR(36) NULL UNIQUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_students_parent FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_students_user FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_students_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_progress (
  id CHAR(36) PRIMARY KEY,
  student_id CHAR(36) NOT NULL,
  subject VARCHAR(160) NOT NULL,
  chapter VARCHAR(255) NULL,
  topic VARCHAR(255) NULL,
  score SMALLINT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(40) NOT NULL DEFAULT 'started',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_progress_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  INDEX idx_progress_student (student_id),
  INDEX idx_progress_lookup (student_id, subject, topic)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tutor_sessions (
  id CHAR(36) PRIMARY KEY,
  student_id CHAR(36) NOT NULL,
  subject VARCHAR(160) NULL,
  chapter VARCHAR(255) NULL,
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at TIMESTAMP NULL,
  CONSTRAINT fk_sessions_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  INDEX idx_sessions_student (student_id, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tutor_messages (
  id CHAR(36) PRIMARY KEY,
  session_id CHAR(36) NOT NULL,
  role ENUM('student','tutor') NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_messages_session FOREIGN KEY (session_id) REFERENCES tutor_sessions(id) ON DELETE CASCADE,
  INDEX idx_messages_session (session_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homeschool_records (
  id CHAR(36) PRIMARY KEY,
  student_id CHAR(36) NOT NULL,
  record_type ENUM('assignment','reading','project','field_trip','physical','art','life_skill','other') NOT NULL,
  title VARCHAR(255) NOT NULL,
  curriculum ENUM('maharashtra','nios','cbse','telangana','general') NOT NULL DEFAULT 'nios',
  subject VARCHAR(160) NULL,
  notes TEXT NULL,
  minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('planned','completed') NOT NULL DEFAULT 'completed',
  occurred_on DATE NOT NULL,
  due_date DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_records_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  INDEX idx_records_student_date (student_id, occurred_on),
  INDEX idx_records_student_status (student_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_progress (
  id CHAR(36) PRIMARY KEY,
  student_id CHAR(36) NOT NULL,
  curriculum ENUM('maharashtra','nios','cbse','telangana') NOT NULL,
  subject VARCHAR(160) NOT NULL,
  lesson_id VARCHAR(190) NOT NULL,
  lesson_order INT NOT NULL DEFAULT 1,
  lesson_title VARCHAR(255) NOT NULL,
  status ENUM('locked','available','in_progress','test_ready','mastered','parent_unlocked') NOT NULL DEFAULT 'locked',
  current_step VARCHAR(190) NULL,
  percent_complete TINYINT UNSIGNED NOT NULL DEFAULT 0,
  test_score TINYINT UNSIGNED NULL,
  test_attempts INT UNSIGNED NOT NULL DEFAULT 0,
  started_at TIMESTAMP NULL,
  mastered_at TIMESTAMP NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_lesson_progress_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  UNIQUE KEY uq_lesson_progress (student_id, curriculum, subject, lesson_id),
  INDEX idx_lesson_progress_student (student_id, curriculum, subject, lesson_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_step_records (
  id CHAR(36) PRIMARY KEY,
  lesson_progress_id CHAR(36) NOT NULL,
  student_id CHAR(36) NOT NULL,
  step_id VARCHAR(190) NOT NULL,
  step_order INT NOT NULL,
  step_title VARCHAR(255) NOT NULL,
  status ENUM('locked','available','in_progress','completed') NOT NULL DEFAULT 'locked',
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  correct_count INT UNSIGNED NOT NULL DEFAULT 0,
  score TINYINT UNSIGNED NULL,
  started_at TIMESTAMP NULL,
  completed_at TIMESTAMP NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_steps_progress FOREIGN KEY (lesson_progress_id) REFERENCES lesson_progress(id) ON DELETE CASCADE,
  CONSTRAINT fk_steps_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  UNIQUE KEY uq_lesson_step (lesson_progress_id, step_id),
  INDEX idx_steps_student (student_id, lesson_progress_id, step_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_test_attempts (
  id CHAR(36) PRIMARY KEY,
  student_id CHAR(36) NOT NULL,
  lesson_progress_id CHAR(36) NOT NULL,
  attempt_no INT UNSIGNED NOT NULL,
  score TINYINT UNSIGNED NOT NULL,
  passed BOOLEAN NOT NULL DEFAULT FALSE,
  correct_count INT UNSIGNED NOT NULL DEFAULT 0,
  question_count INT UNSIGNED NOT NULL DEFAULT 0,
  summary JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tests_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_tests_progress FOREIGN KEY (lesson_progress_id) REFERENCES lesson_progress(id) ON DELETE CASCADE,
  INDEX idx_tests_student (student_id, lesson_progress_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_invites (
  id CHAR(36) PRIMARY KEY,
  student_id CHAR(36) NOT NULL,
  code_hash CHAR(64) NOT NULL UNIQUE,
  created_by CHAR(36) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  used_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_invites_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_invites_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_invites_student (student_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_sessions (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at TIMESTAMP NULL,
  CONSTRAINT fk_auth_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_auth_sessions_user (user_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
