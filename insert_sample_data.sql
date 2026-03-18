-- Insert sample student
INSERT INTO `students` (`student_id`, `first_name`, `last_name`, `advisor_name`) VALUES
('11391', 'Maria', 'Garcia', 'Dr. Sarah Chen');

-- Insert sample student records using actual course IDs from the dataset (to avoid foreign key constraint errors)
INSERT INTO `student_records` (`student_id`, `course_id`, `semester_taken`, `letter_grade`) VALUES
('11391', 'ENGL141', '1st fall', 'B+'),
('11391', 'PHYS101', '1st fall', 'B'),
('11391', 'MATH121', '1st fall', 'B-'),
('11391', 'TURK100', '1st fall', 'A-'),
('11391', 'AIEN100', '1st fall', 'A');

-- Insert sample AI recommendations for the student
INSERT INTO `ai_recommendations` (`student_id`, `course_id`, `recommendation_reason`, `status`) VALUES
('11391', 'ENGL142', 'Based on your strong performance in ENGL141, we recommend ENGL142 to continue building your communication skills.', 'pending'),
('11391', 'PHYS102', 'PHYS102 naturally follows your completion of PHYS101 and is required for core engineering progression.', 'pending'),
('11391', 'MATH122', 'Discrete Mathematics is crucial for computer engineering and complements your linear algebra background.', 'pending');
