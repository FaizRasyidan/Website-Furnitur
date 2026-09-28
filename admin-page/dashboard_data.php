<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../database/config.php';

if (!isset($_SESSION['admin_name'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$currentMonth = intval(date('m'));
$ordersExist = false;

$result = $conn->query("SHOW TABLES LIKE 'orders'");
if ($result && $result->num_rows > 0) {
    $ordersExist = true;
}

$monthNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
$monthlyOrders = array_fill(1, 12, 0);
$monthlyRevenue = array_fill(1, 12, 0);
$daysInMonth = intval(date('t'));
$dailyOrders = array_fill(1, $daysInMonth, 0);
$dailyRevenue = array_fill(1, $daysInMonth, 0);
$topProducts = [];
$totalOrders = 0;
$totalRevenue = 0;
$paymentMethods = [];
$activeOrders = 0;

if ($ordersExist) {
    $stmt = $conn->prepare("SELECT MONTH(timestamp) AS month_num, COUNT(*) AS order_count, COALESCE(SUM(total),0) AS revenue FROM orders WHERE YEAR(timestamp) = ? GROUP BY month_num");
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $month = intval($row['month_num']);
        $monthlyOrders[$month] = intval($row['order_count']);
        $monthlyRevenue[$month] = intval($row['revenue']);
        $totalOrders += intval($row['order_count']);
        $totalRevenue += intval($row['revenue']);
    }
    $stmt->close();

    $daysInMonth = intval(date('t'));
    foreach (range(1, $daysInMonth) as $day) {
        $dailyOrders[$day] = 0;
        $dailyRevenue[$day] = 0;
    }

    $stmt2 = $conn->prepare("SELECT DAY(timestamp) AS day_num, COUNT(*) AS order_count, COALESCE(SUM(total),0) AS revenue FROM orders WHERE YEAR(timestamp) = ? AND MONTH(timestamp) = ? GROUP BY day_num");
    $stmt2->bind_param('ii', $year, $currentMonth);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($row = $res2->fetch_assoc()) {
        $day = intval($row['day_num']);
        $dailyOrders[$day] = intval($row['order_count']);
        $dailyRevenue[$day] = intval($row['revenue']);
    }
    $stmt2->close();

    $paymentMethods = [];
    $paymentStmt = $conn->prepare("SELECT COALESCE(NULLIF(payment_method,''), 'Tidak ditentukan') AS method, COUNT(*) AS count FROM orders WHERE YEAR(timestamp) = ? GROUP BY method ORDER BY count DESC");
    $paymentStmt->bind_param('i', $year);
    $paymentStmt->execute();
    $paymentRes = $paymentStmt->get_result();
    while ($row = $paymentRes->fetch_assoc()) {
        $paymentMethods[] = [
            'method' => $row['method'],
            'count' => intval($row['count'])
        ];
    }
    $paymentStmt->close();

    $activeOrders = 0;
    $activeStmt = $conn->query("SELECT COUNT(*) AS count FROM orders WHERE status NOT IN ('completed', 'delivered', 'selesai', 'canceled', 'cancelled', 'rejected', 'ditolak')");
    if ($activeStmt) {
        $activeRow = $activeStmt->fetch_assoc();
        $activeOrders = intval($activeRow['count']);
    }

    $productStmt = $conn->query("SELECT items FROM orders WHERE YEAR(timestamp) = $year");
    while ($row = $productStmt->fetch_assoc()) {
        $items = json_decode($row['items'], true);
        if (!is_array($items)) {
            continue;
        }
        foreach ($items as $item) {
            $name = trim($item['name'] ?? 'Produk');
            $qty = intval($item['quantity'] ?? $item['qty'] ?? 1);
            if ($name === '') {
                $name = 'Produk';
            }
            if (!isset($topProducts[$name])) {
                $topProducts[$name] = 0;
            }
            $topProducts[$name] += $qty;
        }
    }

    arsort($topProducts);
}

$totalUsers = 0;
$totalProducts = 0;
$totalCategories = 0;
$recentUsers = [];

if ($conn) {
    $userResult = $conn->query('SELECT COUNT(*) AS count FROM users');
    if ($userResult) {
        $totalUsers = intval($userResult->fetch_assoc()['count']);
    }

    $productResult = $conn->query('SELECT COUNT(*) AS count FROM products');
    if ($productResult) {
        $totalProducts = intval($productResult->fetch_assoc()['count']);
    }

    $categoryResult = $conn->query('SELECT COUNT(*) AS count FROM categories');
    if ($categoryResult) {
        $totalCategories = intval($categoryResult->fetch_assoc()['count']);
    }

    $recentResult = $conn->query('SELECT id, name, email FROM users ORDER BY id DESC LIMIT 5');
    if ($recentResult) {
        while ($userRow = $recentResult->fetch_assoc()) {
            $recentUsers[] = [
                'id' => intval($userRow['id']),
                'name' => $userRow['name'],
                'email' => $userRow['email']
            ];
        }
    }
}

$topProductsList = [];
foreach ($topProducts as $name => $qty) {
    $topProductsList[] = ['name' => $name, 'quantity' => $qty];
}

$data = [
    'year' => $year,
    'ordersExist' => $ordersExist,
    'labels' => $monthNames,
    'monthlyOrders' => array_values($monthlyOrders),
    'monthlyRevenue' => array_values($monthlyRevenue),
    'dailyOrders' => array_values($dailyOrders),
    'dailyRevenue' => array_values($dailyRevenue),
    'topProducts' => array_slice($topProductsList, 0, 5),
    'paymentMethods' => $paymentMethods,
    'totalOrders' => $totalOrders,
    'totalRevenue' => $totalRevenue,
    'activeOrders' => $activeOrders,
    'totalUsers' => $totalUsers,
    'totalProducts' => $totalProducts,
    'totalCategories' => $totalCategories,
    'recentUsers' => $recentUsers
];

echo json_encode($data);
