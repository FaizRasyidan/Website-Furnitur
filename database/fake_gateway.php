<?php
header('Content-Type: application/json');

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

$orderId = trim($input['order_id'] ?? '');
$gross_amount = isset($input['gross_amount']) ? intval($input['gross_amount']) : 0;
$paymentMethod = trim($input['payment_method'] ?? 'Bank Transfer');
$customerDetails = $input['customer_details'] ?? [];

if ($orderId === '' || $gross_amount <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Order ID dan gross_amount diperlukan']);
    exit;
}

$email = trim($customerDetails['email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'customer_details.email format is invalid']);
    exit;
}

$paymentToken = 'FAKE-' . preg_replace('/[^A-Za-z0-9_-]/', '', $orderId) . '-' . time();
$expiresAt = date('c', time() + 15 * 60);

$response = [
    'payment_token' => $paymentToken,
    'payment_gateway' => 'OffoPay',
    'order_id' => $orderId,
    'gross_amount' => $gross_amount,
    'expires_at' => $expiresAt,
    'payment_method' => $paymentMethod,
    'customer_details' => [
        'first_name' => $customerDetails['first_name'] ?? '',
        'last_name' => $customerDetails['last_name'] ?? '',
        'email' => $email,
        'phone' => trim($customerDetails['phone'] ?? ''),
    ],
    'instructions' => [
        'Selesaikan pembayaran dengan menekan tombol Bayar Sekarang.',
        'Jika anda memilih transfer bank, ikuti instruksi yang muncul di layar.',
        'Transaksi akan otomatis dicatat setelah pembayaran selesai.'
    ]
];

echo json_encode($response);
