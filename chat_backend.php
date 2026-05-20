<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// Get sender id and role
$stmt = $db->prepare("SELECT id, role FROM examiners WHERE username=?");
$stmt->execute([$_SESSION['user']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$sender_id = $row['id'];
$sender_role = $row['role'];

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get contacts action
    if (isset($_GET['action']) && $_GET['action'] === 'get_contacts') {
        $stmt = $db->prepare("SELECT id, name, username, role FROM examiners WHERE username != ? ORDER BY name");
        $stmt->execute([$_SESSION['user']]);
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add online status (simple implementation - you can enhance this)
        foreach ($contacts as &$contact) {
            $contact['online'] = true; // You can implement real online status here
        }
        
        echo json_encode(['success' => true, 'contacts' => $contacts]);
        exit;
    }
    
    // Unread count for notifications
    if (isset($_GET['action']) && $_GET['action'] === 'unread_count') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM chat_messages WHERE receiver_id=? AND (is_read IS NULL OR is_read=0)");
        $stmt->execute([$sender_id]);
        $count = $stmt->fetchColumn();
        echo json_encode(['success' => true, 'unread' => (int)$count]);
        exit;
    }
    
    // Unread message previews for dropdown
    if (isset($_GET['action']) && $_GET['action'] === 'recent_unread') {
        $stmt = $db->prepare("SELECT m.id, m.sender_id, e.name as sender_name, m.message, m.created_at FROM chat_messages m JOIN examiners e ON m.sender_id=e.id WHERE m.receiver_id=? AND (m.is_read IS NULL OR m.is_read=0) ORDER BY m.created_at DESC LIMIT 5");
        $stmt->execute([$sender_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'messages' => $messages]);
        exit;
    }
    
    // All messages preview for dropdown (read or unread)
    if (isset($_GET['action']) && $_GET['action'] === 'all_messages_preview') {
        $stmt = $db->prepare("SELECT m.id, m.sender_id, e.name as sender_name, m.message, m.created_at, (m.is_read IS NOT NULL AND m.is_read=1) as is_read FROM chat_messages m JOIN examiners e ON m.sender_id=e.id WHERE m.receiver_id=? ORDER BY m.created_at DESC LIMIT 10");
        $stmt->execute([$sender_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'messages' => $messages]);
        exit;
    }
    
    // Fetch messages
    $receiver_id = isset($_GET['receiver_id']) ? intval($_GET['receiver_id']) : null;
    $group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : null;
    $where = [];
    $params = [];
    if ($group_id) {
        $where[] = 'group_id=?';
        $params[] = $group_id;
    } else {
        $where[] = '((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) AND group_id IS NULL';
        $params = array_merge($params, [$sender_id, $receiver_id, $receiver_id, $sender_id]);
    }
    $sql = "SELECT m.*, e.name as sender_name FROM chat_messages m JOIN examiners e ON m.sender_id=e.id";
    if ($where) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    $sql .= " ORDER BY m.created_at ASC LIMIT 100";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // For media, return a base64 string for preview
    foreach ($messages as &$msg) {
        if ($msg['media_blob']) {
            $msg['media_base64'] = 'data:' . $msg['media_type'] . ';base64,' . base64_encode($msg['media_blob']);
        }
        unset($msg['media_blob']);
    }
    echo json_encode(['success' => true, 'messages' => $messages]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mark messages as read
    if (isset($_GET['action']) && $_GET['action'] === 'mark_read') {
        $where = 'receiver_id=? AND (is_read IS NULL OR is_read=0)';
        $params = [$sender_id];
        if (isset($_POST['sender_id'])) {
            $where .= ' AND sender_id=?';
            $params[] = intval($_POST['sender_id']);
        }
        $stmt = $db->prepare("UPDATE chat_messages SET is_read=1 WHERE $where");
        $stmt->execute($params);
        echo json_encode(['success' => true]);
        exit;
    }

    // Clear chats (admin only)
    if (isset($_GET['action']) && $_GET['action'] === 'clear_chats') {
        if ($sender_role === 'admin') {
            $db->exec("TRUNCATE TABLE chat_messages");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Not authorized']);
        }
        exit;
    }
    // Send message
    $receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : null;
    $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : null;
    $message = $_POST['message'] ?? '';
    $media_type = $_POST['media_type'] ?? null;
    $media_blob = null;
    if (isset($_FILES['media']) && $_FILES['media']['tmp_name']) {
        $media_blob = file_get_contents($_FILES['media']['tmp_name']);
        $media_type = $_FILES['media']['type'];
    }
    $stmt = $db->prepare("INSERT INTO chat_messages (sender_id, receiver_id, group_id, message, media_type, media_blob) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$sender_id, $receiver_id, $group_id, $message, $media_type, $media_blob]);
    echo json_encode(['success' => true]);
    exit;
} 
