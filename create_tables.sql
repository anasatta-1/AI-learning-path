-- Learning Path Database Schema
-- Run this script once to create all required tables.

CREATE DATABASE IF NOT EXISTS `capstonef`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `capstonef`;

-- ──────────────────────────────────────────────
-- 1. Courses catalog
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
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `students` (
  `student_id`   VARCHAR(20)  NOT NULL,
  `first_name`   VARCHAR(60)  NOT NULL,
  `last_name`    VARCHAR(60)  NOT NULL,
  `advisor_name` VARCHAR(120) DEFAULT NULL,
  PRIMARY KEY (`student_id`)
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────
-- 3. Student academic records (courses taken)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `student_records` (
  `record_id`      INT          NOT NULL AUTO_INCREMENT,
  `student_id`     VARCHAR(20)  NOT NULL,
  `course_id`      VARCHAR(20)  NOT NULL,
  `semester_taken` VARCHAR(20)  NOT NULL,
  `letter_grade`   VARCHAR(5)   DEFAULT NULL,
  PRIMARY KEY (`record_id`),
  KEY `idx_student` (`student_id`),
  CONSTRAINT `fk_sr_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sr_course`  FOREIGN KEY (`course_id`)  REFERENCES `courses`(`course_id`)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────
-- 4. AI-generated recommendations (cache)
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
