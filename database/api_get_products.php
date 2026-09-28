<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$category = isset($_GET['category']) ? $_GET['category'] : null;

$sql = "SELECT id, name, category_id, price, image FROM products";

if ($category) {
    $sql .= " WHERE category_id = " . intval($category);
}

$sql .= " ORDER BY created_at DESC LIMIT " . $limit;

$result = $conn->query($sql);
$products = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

echo json_encode(['products' => $products, 'success' => true]);
?>
