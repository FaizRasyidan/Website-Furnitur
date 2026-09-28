<?php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function respond($data) {
    echo json_encode($data);
    exit;
}

if (!$conn) {
    respond(['success' => false, 'message' => 'Database connection failed.']);
}

// Create chat table if missing
$createSql = "CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sender ENUM('user','admin') NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(user_id),
    INDEX(sender),
    INDEX(is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createSql);

$isAdmin = isset($_SESSION['admin_name']);
$userId = $_SESSION['user_id'] ?? null;
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'send_message':
        $message = trim($_POST['message'] ?? '');
        if ($message === '') {
            respond(['success' => false, 'message' => 'Message is empty.']);
        }

        if ($isAdmin) {
            $targetUser = intval($_POST['user_id'] ?? 0);
            if ($targetUser <= 0) {
                respond(['success' => false, 'message' => 'User ID is missing.']);
            }
            $sender = 'admin';
            $chatUserId = $targetUser;
        } else {
            if (!$userId) {
                respond(['success' => false, 'message' => 'Not authenticated.']);
            }
            $sender = 'user';
            $chatUserId = $userId;
        }

        $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, sender, message) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $chatUserId, $sender, $message);
        if (!$stmt->execute()) {
            respond(['success' => false, 'message' => 'Failed to save message.']);
        }
        $stmt->close();
        respond(['success' => true, 'message' => 'Message sent.']);
        break;

    case 'fetch_messages':
        if ($isAdmin) {
            $chatUserId = intval($_GET['user_id'] ?? 0);
            if ($chatUserId <= 0) {
                respond(['success' => false, 'message' => 'User ID is missing.']);
            }
        } else {
            if (!$userId) {
                respond(['success' => false, 'message' => 'Not authenticated.']);
            }
            $chatUserId = $userId;
        }

        $stmt = $conn->prepare("SELECT id, sender, message, is_read, created_at FROM chat_messages WHERE user_id = ? ORDER BY created_at ASC");
        $stmt->bind_param('i', $chatUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        $stmt->close();

        // mark messages as read for the current viewer
        if ($isAdmin) {
            $stmt = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE user_id = ? AND sender = 'user' AND is_read = 0");
        } else {
            $stmt = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE user_id = ? AND sender = 'admin' AND is_read = 0");
        }
        $stmt->bind_param('i', $chatUserId);
        $stmt->execute();
        $stmt->close();

        respond(['success' => true, 'messages' => $messages, 'user_id' => $chatUserId]);
        break;

    case 'fetch_threads':
        if (!$isAdmin) {
            respond(['success' => false, 'message' => 'Access denied.']);
        }

        $sql = "SELECT u.id AS user_id, u.name, u.email,
                 (SELECT message FROM chat_messages WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1) AS last_message,
                 (SELECT created_at FROM chat_messages WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1) AS last_time,
                 SUM(CASE WHEN sender = 'user' AND is_read = 0 THEN 1 ELSE 0 END) AS unread_count
                 FROM users u
                 JOIN chat_messages m ON m.user_id = u.id
                 GROUP BY u.id
                 ORDER BY last_time DESC";

        $result = $conn->query($sql);
        $threads = [];
        while ($row = $result->fetch_assoc()) {
            $threads[] = $row;
        }
        respond(['success' => true, 'threads' => $threads]);
        break;

    case 'fetch_unread_count':
        if ($isAdmin) {
            $result = $conn->query("SELECT COUNT(*) AS count FROM chat_messages WHERE sender = 'user' AND is_read = 0");
            $row = $result->fetch_assoc();
            respond(['success' => true, 'unread' => intval($row['count'])]);
        }
        if (!$userId) {
            respond(['success' => false, 'message' => 'Not authenticated.']);
        }
        $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM chat_messages WHERE user_id = ? AND sender = 'admin' AND is_read = 0");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        respond(['success' => true, 'unread' => intval($row['count'])]);
        break;

    default:
        respond(['success' => false, 'message' => 'Action not found.']);
        break;
}
