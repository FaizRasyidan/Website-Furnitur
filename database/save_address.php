<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$label = trim($_POST['label'] ?? 'Rumah');
$recipient_name = trim($_POST['recipient_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$province = trim($_POST['province'] ?? '');
$city = trim($_POST['city'] ?? '');
$district = trim($_POST['district'] ?? '');
$postal_code = trim($_POST['postal_code'] ?? '');
$address_detail = trim($_POST['address'] ?? '');
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;
$is_primary = isset($_POST['is_primary']) ? 1 : 0;
$editId = isset($_POST['address_id']) ? intval($_POST['address_id']) : 0;

$errors = [];
if ($recipient_name === '') $errors[] = 'Nama penerima harus diisi.';
if ($phone === '') $errors[] = 'Nomor telepon harus diisi.';
if ($address_detail === '') $errors[] = 'Alamat lengkap harus diisi.';
if ($latitude === null || $longitude === null || $latitude === '' || $longitude === '') $errors[] = 'Koordinat (latitude/longitude) harus dipilih dari peta.';

// validate numeric ranges
if ($latitude !== null && $longitude !== null && $latitude !== '' && $longitude !== '') {
    if (!is_numeric($latitude) || !is_numeric($longitude)) {
        $errors[] = 'Koordinat tidak valid.';
    } else {
        $latf = floatval($latitude);
        $lngf = floatval($longitude);
        if ($latf < -90 || $latf > 90 || $lngf < -180 || $lngf > 180) {
            $errors[] = 'Koordinat berada di luar jangkauan.';
        }
    }
}

if (!empty($errors)) {
    // store errors in session and redirect back
    $_SESSION['address_errors'] = $errors;
    $_SESSION['address_form'] = [
        'label' => $label,
        'recipient_name' => $recipient_name,
        'phone' => $phone,
        'province' => $province,
        'city' => $city,
        'district' => $district,
        'postal_code' => $postal_code,
        'address' => $address_detail,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'is_primary' => $is_primary,
    ];
    header('Location: ../user-page/addresses.php');
    exit;
}

// if marking primary, clear others
if ($is_primary) {
    $stmt = $conn->prepare("UPDATE user_addresses SET is_primary = 0 WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}

if ($editId > 0) {
    $stmt = $conn->prepare("UPDATE user_addresses SET label=?, recipient_name=?, phone=?, province=?, city=?, district=?, postal_code=?, address=?, latitude=?, longitude=?, is_primary=? WHERE id=? AND user_id=?");
    $stmt->bind_param('ssssssssddiii', $label, $recipient_name, $phone, $province, $city, $district, $postal_code, $address_detail, $latitude, $longitude, $is_primary, $editId, $userId);
    if (!$stmt->execute()) {
        $_SESSION['address_errors'] = ['Gagal memperbarui alamat: ' . $stmt->error];
    } else {
        $_SESSION['address_success'] = 'Alamat berhasil diperbarui.';
    }
    $stmt->close();
} else {
    $stmt = $conn->prepare("INSERT INTO user_addresses (user_id,label,recipient_name,phone,province,city,district,postal_code,address,latitude,longitude,is_primary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('issssssssddi', $userId, $label, $recipient_name, $phone, $province, $city, $district, $postal_code, $address_detail, $latitude, $longitude, $is_primary);
    if (!$stmt->execute()) {
        $_SESSION['address_errors'] = ['Gagal menyimpan alamat: ' . $stmt->error];
    } else {
        $_SESSION['address_success'] = 'Alamat berhasil ditambahkan.';
    }
    $stmt->close();
}

header('Location: ../user-page/addresses.php?saved=1');
exit;
