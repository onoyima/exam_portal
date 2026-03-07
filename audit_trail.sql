-- Audit Trail System for Assessment Portal
-- BMXcode Initiator

-- Audit trail table to track all user actions
CREATE TABLE IF NOT EXISTS audit_trail (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  user_type ENUM('admin', 'examiner', 'student') NOT NULL,
  username VARCHAR(100),
  action VARCHAR(255) NOT NULL,
  table_name VARCHAR(100),
  record_id INT,
  old_values TEXT,
  new_values TEXT,
  ip_address VARCHAR(45),
  user_agent TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_action (user_id, action),
  INDEX idx_created_at (created_at),
  INDEX idx_table_record (table_name, record_id)
);

-- System events table for tracking system-level activities
CREATE TABLE IF NOT EXISTS system_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(100) NOT NULL,
  event_description TEXT,
  severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
  user_id INT,
  username VARCHAR(100),
  ip_address VARCHAR(45),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_event_type (event_type),
  INDEX idx_severity (severity),
  INDEX idx_created_at (created_at)
);

-- Login history table
CREATE TABLE IF NOT EXISTS login_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  username VARCHAR(100) NOT NULL,
  user_type ENUM('admin', 'examiner', 'student') NOT NULL,
  login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  logout_time DATETIME NULL,
  ip_address VARCHAR(45),
  user_agent TEXT,
  status ENUM('success', 'failed') DEFAULT 'success',
  INDEX idx_user_login (user_id, login_time),
  INDEX idx_username (username),
  INDEX idx_status (status)
);

-- Data export history
CREATE TABLE IF NOT EXISTS export_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  username VARCHAR(100),
  export_type VARCHAR(100) NOT NULL,
  file_name VARCHAR(255),
  record_count INT,
  ip_address VARCHAR(45),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_export (user_id, created_at),
  INDEX idx_export_type (export_type)
);

-- Insert initial audit trail entry
INSERT INTO system_events (event_type, event_description, severity) VALUES 
('system_startup', 'Assessment Portal System Initialized', 'low');

-- Create triggers for automatic audit trail
DELIMITER //

-- Trigger for students table
CREATE TRIGGER audit_students_insert AFTER INSERT ON students
FOR EACH ROW
BEGIN
  INSERT INTO audit_trail (user_id, user_type, username, action, table_name, record_id, new_values, ip_address, user_agent)
  VALUES (
    IFNULL(@current_user_id, 0),
    IFNULL(@current_user_type, 'admin'),
    IFNULL(@current_username, 'system'),
    'INSERT',
    'students',
    NEW.id,
    JSON_OBJECT('name', NEW.name, 'matric_no', NEW.matric_no, 'username', NEW.username),
    IFNULL(@current_ip, ''),
    IFNULL(@current_user_agent, '')
  );
END//

