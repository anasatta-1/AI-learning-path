
SET FOREIGN_KEY_CHECKS = 0;


DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
    `student_id` VARCHAR(50) PRIMARY KEY, 
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `advisor_name` VARCHAR(150),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `courses`;
CREATE TABLE `courses` (
    `course_id` VARCHAR(20) PRIMARY KEY,
    `course_name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `credits` INT NOT NULL DEFAULT 0,
    `category` VARCHAR(50) NOT NULL, 
    `semester` VARCHAR(50) NOT NULL  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `student_records`;
CREATE TABLE `student_records` (
    `record_id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` VARCHAR(50) NOT NULL,
    `course_id` VARCHAR(20) NOT NULL,
    `semester_taken` VARCHAR(50),
    `numeric_score` DECIMAL(5, 2) DEFAULT NULL, 
    `letter_grade` VARCHAR(5) DEFAULT NULL, 
    FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`course_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY `unique_student_course` (`student_id`, `course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ai_recommendations`;
CREATE TABLE `ai_recommendations` (
    `recommendation_id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` VARCHAR(50) NOT NULL,
    `course_id` VARCHAR(20) NOT NULL,
    `recommendation_reason` TEXT NOT NULL, 
    `status` ENUM('pending', 'approved_by_advisor', 'rejected') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`course_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;
