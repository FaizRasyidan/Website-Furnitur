<?php
header('Content-Type: application/json');
session_start();
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

function getAddressCoordinates($address) {
    if (!is_array($address)) {
        return null;
    }

    $keys = ['latitude', 'lat', 'lat_value', 'longitude', 'lng', 'lng_value', 'lon', 'long'];
    $coords = ['lat' => null, 'lng' => null];

    foreach ($keys as $key) {
        if (isset($address[$key]) && is_numeric($address[$key])) {
            if (in_array($key, ['longitude', 'lng', 'lng_value', 'lon', 'long'])) {
                $coords['lng'] = floatval($address[$key]);
            } else {
                $coords['lat'] = floatval($address[$key]);
            }
        }
    }

    if ($coords['lat'] !== null && $coords['lng'] !== null) {
        return $coords;
    }

    if (!empty($address['coords']) && is_array($address['coords'])) {
        return getAddressCoordinates($address['coords']);
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

function buildStageLabel($progress) {
    if ($progress >= 1.0) {
        return 'Paket Diterima';
    }
    if ($progress >= 0.75) {
        return 'Hampir Sampai';
    }
    if ($progress >= 0.35) {
        return 'Paket Dikirim';
    }
    if ($progress >= 0.10) {
        return 'Kurir Menuju Lokasi';
    }
    return 'Pesanan Dikemas';
}

function buildTrackingResponse($order, $tracking) {
    $now = time();
    $startedAt = isset($tracking['started_at']) ? intval($tracking['started_at']) : $now;
    $durationSeconds = max(30, intval($tracking['duration_seconds'] ?? 300));
    $elapsed = max(0, $now - $startedAt);
    $progress = min(1, $elapsed / max(1, $durationSeconds));

    $start = $tracking['start'] ?? ['lat' => -6.2088, 'lng' => 106.8456];
    $destination = $tracking['destination'] ?? ['lat' => -6.1944, 'lng' => 106.8296];
    $currentLat = $start['lat'] + (($destination['lat'] - $start['lat']) * $progress);
    $currentLng = $start['lng'] + (($destination['lng'] - $start['lng']) * $progress);
    $stage = buildStageLabel($progress);
    $remaining = max(0, $durationSeconds - $elapsed);

    $tracking['current_location'] = ['lat' => $currentLat, 'lng' => $currentLng];
    $tracking['progress'] = round($progress, 4);
    $tracking['stage'] = $stage;
    $tracking['eta_seconds'] = intval($remaining);
    $tracking['distance_km'] = round(haversineDistance($start['lat'], $start['lng'], $destination['lat'], $destination['lng']), 2);
    $tracking['updated_at'] = $now;

    return $tracking;
}

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        sendError('Invalid JSON payload');
    }

    $action = trim($body['action'] ?? '');
    if ($action !== 'start_delivery') {
        sendError('Unsupported action');
    }

    $order_id = trim($body['order_id'] ?? '');
    if (!$order_id) {
        sendError('order_id is required');
    }

    $stmt = $conn->prepare('SELECT * FROM orders WHERE order_id = ? LIMIT 1');
    if (!$stmt) {
        sendError('Database error: ' . $conn->error, 500);
    }

    $stmt->bind_param('s', $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        sendError('Order tidak ditemukan', 404);
    }

    // Authorization: only admin or owner can start delivery for an order
    $isAdmin = isset($_SESSION['admin_name']);
    $sessionUserId = $_SESSION['user_id'] ?? null;
    if (!$isAdmin) {
        if ($sessionUserId === null || intval($order['user_id']) !== intval($sessionUserId)) {
            sendError('Access denied', 403);
        }
    }

    $orderAddress = parseJsonField($order['address']);
    $storedTracking = parseJsonField($order['tracking_data']);

    $orderStoreId = null;
    if (isset($body['store_id']) && is_numeric($body['store_id'])) {
        $orderStoreId = intval($body['store_id']);
    }

    if ($storedTracking && isset($storedTracking['started_at'])) {
        $current = buildTrackingResponse($order, $storedTracking);
        if ($current['progress'] < 1) {
            echo json_encode(['ok' => true, 'message' => 'Pengiriman sudah dimulai', 'tracking' => $current]);
            exit;
        }
    }

    $start = getStoreLocation($conn, $orderStoreId);
    $destination = getAddressCoordinates($orderAddress);

    if (!$destination) {
        $destination = ['lat' => $start['lat'] + 0.0125, 'lng' => $start['lng'] - 0.0130];
    }

    $distance = haversineDistance($start['lat'], $start['lng'], $destination['lat'], $destination['lng']);
    $duration = max(180, min(900, intval(round($distance * 120 + 240))));

    $trackingData = [
        'status' => 'on_delivery',
        'stage' => 'Kurir Menuju Lokasi',
        'store_id' => $orderStoreId,
        'start' => $start,
        'destination' => $destination,
        'current_location' => $start,
        'progress' => 0,
        'started_at' => time(),
        'duration_seconds' => $duration,
        'distance_km' => round($distance, 2),
        'route' => [$start, $destination],
        'history' => [
            ['lat' => $start['lat'], 'lng' => $start['lng'], 'recorded_at' => time()]
        ]
    ];

    $trackingJson = json_encode($trackingData);
    $updateStmt = $conn->prepare('UPDATE orders SET status = ?, tracking_data = ?, updated_at = CURRENT_TIMESTAMP WHERE order_id = ?');
    if (!$updateStmt) {
        sendError('Failed to prepare update statement: ' . $conn->error, 500);
    }

    $statusValue = 'on_delivery';
    $updateStmt->bind_param('sss', $statusValue, $trackingJson, $order_id);
    if (!$updateStmt->execute()) {
        sendError('Failed to update order tracking: ' . $updateStmt->error, 500);
    }
    $updateStmt->close();

    // Create optional tracking history record if table exists
    $exists = $conn->query("SHOW TABLES LIKE 'order_trackings'");
    if ($exists && $exists->num_rows > 0) {
        $historyStmt = $conn->prepare('INSERT INTO order_trackings (order_id, status, stage, progress, eta_seconds, notes) VALUES (?, ?, ?, ?, ?, ?)');
        if ($historyStmt) {
            $notes = 'Pengiriman dimulai.';
            $etaSeconds = intval($duration);
            $progressValue = 0.0;
            $stage = $trackingData['stage'] ?? '';
            // types: order_id(s), status(s), stage(s), progress(d), eta_seconds(i), notes(s)
            $historyStmt->bind_param('sssdis', $order_id, $statusValue, $stage, $progressValue, $etaSeconds, $notes);
            $historyStmt->execute();
            $historyStmt->close();
        }
    }

    echo json_encode(['ok' => true, 'message' => 'Pengiriman dimulai', 'tracking' => $trackingData]);
    exit;
}