CREATE TRIGGER audit_students_update AFTER UPDATE ON students
FOR EACH ROW
BEGIN
  INSERT INTO audit_trail (user_id, user_type, username, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
  VALUES (
    IFNULL(@current_user_id, 0),
    IFNULL(@current_user_type, 'admin'),
    IFNULL(@current_username, 'system'),
    'UPDATE',
    'students',
    NEW.id,
    JSON_OBJECT('name', OLD.name, 'matric_no', OLD.matric_no, 'username', OLD.username),
    JSON_OBJECT('name', NEW.name, 'matric_no', NEW.matric_no, 'username', NEW.username),
    IFNULL(@current_ip, ''),
    IFNULL(@current_user_agent, '')
  );
END//

CREATE TRIGGER audit_students_delete AFTER DELETE ON students
FOR EACH ROW
BEGIN
  INSERT INTO audit_trail (user_id, user_type, username, action, table_name, record_id, old_values, ip_address, user_agent)
  VALUES (
    IFNULL(@current_user_id, 0),
    IFNULL(@current_user_type, 'admin'),
    IFNULL(@current_username, 'system'),
    'DELETE',
    'students',
    OLD.id,
    JSON_OBJECT('name', OLD.name, 'matric_no', OLD.matric_no, 'username', OLD.username),
    IFNULL(@current_ip, ''),
    IFNULL(@current_user_agent, '')
  );
END//

-- Trigger for questions table
CREATE TRIGGER audit_questions_insert AFTER INSERT ON questions
FOR EACH ROW
BEGIN
  INSERT INTO audit_trail (user_id, user_type, username, action, table_name, record_id, new_values, ip_address, user_agent)
  VALUES (
    IFNULL(@current_user_id, 0),
    IFNULL(@current_user_type, 'admin'),
    IFNULL(@current_username, 'system'),
    'INSERT',
    'questions',
    NEW.id,
    JSON_OBJECT('text', NEW.text, 'score_values', NEW.score_values),
    IFNULL(@current_ip, ''),
    IFNULL(@current_user_agent, '')
  );
END//

CREATE TRIGGER audit_questions_update AFTER UPDATE ON questions
FOR EACH ROW
BEGIN
  INSERT INTO audit_trail (user_id, user_type, username, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
  VALUES (
    IFNULL(@current_user_id, 0),
    IFNULL(@current_user_type, 'admin'),
    IFNULL(@current_username, 'system'),
    'UPDATE',
    'questions',
    NEW.id,
    JSON_OBJECT('text', OLD.text, 'score_values', OLD.score_values),
    JSON_OBJECT('text', NEW.text, 'score_values', NEW.score_values),
    IFNULL(@current_ip, ''),
    IFNULL(@current_user_agent, '')
  );
END//

CREATE TRIGGER audit_questions_delete AFTER DELETE ON questions
FOR EACH ROW
BEGIN
  INSERT INTO audit_trail (user_id, user_type, username, action, table_name, record_id, old_values, ip_address, user_agent)
  VALUES (
    IFNULL(@current_user_id, 0),
    IFNULL(@current_user_type, 'admin'),
    IFNULL(@current_username, 'system'),
    'DELETE',
    'questions',
    OLD.id,
    JSON_OBJECT('text', OLD.text, 'score_values', OLD.score_values),
    IFNULL(@current_ip, ''),
    IFNULL(@current_user_agent, '')
  );
END//

-- Trigger for exams table
CREATE TRIGGER audit_exams_insert AFTER INSERT ON exams
FOR EACH ROW
BEGIN
  INSERT INTO audit_trail (user_id, user_type, username, action, table_name, record_id, new_values, ip_address, user_agent)
  VALUES (
    IFNULL(@current_user_id, 0),
    IFNULL(@current_user_type, 'admin'),
    IFNULL(@current_username, 'system'),
    'INSERT',
    'exams',
    NEW.id,
    JSON_OBJECT('name', NEW.name, 'is_active', NEW.is_active),
    IFNULL(@current_ip, ''),
    IFNULL(@current_user_agent, '')
  );
END//

CREATE TRIGGER audit_exams_update AFTER UPDATE ON exams
FOR EACH ROW
BEGIN
  INSERT INTO audit_trail (user_id, user_type, username, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
  VALUES (
    IFNULL(@current_user_id, 0),
    IFNULL(@current_user_type, 'admin'),
    IFNULL(@current_username, 'system'),
    'UPDATE',
    'exams',
    NEW.id,
    JSON_OBJECT('name', OLD.name, 'is_active', OLD.is_active),
    JSON_OBJECT('name', NEW.name, 'is_active', NEW.is_active),
    IFNULL(@current_ip, ''),
    IFNULL(@current_user_agent, '')
  );
END//

-- Trigger for answers table (assessment submissions)
CREATE TRIGGER audit_answers_insert AFTER INSERT ON answers
FOR EACH ROW
BEGIN
  INSERT INTO audit_trail (user_id, user_type, username, action, table_name, record_id, new_values, ip_address, user_agent)
  VALUES (
    NEW.examiner_id,
    'examiner',
    (SELECT username FROM examiners WHERE id = NEW.examiner_id),
    'ASSESSMENT_SUBMITTED',
    'answers',
    NEW.id,
    JSON_OBJECT('exam_id', NEW.exam_id, 'student_id', NEW.student_id, 'question_id', NEW.question_id, 'score', NEW.score),
    IFNULL(@current_ip, ''),
    IFNULL(@current_user_agent, '')
  );
END//

DELIMITER ;

-- Create stored procedures for audit trail management
DELIMITER //

-- Procedure to set current user context for triggers
CREATE PROCEDURE SetAuditContext(
  IN p_user_id INT,
  IN p_user_type VARCHAR(20),
  IN p_username VARCHAR(100),
  IN p_ip_address VARCHAR(45),
  IN p_user_agent TEXT
)
BEGIN
  SET @current_user_id = p_user_id;
  SET @current_user_type = p_user_type;
  SET @current_username = p_username;
  SET @current_ip = p_ip_address;
  SET @current_user_agent = p_user_agent;
END//

-- Procedure to log system events
CREATE PROCEDURE LogSystemEvent(
  IN p_event_type VARCHAR(100),
  IN p_event_description TEXT,
  IN p_severity VARCHAR(20),
  IN p_user_id INT,
  IN p_username VARCHAR(100),
  IN p_ip_address VARCHAR(45)
)
BEGIN
  INSERT INTO system_events (event_type, event_description, severity, user_id, username, ip_address)
  VALUES (p_event_type, p_event_description, p_severity, p_user_id, p_username, p_ip_address);
END//

-- Procedure to log login attempts
CREATE PROCEDURE LogLogin(
  IN p_user_id INT,
  IN p_username VARCHAR(100),
  IN p_user_type VARCHAR(20),
  IN p_ip_address VARCHAR(45),
  IN p_user_agent TEXT,
  IN p_status VARCHAR(20)
)
BEGIN
  INSERT INTO login_history (user_id, username, user_type, ip_address, user_agent, status)
  VALUES (p_user_id, p_username, p_user_type, p_ip_address, p_user_agent, p_status);
END//

-- Procedure to log data exports
CREATE PROCEDURE LogExport(
  IN p_user_id INT,
  IN p_username VARCHAR(100),
  IN p_export_type VARCHAR(100),
  IN p_file_name VARCHAR(255),
  IN p_record_count INT,
  IN p_ip_address VARCHAR(45)
)
BEGIN
  INSERT INTO export_history (user_id, username, export_type, file_name, record_count, ip_address)
  VALUES (p_user_id, p_username, p_export_type, p_file_name, p_record_count, p_ip_address);
END//

DELIMITER ; 