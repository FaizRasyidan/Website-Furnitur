<?php
session_start();
require_once __DIR__ . '/config.php';

// Only admin can delete orders
if (!isset($_SESSION['admin_name'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Accept form-encoded or JSON
$input = [];
if ($_SERVER['CONTENT_TYPE'] ?? '' === 'application/json') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?: [];
} else {
    $input = $_POST;
}

$orderId = trim($input['order_id'] ?? '');
if (!$orderId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing order_id']);
    exit();
}

$stmt = $conn->prepare('DELETE FROM orders WHERE order_id = ?');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit();
}
$stmt->bind_param('s', $orderId);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}
$stmt->close();
?>