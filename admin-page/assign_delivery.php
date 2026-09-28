<?php
session_start();
require_once __DIR__ . '/../database/config.php';
header('Content-Type: application/json; charset=utf-8');

// Check if admin is logged in
if (!isset($_SESSION['admin_name'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$action = trim($_POST['action'] ?? '');
$orderId = trim($_POST['order_id'] ?? '');
$deliveryPersonnelId = intval($_POST['delivery_personnel_id'] ?? 0);

// Validate tables exist
$ordersTableCheck = $conn->query("SHOW TABLES LIKE 'orders'");
if (!$ordersTableCheck || $ordersTableCheck->num_rows === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Orders table not found']);
    exit();
}

$deliveryTableCheck = $conn->query("SHOW TABLES LIKE 'delivery_personnel'");
if (!$deliveryTableCheck || $deliveryTableCheck->num_rows === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Delivery personnel table not found']);
    exit();
}

if ($action === 'assign') {
    // Assign delivery person to order
    if (empty($orderId) || $deliveryPersonnelId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order ID and delivery personnel ID required']);
        exit();
    }

    // Verify delivery person exists
    $stmt = $conn->prepare("SELECT id FROM delivery_personnel WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $deliveryPersonnelId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $stmt->close();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Delivery person not found']);
        exit();
    }
    $stmt->close();

    // Assign delivery person to order
    $stmt = $conn->prepare("UPDATE orders SET delivery_personnel_id = ? WHERE order_id = ?");
    $stmt->bind_param('is', $deliveryPersonnelId, $orderId);
    if ($stmt->execute()) {
        $stmt->close();
        
        // Update delivery person status
        $statusStmt = $conn->prepare("UPDATE delivery_personnel SET status = 'on_delivery' WHERE id = ?");
        $statusStmt->bind_param('i', $deliveryPersonnelId);
        $statusStmt->execute();
        $statusStmt->close();

        echo json_encode(['success' => true, 'message' => 'Delivery person assigned successfully']);
    } else {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to assign delivery person']);
    }
    exit();
}

if ($action === 'get_available') {
    // Get list of available delivery personnel
    $result = $conn->query("SELECT id, driver_name, vehicle_number, phone_number, status FROM delivery_personnel WHERE status IN ('active', 'inactive') ORDER BY driver_name ASC");
    $personnel = [];
    while ($row = $result->fetch_assoc()) {
        $personnel[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $personnel]);
    exit();
}

if ($action === 'remove') {
    // Remove delivery person from order
    if (empty($orderId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order ID required']);
        exit();
    }

    $stmt = $conn->prepare("UPDATE orders SET delivery_personnel_id = NULL WHERE order_id = ?");
    $stmt->bind_param('s', $orderId);
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'Delivery person removed successfully']);
    } else {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to remove delivery person']);
    }
    exit();
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
