<?php
session_start();
header('Content-Type: application/json');

// Set timeout untuk mencegah stuck
set_time_limit(30);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$body = file_get_contents('php://input');
$input = json_decode($body, true);
if (!$input || !is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON invalid atau tidak ada data']);
    exit;
}

require_once __DIR__ . '/../database/config.php';

// Validate database connection
if ($conn->connect_error) {
    http_response_code(503);
    echo json_encode(['error' => 'Database connection error']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

session_write_close();

$order_id = trim($input['order_id'] ?? '');
$total = isset($input['total']) ? intval($input['total']) : 0;
$discount = isset($input['discount']) ? intval($input['discount']) : 0;
$shipping = isset($input['shipping']) ? intval($input['shipping']) : 0;
$voucher = trim($input['voucher'] ?? '');
$payment_method = trim($input['payment_method'] ?? '');
$payment_type = trim($input['payment_type'] ?? 'immediate');

if ($order_id === '' || $total <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'order_id dan total diperlukan']);
    exit;
}

// Determine payment_status and order status based on type
if ($payment_type === 'cod') {
    $payment_status = 'cod';
    $status = 'pending';
} else {
    // immediate payment - simulate as success
    $payment_status = 'success';
    $status = 'processing';
}

// Directly save order to database (avoid cURL session locking issues)
// Check if table exists first to avoid unnecessary CREATE operation
$tableExists = false;
$checkTable = $conn->query("SHOW TABLES LIKE 'orders'");
if ($checkTable && $checkTable->num_rows > 0) {
    $tableExists = true;
}

if (!$tableExists) {
    // Create orders table if not exists
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
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($createSql)) {
        http_response_code(500);
        echo json_encode(['error' => 'Table creation failed: ' . $conn->error]);
        exit;
    }
}

// Prepare order data
$address = json_encode($input['address'] ?? []);
$items = json_encode($input['items'] ?? []);
$paymentResult = json_encode(['simulated' => true, 'method' => $payment_method]);
$message = trim($input['message'] ?? '');
$timestamp = date('Y-m-d H:i:s');

// Insert order
$sql = "INSERT INTO orders (order_id, user_id, payment_method, payment_status, status, total, discount, shipping, voucher, message, address, items, payment_result, timestamp)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param(
    'sisssiiissssss',
    $order_id,
    $user_id,
    $payment_method,
    $payment_status,
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
    $errorMsg = $stmt->error;
    $stmt->close();
    http_response_code(500);
    if (strpos($errorMsg, 'Duplicate entry') !== false) {
        // Return existing order data for idempotency on repeated payment requests.
        $checkStmt = $conn->prepare('SELECT payment_status, status FROM orders WHERE order_id = ? LIMIT 1');
        if ($checkStmt) {
            $checkStmt->bind_param('s', $order_id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            if ($existing = $result->fetch_assoc()) {
                echo json_encode([
                    'success' => true,
                    'orderId' => $order_id,
                    'payment_status' => $existing['payment_status'] ?? $payment_status,
                    'status' => $existing['status'] ?? $status,
                    'message' => 'Order sudah diproses sebelumnya.'
                ]);
                $checkStmt->close();
                exit;
            }
            $checkStmt->close();
        }
        echo json_encode(['error' => 'Order ID sudah ada. Silakan coba lagi dengan order ID yang berbeda.']);
    } elseif (strpos($errorMsg, 'foreign key') !== false || strpos($errorMsg, 'FOREIGN KEY') !== false) {
        echo json_encode(['error' => 'User belum terdaftar di database. Silakan login ulang.']);
    } else {
        echo json_encode(['error' => 'Insert order gagal: ' . $errorMsg]);
    }
    exit;
}

$stmt->close();
$respData = ['success' => true, 'orderId' => $order_id];

// If voucher was used and immediate payment, decrement quota (best-effort)
if ($voucher !== '' && $payment_type !== 'cod') {
    $q = $conn->prepare('UPDATE vouchers SET quota = GREATEST(0, quota - 1) WHERE code = ?');
    if ($q) {
        $q->bind_param('s', $voucher);
        $q->execute();
        $q->close();
    }
}

echo json_encode(array_merge(['ok' => true, 'order_id' => $order_id, 'payment_status' => $payment_status], $respData));
exit;
