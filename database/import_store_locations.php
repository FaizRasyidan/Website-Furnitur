<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['admin_name'])) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function redirectBack() {
    header('Location: ../admin-page/store_locations.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'commit_import') {
    $preview = $_SESSION['store_import_preview'] ?? [];
    $inserted = 0;
    $errors = [];

    if (empty($preview)) {
        $_SESSION['store_import_errors'] = ['Tidak ada data preview untuk diimport.'];
        redirectBack();
    }

    foreach ($preview as $index => $row) {
        if (empty($row['valid'])) {
            continue;
        }

        $stmt = $conn->prepare('INSERT INTO store_locations (name,address,latitude,longitude) VALUES (?, ?, ?, ?)');
        if (!$stmt) {
            $errors[] = 'DB prepare error di baris #' . ($index + 1) . ': ' . $conn->error;
            continue;
        }

        $stmt->bind_param('ssdd', $row['name'], $row['address'], $row['latitude'], $row['longitude']);
        if ($stmt->execute()) {
            $inserted++;
        } else {
            $errors[] = 'DB error di baris #' . ($index + 1) . ': ' . $stmt->error;
        }
        $stmt->close();
    }

    unset($_SESSION['store_import_preview']);
    $_SESSION['store_import_count'] = $inserted;
    if (!empty($errors)) {
        $_SESSION['store_import_errors'] = $errors;
    }

    redirectBack();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csvfile'])) {
    redirectBack();
}

$file = $_FILES['csvfile'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['store_import_errors'] = ['Gagal mengupload file.'];
    redirectBack();
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    $_SESSION['store_import_errors'] = ['File harus berekstensi .csv'];
    redirectBack();
}

$fh = fopen($file['tmp_name'], 'r');
if (!$fh) {
    $_SESSION['store_import_errors'] = ['Tidak bisa membuka file.'];
    redirectBack();
}

$header = fgetcsv($fh);
if ($header === false) {
    $_SESSION['store_import_errors'] = ['CSV kosong atau tidak valid.'];
    fclose($fh);
    redirectBack();
}

$expected = ['name', 'address', 'latitude', 'longitude'];
$map = [];
foreach ($header as $i => $h) {
    $key = strtolower(trim($h));
    if ($key !== '') {
        $map[$key] = $i;
    }
}

foreach ($expected as $e) {
    if (!isset($map[$e])) {
        $_SESSION['store_import_errors'] = ['Header CSV harus berisi setidaknya: name,address,latitude,longitude'];
        fclose($fh);
        redirectBack();
    }
}

$preview = [];
$errors = [];
$rowNumber = 1;
$maxRows = 1000;
while (($row = fgetcsv($fh)) !== false) {
    $rowNumber++;
    if ($rowNumber > $maxRows + 1) {
        $errors[] = 'Maksimum ' . $maxRows . ' baris diperbolehkan dalam satu import.';
        break;
    }

    $name = isset($row[$map['name']]) ? trim($row[$map['name']]) : '';
    $address = isset($row[$map['address']]) ? trim($row[$map['address']]) : '';
    $latitude = isset($row[$map['latitude']]) ? trim($row[$map['latitude']]) : '';
    $longitude = isset($row[$map['longitude']]) ? trim($row[$map['longitude']]) : '';

    $rowErrors = [];
    if ($name === '') {
        $rowErrors[] = 'Nama kosong';
    } elseif (mb_strlen($name) > 150) {
        $rowErrors[] = 'Nama terlalu panjang (maks 150 karakter)';
    }

    if ($latitude === '' || $longitude === '') {
        $rowErrors[] = 'Latitude/longitude kosong';
    } elseif (!is_numeric($latitude) || !is_numeric($longitude)) {
        $rowErrors[] = 'Koordinat harus angka';
    } else {
        $lat = floatval($latitude);
        $lng = floatval($longitude);
        if ($lat < -90 || $lat > 90) {
            $rowErrors[] = 'Latitude di luar kisaran -90..90';
        }
        if ($lng < -180 || $lng > 180) {
            $rowErrors[] = 'Longitude di luar kisaran -180..180';
        }
    }

    $preview[] = [
        'name' => $name,
        'address' => $address,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'valid' => empty($rowErrors),
        'errors' => $rowErrors,
        'row_number' => $rowNumber,
    ];
}

fclose($fh);

$_SESSION['store_import_preview'] = $preview;
$_SESSION['store_import_errors'] = array_merge($_SESSION['store_import_errors'] ?? [], $errors);
redirectBack();
