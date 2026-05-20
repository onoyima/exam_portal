<?php
require 'auth.php';
check_auth('admin');

$type = isset($_GET['type']) ? trim($_GET['type']) : '';

if (empty($type)) {
    die('Error: Missing template type specifier.');
}

switch ($type) {
    case 'student_bulk':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="student_bulk_upload_template.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Firstname', 'Middlename', 'Lastname', 'Email', 'Matric No', 'Password']);
        fputcsv($out, ['Jane', 'Elizabeth', 'Doe', 'jane.doe@university.edu', 'MAT/2026/001', 'Pass123']);
        fputcsv($out, ['John', 'Robert', 'Smith', 'john.smith@university.edu', 'MAT/2026/002', 'Pass456']);
        fclose($out);
        exit;

    case 'student_assign':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="student_assignment_template.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Name', 'Matric No', 'Username']);
        fputcsv($out, ['Alice Brown', 'MAT/2026/003', 'alicebrown']);
        fputcsv($out, ['Charlie Green', 'MAT/2026/004', 'charliegreen']);
        fclose($out);
        exit;

    case 'practical_steps':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="practical_steps_template.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Step Text', 'Score Values']);
        fputcsv($out, ['Perform hand hygiene before starting the clinical procedure.', '0,1,2']);
        fputcsv($out, ['Confirm student identification and match with exam records.', '0,1']);
        fputcsv($out, ['Properly locate the landmark and perform safe needle insertion.', '0,1,2,3']);
        fputcsv($out, ['Dispose of clinical waste appropriately in the designated bin.', '0,1']);
        fclose($out);
        exit;

    case 'objective_csv_simple':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="objective_mcq_simple_template.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Question', 'Option A', 'Option B', 'Option C', 'Option D', 'Correct Option', 'Mark']);
        fputcsv($out, ['Which database is primarily used in this exam portal?', 'MySQL', 'PostgreSQL', 'SQLite', 'MongoDB', 'B', '1.5']);
        fputcsv($out, ['What does OSCE stand for in professional academic testing?', 'Objective Structured Clinical Examination', 'Open Source Certified Engineer', 'Online System Competency Evaluation', 'Organized Student Competency Exam', 'A', '2.0']);
        fclose($out);
        exit;

    case 'objective_csv_extended':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="objective_mcq_extended_template.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Question', 'Question_Image', 'Option A', 'Option_A_Image', 'Option B', 'Option_B_Image', 'Option C', 'Option_C_Image', 'Option D', 'Option_D_Image', 'Correct Option (A-D)', 'Mark']);
        fputcsv($out, ['Identify the muscle highlighted in the anatomical diagram.', 'muscles_diagram.png', 'Deltoid', '', 'Biceps', '', 'Triceps', '', 'Quadriceps', '', 'A', '2.5']);
        fputcsv($out, ['Which graph represents the correct isothermal expansion curve?', 'isothermal_question.png', 'Graph A', 'graph_a.png', 'Graph B', 'graph_b.png', 'Graph C', 'graph_c.png', 'Graph D', 'graph_d.png', 'C', '3.0']);
        fclose($out);
        exit;

    case 'objective_aiken':
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="objective_mcq_aiken_template.txt"');
        echo "What is the default port number for a standard MySQL database installation?\r\n";
        echo "A. 80\r\n";
        echo "B. 443\r\n";
        echo "C. 3306\r\n";
        echo "D. 8080\r\n";
        echo "ANSWER: C\r\n\r\n";
        echo "Which of the following is a server-side scripting language?\r\n";
        echo "A. HTML\r\n";
        echo "B. CSS\r\n";
        echo "C. PHP\r\n";
        echo "D. JavaScript\r\n";
        echo "ANSWER: C\r\n";
        exit;

    case 'objective_images_zip':
        if (!class_exists('ZipArchive')) {
            die('Error: ZipArchive PHP extension is not enabled on this server.');
        }
        
        $zip = new ZipArchive();
        $tmpFile = tempnam(sys_get_temp_dir(), 'zip');
        
        if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            // base64 encoded transparent 1x1 png image
            $dummyImage = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
            $zip->addFromString('muscles_diagram.png', $dummyImage);
            $zip->addFromString('isothermal_question.png', $dummyImage);
            $zip->addFromString('graph_a.png', $dummyImage);
            $zip->addFromString('graph_b.png', $dummyImage);
            $zip->addFromString('graph_c.png', $dummyImage);
            $zip->addFromString('graph_d.png', $dummyImage);
            $zip->close();
            
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="sample_image_library.zip"');
            header('Content-Length: ' . filesize($tmpFile));
            readfile($tmpFile);
            unlink($tmpFile);
            exit;
        } else {
            die('Error: Could not generate zip file template.');
        }
        break;

    default:
        die('Error: Invalid template type.');
}
