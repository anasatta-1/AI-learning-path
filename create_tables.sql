-- Learning Path Database Schema
-- Matches structure of CSV data files.
-- Run this script once to create / recreate all required tables.

CREATE DATABASE IF NOT EXISTS `capstonef`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `capstonef`;

-- ──────────────────────────────────────────────
-- 1. Courses catalog
--    CSV columns: course_id, course_name, description, credits, category, semester
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `courses` (
  `course_id`   VARCHAR(20)  NOT NULL,
  `course_name` VARCHAR(120) NOT NULL,
  `description` TEXT         DEFAULT NULL,
  `credits`     TINYINT      NOT NULL DEFAULT 3,
  `category`    ENUM('core','elective') NOT NULL DEFAULT 'core',
  `semester`    VARCHAR(20)  NOT NULL,
  PRIMARY KEY (`course_id`)
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────
-- 2. Students
--    CSV columns: student_id, enrollment_year, gender, age
--    (first_name, last_name, advisor_name removed — not in CSV)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `students` (
  `student_id`       VARCHAR(20)  NOT NULL,
  `enrollment_year`  SMALLINT     NOT NULL,
  `gender`           CHAR(1)      NOT NULL DEFAULT 'M',
  `age`              TINYINT      NOT NULL DEFAULT 18,
  PRIMARY KEY (`student_id`)
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────
-- 3. Prerequisites
--    CSV columns: course_id, prerequisite_course_id
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `prerequisites` (
  `course_id`              VARCHAR(20) NOT NULL,
  `prerequisite_course_id` VARCHAR(20) NOT NULL,
  PRIMARY KEY (`course_id`, `prerequisite_course_id`),
  CONSTRAINT `fk_prereq_course`  FOREIGN KEY (`course_id`)              REFERENCES `courses`(`course_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prereq_prereq`  FOREIGN KEY (`prerequisite_course_id`) REFERENCES `courses`(`course_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────
-- 4. Student academic records (courses taken)
--    CSV columns: student_id, course_name, course_id, final_result, score, letter_grade
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `student_records` (
  `record_id`      INT          NOT NULL AUTO_INCREMENT,
  `student_id`     VARCHAR(20)  NOT NULL,
  `course_id`      VARCHAR(20)  NOT NULL,
  `course_name`    VARCHAR(120) DEFAULT NULL,
  `final_result`   VARCHAR(20)  DEFAULT NULL,
  `score`          VARCHAR(10)  DEFAULT NULL,
  `letter_grade`   VARCHAR(10)  DEFAULT NULL,
  PRIMARY KEY (`record_id`),
  KEY `idx_student` (`student_id`),
  CONSTRAINT `fk_sr_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sr_course`  FOREIGN KEY (`course_id`)  REFERENCES `courses`(`course_id`)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────
-- 5. AI-generated recommendations (cache)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ai_recommendations` (
  `rec_id`                INT          NOT NULL AUTO_INCREMENT,
  `student_id`            VARCHAR(20)  NOT NULL,
  `course_id`             VARCHAR(20)  NOT NULL,
  `recommendation_reason` TEXT         DEFAULT NULL,
  `status`                ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `created_at`            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rec_id`),
  KEY `idx_rec_student` (`student_id`),
  CONSTRAINT `fk_rec_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rec_course`  FOREIGN KEY (`course_id`)  REFERENCES `courses`(`course_id`)  ON DELETE CASCADE
) ENGINE=InnoDB;
