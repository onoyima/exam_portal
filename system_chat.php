<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user'])) {
    die(json_encode(['error' => 'Not authenticated']));
}

$user = $_SESSION['user'];
$role = $_SESSION['role'] ?? 'user';

// Simple chatbot responses
$responses = [
    'hello' => 'Hello! How can I help you with the Assessment Portal today?',
    'help' => 'I can help you with:\n• Exam management\n• Student management\n• Question management\n• Analytics and reports\n• System navigation\n\nWhat would you like to know?',
    'exam' => 'To manage exams:\n1. Go to "Manage Exams"\n2. Create a new exam\n3. Add students and questions\n4. Activate when ready',
    'student' => 'To manage students:\n1. Go to "Manage Students"\n2. Add individual students or bulk upload\n3. Edit or delete as needed',
    'question' => 'To manage questions:\n1. Go to "Manage Questions"\n2. Add questions with score values\n3. Use bulk upload for multiple questions',
    'analytics' => 'View analytics in the "Analytics" section to see:\n• Student performance\n• Exam statistics\n• Score distributions',
    'report' => 'Generate reports in the "Reports" section to export exam results and student scores.',
    'chat' => 'Use the "Chat" feature to communicate with other users in real-time.',
    'logout' => 'Click "Logout" in the sidebar to safely exit the system.',
    'default' => 'I\'m here to help! Try asking about:\n• Exams\n• Students\n• Questions\n• Analytics\n• Reports\n• Chat\n• Help'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = strtolower(trim($_POST['message'] ?? ''));
    
    // Simple keyword matching
    $response = $responses['default'];
    
    if (strpos($input, 'hello') !== false || strpos($input, 'hi') !== false) {
        $response = $responses['hello'];
    } elseif (strpos($input, 'help') !== false) {
        $response = $responses['help'];
    } elseif (strpos($input, 'exam') !== false) {
        $response = $responses['exam'];
    } elseif (strpos($input, 'student') !== false) {
        $response = $responses['student'];
    } elseif (strpos($input, 'question') !== false) {
        $response = $responses['question'];
    } elseif (strpos($input, 'analytics') !== false || strpos($input, 'stat') !== false) {
        $response = $responses['analytics'];
    } elseif (strpos($input, 'report') !== false) {
        $response = $responses['report'];
    } elseif (strpos($input, 'chat') !== false) {
        $response = $responses['chat'];
    } elseif (strpos($input, 'logout') !== false || strpos($input, 'exit') !== false) {
        $response = $responses['logout'];
    }
    
    // Log the conversation
    $db->exec("
        CREATE TABLE IF NOT EXISTS system_chat_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(100) NOT NULL,
            user_role VARCHAR(20) NOT NULL,
            user_message TEXT NOT NULL,
            bot_response TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $stmt = $db->prepare("INSERT INTO system_chat_log (user_id, user_role, user_message, bot_response) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user, $role, $input, $response]);
    
    echo json_encode([
        'success' => true,
        'response' => $response,
        'timestamp' => date('H:i')
    ]);
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?> 