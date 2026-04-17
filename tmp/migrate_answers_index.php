<?php
require 'db.php';

try {
    // 1. Ensure objective_answers has all required columns (audit for scale)
    $db->exec("ALTER TABLE `objective_answers` ADD COLUMN IF NOT EXISTS `attempt_no` INT DEFAULT 1 AFTER `student_id`;");

    // 2. Add composite UNIQUE index. 
    // This allows us to use "INSERT ... ON DUPLICATE KEY UPDATE"
    // It also allows the database to find existing records for an actual student/question combo much faster.

    // Check if index already exists
    $idx_check = $db->query("SHOW INDEX FROM `objective_answers` WHERE Key_name = 'idx_unique_exam_attempt_question'");
    if ($idx_check->rowCount() === 0) {
        // Clean up duplicates if any before adding unique index (safety first)
        echo "Backing up pre-existing duplicate answers...\n";
        $db->exec("CREATE TABLE IF NOT EXISTS objective_answers_duplicates AS SELECT t1.* FROM objective_answers t1 INNER JOIN objective_answers t2 WHERE t1.id < t2.id AND t1.exam_id = t2.exam_id AND t1.student_id = t2.student_id AND t1.attempt_no = t2.attempt_no AND t1.question_id = t2.question_id;");
        
        echo "Cleaning pre-existing duplicate answers to prepare for unique index...\n";
        $db->exec("DELETE t1 FROM objective_answers t1 INNER JOIN objective_answers t2 WHERE t1.id < t2.id AND t1.exam_id = t2.exam_id AND t1.student_id = t2.student_id AND t1.attempt_no = t2.attempt_no AND t1.question_id = t2.question_id;");

        $db->exec("ALTER TABLE `objective_answers` ADD UNIQUE INDEX `idx_unique_exam_attempt_question` (`exam_id`, `student_id`, `attempt_no`, `question_id`);");
        echo "Successfully added UNIQUE INDEX for high-performance writes.\n";
    } else {
        echo "Unique Index already exists.\n";
    }
} catch (PDOException $e) {
    die("Database migration error: " . $e->getMessage() . "\n");
}
?>