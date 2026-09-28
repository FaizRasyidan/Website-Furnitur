<?php
header('Content-Type: application/json');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/config.php';

function parseJsonField($value) {
    if ($value === null || $value === '') {
        return null;
    }
    if (is_array($value)) {
        return $value;
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : null;
}

function getOrderAddressCoordinates($address) {
    if (!is_array($address)) {
        return null;
    }
    if (isset($address['latitude'], $address['longitude']) && is_numeric($address['latitude']) && is_numeric($address['longitude'])) {
        return ['lat' => floatval($address['latitude']), 'lng' => floatval($address['longitude'])];
    }
    if (isset($address['lat'], $address['lng']) && is_numeric($address['lat']) && is_numeric($address['lng'])) {
        return ['lat' => floatval($address['lat']), 'lng' => floatval($address['lng'])];
    }
    return null;
}

function getStoreLocation($conn, $storeId = null) {
    if ($storeId !== null) {
        $stmt = $conn->prepare('SELECT latitude, longitude FROM store_locations WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $storeId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            if ($row && is_numeric($row['latitude']) && is_numeric($row['longitude'])) {
                return ['lat' => floatval($row['latitude']), 'lng' => floatval($row['longitude'])];
            }
        }
    }

    $result = $conn->query('SELECT latitude, longitude FROM store_locations ORDER BY id ASC LIMIT 1');
    if ($result && ($row = $result->fetch_assoc())) {
        if (is_numeric($row['latitude']) && is_numeric($row['longitude'])) {
            return ['lat' => floatval($row['latitude']), 'lng' => floatval($row['longitude'])];
        }
    }
    return ['lat' => -6.2088, 'lng' => 106.8456];
}

function haversineDistance($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371;
    $latFrom = deg2rad($lat1);
    $latTo = deg2rad($lat2);
    $lonFrom = deg2rad($lng1);
    $lonTo = deg2rad($lng2);
    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;
    $a = sin($latDelta / 2) * sin($latDelta / 2) + cos($latFrom) * cos($latTo) * sin($lonDelta / 2) * sin($lonDelta / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

$body = file_get_contents('php://input');
$input = json_decode($body, true);

if (!$input || !is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$order_id = trim($input['order_id'] ?? '');
$new_status = trim($input['new_status'] ?? '');
$payment_status = trim($input['payment_status'] ?? '');
$user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;

// Determine session user and admin flag
$isAdmin = isset($_SESSION['admin_name']);
$sessionUserId = $_SESSION['user_id'] ?? null;

if (!$order_id || (!$new_status && !$payment_status)) {
    http_response_code(400);
    echo json_encode(['error' => 'order_id and new_status or payment_status required']);
    exit;
}

// Map admin status to user-facing status
$statusMap = [
    'processing' => 'processing',
    'ready' => 'on_delivery',
    'siap_dikirim' => 'on_delivery',
    'dikirim' => 'on_delivery',
    'on_delivery' => 'on_delivery',
    'delivered' => 'delivered',
    'completed' => 'completed'
];

$mapped_status = $statusMap[$new_status] ?? $new_status;
$storeId = isset($input['store_id']) && is_numeric($input['store_id']) ? intval($input['store_id']) : null;

$trackingJson = null;
$trackingColumnExists = false;
$colCheck = $conn->query("SHOW COLUMNS FROM orders LIKE 'tracking_data'");
if ($colCheck && $colCheck->num_rows > 0) {
    $trackingColumnExists = true;
}

if ($mapped_status === 'on_delivery') {
    // Ensure tracking_data column exists before writing tracking data
    if (!$trackingColumnExists) {
        $conn->query("ALTER TABLE orders ADD COLUMN tracking_data JSON DEFAULT NULL");
        $colCheck = $conn->query("SHOW COLUMNS FROM orders LIKE 'tracking_data'");
        if ($colCheck && $colCheck->num_rows > 0) {
            $trackingColumnExists = true;
        }
    }

    // Safely fetch order address from order record
    $existingOrder = null;
    $stmt = $conn->prepare('SELECT address FROM orders WHERE order_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $order_id);
        $stmt->execute();
        $existingOrder = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    $orderAddress = parseJsonField($existingOrder['address'] ?? null);
    $destination = getOrderAddressCoordinates($orderAddress);
    $start = getStoreLocation($conn, $storeId);

    if (!$destination) {
        $destination = ['lat' => $start['lat'] + 0.0125, 'lng' => $start['lng'] - 0.0130];
    }

    $distance = haversineDistance($start['lat'], $start['lng'], $destination['lat'], $destination['lng']);
    $duration = max(180, min(900, intval(round($distance * 120 + 240))));

    $trackingData = [
        'status' => 'on_delivery',
        'stage' => 'Kurir Menuju Lokasi',
        'store_id' => $storeId,
        'start' => $start,
        'destination' => $destination,
        'current_location' => $start,
        'progress' => 0,
        'started_at' => time(),
        'duration_seconds' => $duration,
        'distance_km' => round($distance, 2),
        'route' => [$start, $destination]
    ];
    $trackingJson = json_encode($trackingData);
}

$fields = [];
$params = [];
$types = '';
if ($new_status) {
    $fields[] = 'status = ?';
    $params[] = &$mapped_status;
    $types .= 's';
}
if ($payment_status) {
    $fields[] = 'payment_status = ?';
    $params[] = &$payment_status;
    $types .= 's';
}
if ($trackingJson !== null && $trackingColumnExists) {
    $fields[] = 'tracking_data = ?';
    $params[] = &$trackingJson;
    $types .= 's';
}
$fields[] = 'updated_at = CURRENT_TIMESTAMP';

$sql = "UPDATE orders SET " . implode(', ', $fields) . " WHERE order_id = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
    exit;
}

// Authorization: if not admin, ensure the order belongs to the session user
if (!$isAdmin) {
    $checkStmt = $conn->prepare('SELECT user_id FROM orders WHERE order_id = ? LIMIT 1');
    if ($checkStmt) {
        $checkStmt->bind_param('s', $order_id);
        $checkStmt->execute();
        $res = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();
        if (!$res || intval($res['user_id']) !== intval($sessionUserId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
        exit;
    }
}

$params[] = &$order_id;
$types .= 's';
array_unshift($params, $types);

call_user_func_array([$stmt, 'bind_param'], $params);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Update failed: ' . $stmt->error]);
    exit;
}

$stmt->close();

echo json_encode(['ok' => true, 'message' => 'Order status updated', 'new_status' => $mapped_status]);
exit;