$order_id = trim($_GET['order_id'] ?? '');
if (!$order_id) {
    sendError('order_id is required');
}

$stmt = $conn->prepare('SELECT * FROM orders WHERE order_id = ? LIMIT 1');
if (!$stmt) {
    sendError('Database error: ' . $conn->error, 500);
}

$stmt->bind_param('s', $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    sendError('Order tidak ditemukan', 404);
}

// Authorization: only admin or owner can view tracking for an order
$isAdmin = isset($_SESSION['admin_name']);
$sessionUserId = $_SESSION['user_id'] ?? null;
if (!$isAdmin) {
    if ($sessionUserId === null || intval($order['user_id']) !== intval($sessionUserId)) {
        sendError('Access denied', 403);
    }
}

$tracking = parseJsonField($order['tracking_data']);
if (!$tracking || !isset($tracking['started_at'])) {
    $storeId = isset($tracking['store_id']) && is_numeric($tracking['store_id']) ? intval($tracking['store_id']) : null;
    $store = getStoreLocation($conn, $storeId);
    $destination = getAddressCoordinates(parseJsonField($order['address']));
    if (!$destination) {
        $destination = ['lat' => $store['lat'] + 0.0125, 'lng' => $store['lng'] - 0.0130];
    }

    $trackingData = [
        'status' => $order['status'],
        'stage' => 'Pengiriman belum dimulai',
        'store_id' => $storeId,
        'start' => $store,
        'destination' => $destination,
        'current_location' => $store,
        'progress' => 0,
        'started_at' => null,
        'duration_seconds' => 0,
        'distance_km' => round(haversineDistance($store['lat'], $store['lng'], $destination['lat'], $destination['lng']), 2),
        'route' => [$store, $destination]
    ];

    echo json_encode([
        'ok' => true,
        'order_id' => $order_id,
        'status' => $order['status'],
        'tracking' => $trackingData,
        'address' => parseJsonField($order['address']),
        'store' => $store,
        'destination' => $destination
    ]);
    exit;
}

$trackingResponse = buildTrackingResponse($order, $tracking);
if ($trackingResponse['progress'] >= 1 && $order['status'] !== 'delivered') {
    $fixStmt = $conn->prepare('UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE order_id = ?');
    if ($fixStmt) {
        $doneStatus = 'delivered';
        $fixStmt->bind_param('ss', $doneStatus, $order_id);
        $fixStmt->execute();
        $fixStmt->close();
    }
}

echo json_encode([
    'ok' => true,
    'order_id' => $order_id,
    'status' => $trackingResponse['status'] ?? $order['status'],
    'tracking' => $trackingResponse,
    'address' => parseJsonField($order['address']),
    'delivery_personnel_id' => $order['delivery_personnel_id'] ?? null
]);
exit;
