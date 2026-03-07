-- Add created_at column to answers table if it doesn't exist
ALTER TABLE answers ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE answers ADD COLUMN IF NOT EXISTS updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Update existing records to have a default timestamp
UPDATE answers SET created_at = NOW() WHERE created_at IS NULL;

-- Add type to exams table
ALTER TABLE exams ADD COLUMN type ENUM('practical','objective') DEFAULT 'practical';

-- Add password to students table
ALTER TABLE students ADD COLUMN password VARCHAR(255) DEFAULT '12345';

-- Create table for objective questions (per exam)
CREATE TABLE IF NOT EXISTS objective_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  exam_id INT NOT NULL,
  question_text TEXT NOT NULL,
  option_a VARCHAR(255) NOT NULL,
  option_b VARCHAR(255) NOT NULL,
  option_c VARCHAR(255) NOT NULL,
  option_d VARCHAR(255) NOT NULL,
  correct_option CHAR(1) NOT NULL,
  FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
);

-- Create table for student answers to objective questions
CREATE TABLE IF NOT EXISTS objective_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  exam_id INT NOT NULL,
  student_id INT NOT NULL,
  question_id INT NOT NULL,
  selected_option CHAR(1) NOT NULL,
  submitted_at DATETIME NOT NULL,
  FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES objective_questions(id) ON DELETE CASCADE
); 