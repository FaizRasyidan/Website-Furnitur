<?php
header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

$orderId = trim($input['orderId'] ?? $input['order_id'] ?? '');

// Enforce server-side user identity: prefer session user_id as source-of-truth
$isAdmin = isset($_SESSION['admin_name']);
$sessionUserId = $_SESSION['user_id'] ?? null;

if ($isAdmin && isset($input['userId']) && intval($input['userId']) > 0) {
    // allow admin to create order on behalf of a user when explicitly provided
    $userId = intval($input['userId']);
} else {
    $userId = $sessionUserId;
}
$total = isset($input['total']) ? intval($input['total']) : 0;
$discount = isset($input['discount']) ? intval($input['discount']) : 0;
$shipping = isset($input['shipping']) ? intval($input['shipping']) : 0;
$voucher = trim($input['voucher'] ?? '');
$paymentMethod = trim($input['paymentMethod'] ?? $input['payment_method'] ?? '');
$paymentStatus = trim($input['paymentStatus'] ?? $input['payment_status'] ?? 'notdone');
$status = trim($input['status'] ?? 'pending');
$message = trim($input['message'] ?? '');
$address = json_encode($input['address'] ?? []);
$items = json_encode($input['items'] ?? []);
$paymentResult = json_encode($input['paymentResult'] ?? $input['payment_result'] ?? []);
$timestamp = isset($input['timestamp']) ? date('Y-m-d H:i:s', strtotime($input['timestamp'])) : date('Y-m-d H:i:s');

if ($orderId === '' || $total <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'orderId and total are required']);
    exit;
}

// Require authenticated session user unless admin supplied valid userId
if (!$isAdmin) {
    if ($sessionUserId === null || intval($sessionUserId) <= 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    // ensure we use session user id
    $userId = intval($sessionUserId);
} else {
    if (!isset($userId) || intval($userId) <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Admin must provide valid userId when creating orders for others']);
        exit;
    }
}

$createSql = "CREATE TABLE IF NOT EXISTS orders (
    order_id VARCHAR(100) PRIMARY KEY,
    user_id INT NOT NULL,
    payment_method VARCHAR(100) DEFAULT NULL,
    payment_status VARCHAR(50) DEFAULT 'pending',
    status VARCHAR(50) DEFAULT 'pending',
    total INT NOT NULL,
    discount INT DEFAULT 0,
    shipping INT DEFAULT 0,
    voucher VARCHAR(50) DEFAULT NULL,
    message TEXT DEFAULT NULL,
    address JSON DEFAULT NULL,
    items JSON DEFAULT NULL,
    payment_result JSON DEFAULT NULL,
    tracking_data JSON DEFAULT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (!$conn->query($createSql)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database table creation failed: ' . $conn->error]);
    exit;
}

$sql = "INSERT INTO orders (order_id, user_id, payment_method, payment_status, status, total, discount, shipping, voucher, message, address, items, payment_result, timestamp)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param(
    'sisssiiissssss',
    $orderId,
    $userId,
    $paymentMethod,
    $paymentStatus,
    $status,
    $total,
    $discount,
    $shipping,
    $voucher,
    $message,
    $address,
    $items,
    $paymentResult,
    $timestamp
);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Database insert failed: ' . $stmt->error]);
    exit;
}

echo json_encode(['success' => true, 'orderId' => $orderId]);
