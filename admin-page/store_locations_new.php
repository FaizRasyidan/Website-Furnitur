<?php
session_start();
require_once __DIR__ . '/../database/config.php';
if(!isset($_SESSION['admin_name'])){ header('location:index.php'); exit(); }
$admin = $_SESSION['admin_name'];

// Ensure table exists
$sql = "CREATE TABLE IF NOT EXISTS store_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

$errors = [];
$success = null;
$edit = false;
$editId = 0;
$form = ['name'=>'','phone'=>'','address'=>'','latitude'=>'','longitude'=>''];

if (isset($_GET['clear_import_preview'])) {
    unset($_SESSION['store_import_preview']);
    header('Location: store_locations_new.php');
    exit();
}

$importPreview = $_SESSION['store_import_preview'] ?? null;
$importErrors = $_SESSION['store_import_errors'] ?? null;
$importCount = $_SESSION['store_import_count'] ?? null;
unset($_SESSION['store_import_preview'], $_SESSION['store_import_errors'], $_SESSION['store_import_count']);

// Handle delete
if (isset($_GET['delete'])) {
    $delId = intval($_GET['delete']);
    $stmt = $conn->prepare('DELETE FROM store_locations WHERE id = ?');
    $stmt->bind_param('i', $delId);
    $stmt->execute();
    $stmt->close();
    header('Location: store_locations_new.php?deleted=1');
    exit();
}

// Handle edit load
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $conn->prepare('SELECT * FROM store_locations WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $edit = true;
        $form = ['name'=>$row['name'],'phone'=>$row['phone'],'address'=>$row['address'],'latitude'=>$row['latitude'],'longitude'=>$row['longitude']];
    }
    $stmt->close();
}

// Handle save (create/update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $latitude = $_POST['latitude'] ?? '';
    $longitude = $_POST['longitude'] ?? '';
    $editId = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($name === '') {
        $errors[] = 'Nama toko/gudang harus diisi.';
    } elseif (mb_strlen($name) > 150) {
        $errors[] = 'Nama toko/gudang terlalu panjang (maks 150 karakter).';
    }
    if ($latitude === '' || $longitude === '') {
        $errors[] = 'Koordinat harus dipilih di peta.';
    } elseif (!is_numeric($latitude) || !is_numeric($longitude)) {
        $errors[] = 'Koordinat tidak valid.';
    } else {
        $latValue = floatval($latitude);
        $lngValue = floatval($longitude);
        if ($latValue < -90 || $latValue > 90) {
            $errors[] = 'Latitude berada di luar kisaran -90..90.';
        }
        if ($lngValue < -180 || $lngValue > 180) {
            $errors[] = 'Longitude berada di luar kisaran -180..180.';
        }
    }
    if (mb_strlen($address) > 1000) {
        $errors[] = 'Alamat terlalu panjang (maks 1000 karakter).';
    }

    if (empty($errors)) {
        if ($editId > 0) {
            $stmt = $conn->prepare('UPDATE store_locations SET name=?, phone=?, address=?, latitude=?, longitude=? WHERE id=?');
            $stmt->bind_param('ssdddi', $name, $phone, $address, $latitude, $longitude, $editId);
            if ($stmt->execute()) $success = 'Lokasi berhasil diperbarui.'; else $errors[] = 'Gagal memperbarui: '.$stmt->error;
            $stmt->close();
        } else {
            $stmt = $conn->prepare('INSERT INTO store_locations (name,phone,address,latitude,longitude) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('ssddd', $name, $phone, $address, $latitude, $longitude);
            if ($stmt->execute()) $success = 'Lokasi berhasil ditambahkan.'; else $errors[] = 'Gagal menyimpan: '.$stmt->error;
            $stmt->close();
        }
        if ($success) {
            header('Location: store_locations_new.php?saved=1');
            exit();
        }
    }
    $form = ['name'=>$name,'phone'=>$phone,'address'=>$address,'latitude'=>$latitude,'longitude'=>$longitude];
}

if (isset($_GET['saved'])) $success = 'Perubahan tersimpan.';
if (isset($_GET['deleted'])) $success = 'Lokasi dihapus.';

// Fetch all locations
$locations = [];
$res = $conn->query('SELECT * FROM store_locations ORDER BY id DESC');
if ($res) while ($r = $res->fetch_assoc()) $locations[] = $r;
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Lokasi Toko/Gudang - Admin</title>
  <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet" />
  <link href="../css/admin.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <style>
    :root {
      --primary: #3b5d50;
      --secondary: #5f6d7a;
      --light: #f7faf9;
      --border: #e6e6e6;
      --shadow: 0 2px 8px rgba(0,0,0,0.08);
      --shadow-md: 0 4px 16px rgba(0,0,0,0.12);
    }

    * { box-sizing: border-box; }
    
    /* ===== HEADER SECTION ===== */
    .store-header {
      background: linear-gradient(135deg, var(--primary) 0%, #2d4840 100%);
      color: white;
      padding: 40px 0;
      margin-bottom: 40px;
      box-shadow: var(--shadow-md);
    }

    .store-header h1 {
      font-size: 32px;
      font-weight: 700;
      margin: 0 0 8px 0;
      letter-spacing: -0.5px;
    }

    .store-header p {
      font-size: 15px;
      opacity: 0.9;
      margin: 0;
      line-height: 1.5;
    }

    /* ===== MAIN CONTAINER ===== */
    .store-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
    }

    /* ===== FORM SECTION ===== */
    .store-form-section {
      background: white;
      border-radius: 20px;
      box-shadow: var(--shadow-md);
      padding: 40px;
      margin-bottom: 40px;
      border: 1px solid #f0f0f0;
    }

    .store-form-title {
      font-size: 24px;
      font-weight: 700;
      margin: 0 0 12px 0;
      color: #1a1a1a;
    }

    .store-form-desc {
      color: var(--secondary);
      font-size: 14px;
      margin: 0 0 30px 0;
      line-height: 1.6;
    }

    /* ===== FORM GRID ===== */
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
      margin-bottom: 24px;
    }

    .form-group {
      display: flex;
      flex-direction: column;
    }

    .form-label {
      font-weight: 600;
      font-size: 14px;
      margin-bottom: 8px;
      color: #1a1a1a;
    }

    .form-control {
      padding: 12px 14px;
      border: 1px solid var(--border);
      border-radius: 12px;
      font-size: 14px;
      font-family: inherit;
      transition: all 0.3s ease;
      background: white;
    }

    .form-control:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(59, 93, 80, 0.1);
    }

    textarea.form-control {
      resize: vertical;
      min-height: 80px;
    }

    /* ===== SEARCH BOX ===== */
    .search-location-wrapper {
      grid-column: 1 / -1;
    }

    .search-location-container {
      position: relative;
    }

    .search-location-input {
      width: 100%;
      padding: 12px 14px;
      border: 1px solid var(--border);
      border-radius: 12px;
      font-size: 14px;
      transition: all 0.3s ease;
    }

    .search-location-input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(59, 93, 80, 0.1);
    }

    .autocomplete-results {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid var(--border);
      border-top: none;
      border-radius: 0 0 12px 12px;
      max-height: 300px;
      overflow-y: auto;
      display: none;
      z-index: 1000;
      box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    }

    .autocomplete-results.show {
      display: block;
    }

    .autocomplete-item {
      padding: 12px 14px;
      border-bottom: 1px solid #f0f0f0;
      cursor: pointer;
      font-size: 13px;
      color: var(--secondary);
      transition: background 0.2s;
    }

    .autocomplete-item:last-child {
      border-bottom: none;
    }

    .autocomplete-item:hover {
      background: var(--light);
      color: var(--primary);
    }

    /* ===== LOCATION BUTTONS ===== */
    .location-buttons {
      grid-column: 1 / -1;
      display: grid;
      grid-template-columns: auto 1fr;
      gap: 12px;
      margin-bottom: 24px;
    }

    /* ===== MAP CONTAINER ===== */
    .map-container {
      grid-column: 1 / -1;
      position: relative;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 2px 12px rgba(0,0,0,0.1);
      margin-bottom: 24px;
    }

    #adminMap {
      width: 100%;
      height: 400px;
      border-radius: 16px;
    }

    .map-info {
      position: absolute;
      top: 12px;
      left: 12px;
      background: white;
      padding: 8px 12px;
      border-radius: 8px;
      font-size: 12px;
      color: var(--secondary);
      box-shadow: var(--shadow);
      z-index: 400;
      pointer-events: none;
    }

    /* ===== FORM BUTTONS ===== */
    .form-actions {
      grid-column: 1 / -1;
      display: flex;
      gap: 12px;
      justify-content: flex-start;
      padding-top: 20px;
      border-top: 1px solid var(--border);
    }

    .btn {
      padding: 12px 24px;
      border: none;
      border-radius: 12px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      text-align: center;
    }

    .btn-primary {
      background: var(--primary);
      color: white;
    }

    .btn-primary:hover {
      background: #2d4840;
      box-shadow: 0 4px 12px rgba(59, 93, 80, 0.3);
    }

    .btn-secondary {
      background: white;
      color: var(--secondary);
      border: 1px solid var(--border);
    }

    .btn-secondary:hover {
      background: var(--light);
      border-color: var(--primary);
    }

    /* ===== LOCATIONS LIST ===== */
    .locations-list-section {
      background: white;
      border-radius: 20px;
      box-shadow: var(--shadow-md);
      padding: 40px;
      border: 1px solid #f0f0f0;
    }

    .locations-list-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 30px;
    }

    .locations-list-title {
      font-size: 24px;
      font-weight: 700;
      margin: 0 0 12px 0;
      color: #1a1a1a;
    }

    .locations-list-desc {
      color: var(--secondary);
      font-size: 14px;
      margin: 0;
      line-height: 1.6;
    }

    /* ===== LOCATION CARD ===== */
    .location-items {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 16px;
      margin-bottom: 30px;
    }

    .location-card {
      background: white;
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 20px;
      transition: all 0.3s ease;
      position: relative;
    }

    .location-card:hover {
      border-color: var(--primary);
      box-shadow: var(--shadow-md);
      transform: translateY(-2px);
    }

    .location-card-header {
      display: flex;
      justify-content: space-between;
      align-items: start;
      margin-bottom: 12px;
    }

    .location-card-title {
      font-size: 16px;
      font-weight: 700;
      color: #1a1a1a;
      margin: 0;
    }

    .location-card-phone {
      font-size: 13px;
      color: var(--secondary);
      margin: 4px 0 8px 0;
    }

    .location-card-address {
      font-size: 13px;
      color: var(--secondary);
      margin: 0;
      line-height: 1.5;
    }

    .location-card-coords {
      font-size: 11px;
      color: #999;
      margin-top: 8px;
      padding-top: 8px;
      border-top: 1px solid #f0f0f0;
    }

    .location-card-actions {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
      margin-top: 16px;
      padding-top: 16px;
      border-top: 1px solid var(--border);
    }

    .location-card-actions a,
    .location-card-actions button {
      padding: 8px 12px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
      text-align: center;
      background: white;
      color: var(--secondary);
    }

    .location-card-actions a:hover,
    .location-card-actions button:hover {
      border-color: var(--primary);
      color: var(--primary);
      background: var(--light);
    }

    .location-card-actions .edit-btn {
      color: var(--primary);
      border-color: var(--primary);
    }

    .location-card-actions .delete-btn {
      color: #d9534f;
      border-color: #d9534f;
    }

    .location-card-actions .delete-btn:hover {
      background: #fff5f5;
    }

    /* ===== IMPORT SECTION ===== */
    .import-section {
      background: #f9f9f9;
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 20px;
      margin-bottom: 30px;
    }

    .import-title {
      font-weight: 700;
      margin-bottom: 16px;
      font-size: 14px;
    }

    .import-form {
      display: flex;
      gap: 12px;
      align-items: center;
      flex-wrap: wrap;
    }

    .file-input-wrapper {
      position: relative;
      overflow: hidden;
      display: inline-block;
    }

    .file-input-wrapper input[type="file"] {
      position: absolute;
      left: -9999px;
    }

    .file-input-label {
      display: inline-block;
      padding: 8px 12px;
      background: white;
      border: 1px solid var(--border);
      border-radius: 8px;
      cursor: pointer;
      font-size: 13px;
      transition: all 0.2s;
    }

    .file-input-label:hover {
      border-color: var(--primary);
      color: var(--primary);
    }

    /* ===== ALERTS ===== */
    .alert {
      padding: 14px 16px;
      border-radius: 12px;
      margin-bottom: 24px;
      font-size: 14px;
      border: 1px solid;
      animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .alert-success {
      background: #f0fdf4;
      color: #166534;
      border-color: #86efac;
    }

    .alert-danger {
      background: #fef2f2;
      color: #991b1b;
      border-color: #fca5a5;
    }

    .alert-warning {
      background: #fffbeb;
      color: #92400e;
      border-color: #fcd34d;
    }

    /* ===== EMPTY STATE ===== */
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: var(--secondary);
    }

    .empty-state-icon {
      font-size: 48px;
      margin-bottom: 16px;
      opacity: 0.5;
    }

    .empty-state-title {
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 8px;
    }

    /* ===== RESPONSIVE DESIGN ===== */
    @media (max-width: 768px) {
      .store-header {
        padding: 30px 0;
        margin-bottom: 30px;
      }

      .store-header h1 {
        font-size: 24px;
      }

      .store-header p {
        font-size: 13px;
      }

      .store-container {
        padding: 0 16px;
      }

      .store-form-section,
      .locations-list-section {
        padding: 24px;
      }

      .form-grid {
        grid-template-columns: 1fr;
        gap: 16px;
      }

      .form-grid .form-group {
        grid-column: auto;
      }

      .location-buttons {
        grid-template-columns: 1fr;
      }

      .location-buttons .btn {
        width: 100%;
        justify-content: center;
      }

      #adminMap {
        height: 300px;
      }

      .location-items {
        grid-template-columns: 1fr;
      }

      .location-card-actions {
        grid-template-columns: 1fr;
      }

      .locations-list-header {
        flex-direction: column;
        gap: 16px;
      }

      .import-form {
        flex-direction: column;
        align-items: stretch;
      }

      .import-form .btn,
      .file-input-label {
        width: 100%;
        justify-content: center;
      }

      .form-actions {
        flex-direction: column;
      }

      .form-actions .btn {
        width: 100%;
        justify-content: center;
      }
    }

    /* ===== SCROLLBAR ===== */
    .autocomplete-results::-webkit-scrollbar {
      width: 6px;
    }

    .autocomplete-results::-webkit-scrollbar-track {
      background: transparent;
    }

    .autocomplete-results::-webkit-scrollbar-thumb {
      background: #ccc;
      border-radius: 3px;
    }

    .autocomplete-results::-webkit-scrollbar-thumb:hover {
      background: #999;
    }

    /* ===== PREVIEW TABLE ===== */
    .preview-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }

    .preview-table th {
      text-align: left;
      padding: 10px;
      background: var(--light);
      border-bottom: 1px solid var(--border);
      font-weight: 600;
      color: var(--secondary);
    }

    .preview-table td {
      padding: 10px;
      border-bottom: 1px solid #f0f0f0;
    }

    .preview-table tr.valid { background: #f0fdf4; }
    .preview-table tr.invalid { background: #fef2f2; }
  </style>
</head>
<body>
<?php include __DIR__ . '/admin_sidebar.php'; ?>
<div class="main-content">
  <!-- HEADER -->
  <div class="store-header">
    <div class="store-container">
      <h1><?php echo $edit ? 'Edit Lokasi Toko/Gudang' : 'Tambah Lokasi Toko/Gudang'; ?></h1>
      <p>Tentukan alamat dan titik lokasi toko/gudang untuk kebutuhan pengiriman. Sistem akan otomatis mengisi koordinat dari peta.</p>
    </div>
  </div>

  <!-- MAIN CONTAINER -->
  <div class="store-container">
    <!-- ALERTS -->
    <?php if ($success): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
      </div>
    <?php endif; ?>

    <!-- FORM SECTION -->
    <div class="store-form-section">
      <h2 class="store-form-title"><?php echo $edit ? 'Edit Lokasi' : 'Informasi Lokasi'; ?></h2>
      <p class="store-form-desc">Isi semua informasi lokasi dan pilih titik di peta. Koordinat akan otomatis tersimpan.</p>

      <form method="post" enctype="multipart/form-data" id="storeForm">
        <?php if ($edit): ?>
          <input type="hidden" name="id" value="<?php echo intval($editId); ?>" />
        <?php endif; ?>

        <div class="form-grid">
          <!-- Nama Toko -->
          <div class="form-group">
            <label class="form-label">
              <i class="fas fa-store"></i> Nama Toko/Gudang
            </label>
            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($form['name']); ?>" placeholder="Contoh: Toko Pusat Jakarta" required />
          </div>

          <!-- Nomor Telepon -->
          <div class="form-group">
            <label class="form-label">
              <i class="fas fa-phone"></i> Nomor Telepon (Opsional)
            </label>
            <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($form['phone']); ?>" placeholder="Contoh: 081234567890" />
          </div>

          <!-- Alamat Lengkap -->
          <div class="form-group" style="grid-column: 1 / -1;">
            <label class="form-label">
              <i class="fas fa-map-marker-alt"></i> Alamat Lengkap
            </label>
            <textarea name="address" class="form-control" placeholder="Masukkan alamat lengkap toko/gudang" rows="4"><?php echo htmlspecialchars($form['address']); ?></textarea>
          </div>

          <!-- Search Lokasi -->
          <div class="form-group search-location-wrapper">
            <label class="form-label">
              <i class="fas fa-search"></i> Cari Lokasi / Alamat
            </label>
            <div class="search-location-container">
              <input type="text" id="searchInput" class="search-location-input" placeholder="Ketik alamat atau nama lokasi untuk dicari..." autocomplete="off" />
              <div id="autocompleteResults" class="autocomplete-results"></div>
            </div>
          </div>

          <!-- Location Buttons -->
          <div class="location-buttons">
            <button type="button" id="centerMyLocation" class="btn btn-secondary">
              <i class="fas fa-location-crosshairs"></i> Gunakan Lokasi Saya
            </button>
          </div>

          <!-- MAP CONTAINER -->
          <div class="map-container" style="grid-column: 1 / -1;">
            <div class="map-info">
              <i class="fas fa-info-circle"></i> Klik peta atau drag marker untuk memilih lokasi
            </div>
            <div id="adminMap"></div>
          </div>

          <!-- Hidden Inputs untuk Latitude/Longitude -->
          <input type="hidden" id="latInput" name="latitude" value="<?php echo htmlspecialchars($form['latitude']); ?>" />
          <input type="hidden" id="lngInput" name="longitude" value="<?php echo htmlspecialchars($form['longitude']); ?>" />

          <!-- Form Actions -->
          <div class="form-actions">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save"></i> <?php echo $edit ? 'Perbarui Lokasi' : 'Simpan Lokasi'; ?>
            </button>
            <a href="store_locations.php" class="btn btn-secondary">
              <i class="fas fa-times"></i> Batal
            </a>
          </div>
        </div>
      </form>
    </div>

    <!-- LOCATIONS LIST SECTION -->
    <div class="locations-list-section">
      <div class="locations-list-header">
        <div>
          <h2 class="locations-list-title">Daftar Lokasi Toko</h2>
          <p class="locations-list-desc">Kelola semua lokasi toko/gudang Anda di sini.</p>
        </div>
        <a href="../database/export_store_locations.php" class="btn btn-secondary">
          <i class="fas fa-download"></i> Export CSV
        </a>
      </div>

      <!-- IMPORT SECTION -->
      <div class="import-section">
        <div class="import-title"><i class="fas fa-upload"></i> Import dari CSV</div>
        <form id="importForm" method="post" action="../database/import_store_locations.php" enctype="multipart/form-data" class="import-form">
          <div class="file-input-wrapper">
            <label class="file-input-label">
              <i class="fas fa-file-csv"></i> Pilih File CSV
            </label>
            <input type="file" name="csvfile" accept=".csv" required />
          </div>
          <button type="submit" class="btn btn-secondary">
            <i class="fas fa-upload"></i> Upload & Preview
          </button>
        </form>
      </div>

      <!-- IMPORT PREVIEW -->
      <?php if (!empty($importPreview)): ?>
        <div class="alert alert-warning" style="margin-bottom: 30px;">
          <strong>Preview Import CSV</strong><br>
          Ditemukan <strong><?php echo count($importPreview); ?></strong> baris, 
          <strong><?php echo count(array_filter($importPreview, function($row){ return !$row['valid']; })); ?></strong> baris bermasalah
        </div>
        <div style="background: white; border: 1px solid var(--border); border-radius: 16px; padding: 20px; margin-bottom: 20px; overflow-x: auto;">
          <table class="preview-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Nama</th>
                <th>Latitude</th>
                <th>Longitude</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($importPreview as $row): ?>
                <tr class="<?php echo $row['valid'] ? 'valid' : 'invalid'; ?>">
                  <td><?php echo intval($row['row_number']); ?></td>
                  <td><?php echo htmlspecialchars($row['name']); ?></td>
                  <td><?php echo htmlspecialchars($row['latitude']); ?></td>
                  <td><?php echo htmlspecialchars($row['longitude']); ?></td>
                  <td>
                    <?php if ($row['valid']): ?>
                      <span style="color: #166534;"><i class="fas fa-check"></i> Valid</span>
                    <?php else: ?>
                      <span style="color: #991b1b;"><i class="fas fa-times"></i> Invalid: <?php echo htmlspecialchars(implode(', ', $row['errors'])); ?></span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="display: flex; gap: 12px; margin-bottom: 30px;">
          <form method="post" action="../database/import_store_locations.php" style="flex: 1;">
            <input type="hidden" name="action" value="commit_import" />
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
              <i class="fas fa-save"></i> Simpan Baris Valid
            </button>
          </form>
          <a href="store_locations_new.php?clear_import_preview=1" class="btn btn-secondary" style="flex: 1; text-align: center;">
            <i class="fas fa-times"></i> Batalkan
          </a>
        </div>
      <?php endif; ?>

      <!-- IMPORT SUCCESS MESSAGE -->
      <?php if ($importCount !== null): ?>
        <div class="alert alert-success">
          <i class="fas fa-check-circle"></i> Berhasil mengimpor <strong><?php echo intval($importCount); ?></strong> baris lokasi.
        </div>
      <?php endif; ?>

      <?php if (!empty($importErrors)): ?>
        <div class="alert alert-danger">
          <i class="fas fa-exclamation-circle"></i>
          <?php echo implode('<br>', array_map('htmlspecialchars', $importErrors)); ?>
        </div>
      <?php endif; ?>

      <!-- LOCATIONS GRID -->
      <?php if (count($locations) === 0): ?>
        <div class="empty-state">
          <div class="empty-state-icon"><i class="fas fa-store"></i></div>
          <div class="empty-state-title">Belum Ada Lokasi</div>
          <p>Mulai dengan menambahkan lokasi toko/gudang pertama Anda di atas.</p>
        </div>
      <?php else: ?>
        <div class="location-items">
          <?php foreach ($locations as $loc): ?>
            <div class="location-card">
              <div class="location-card-header">
                <div>
                  <h3 class="location-card-title"><?php echo htmlspecialchars($loc['name']); ?></h3>
                </div>
              </div>

              <?php if ($loc['phone']): ?>
                <p class="location-card-phone">
                  <i class="fas fa-phone"></i> <?php echo htmlspecialchars($loc['phone']); ?>
                </p>
              <?php endif; ?>

              <?php if ($loc['address']): ?>
                <p class="location-card-address"><?php echo nl2br(htmlspecialchars($loc['address'])); ?></p>
              <?php endif; ?>

              <div class="location-card-coords">
                <i class="fas fa-map-pin"></i> 
                <?php echo round($loc['latitude'], 5); ?>, <?php echo round($loc['longitude'], 5); ?>
              </div>

              <div class="location-card-actions">
                <a href="store_locations_new.php?edit=<?php echo $loc['id']; ?>" class="edit-btn">
                  <i class="fas fa-edit"></i> Edit
                </a>
                <a href="store_locations_new.php?delete=<?php echo $loc['id']; ?>" class="delete-btn" onclick="return confirm('Hapus lokasi ini?');">
                  <i class="fas fa-trash"></i> Hapus
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // ===== INITIALIZE MAP =====
  const map = L.map('adminMap').setView([-6.200000, 106.816666], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19
  }).addTo(map);

  let marker = null;

  // ===== SET MARKER FUNCTION =====
  function setMarker(lat, lng, draggable = true) {
    if (marker) marker.remove();
    marker = L.marker([lat, lng], { draggable: draggable }).addTo(map);
    marker.on('dragend', onMarkerMoved);
    map.setView([lat, lng], 16);
    
    document.getElementById('latInput').value = parseFloat(lat).toFixed(7);
    document.getElementById('lngInput').value = parseFloat(lng).toFixed(7);
    
    reverseGeocode(lat, lng);
  }

  // ===== ON MARKER MOVED =====
  function onMarkerMoved() {
    const pos = marker.getLatLng();
    document.getElementById('latInput').value = pos.lat.toFixed(7);
    document.getElementById('lngInput').value = pos.lng.toFixed(7);
    reverseGeocode(pos.lat, pos.lng);
  }

  // ===== MAP CLICK EVENT =====
  map.on('click', (e) => {
    setMarker(e.latlng.lat, e.latlng.lng);
  });

  // ===== REVERSE GEOCODE =====
  async function reverseGeocode(lat, lng) {
    try {
      const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}`;
      const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
      const data = await res.json();
      if (data && data.display_name) {
        const textarea = document.querySelector('textarea[name="address"]');
        if (textarea && !textarea.value.trim()) {
          textarea.value = data.display_name;
        }
      }
    } catch (err) {
      console.error('reverseGeocode error:', err);
    }
  }

  // ===== USE MY LOCATION =====
  document.getElementById('centerMyLocation').addEventListener('click', () => {
    if (!navigator.geolocation) {
      alert('Geolocation tidak didukung pada browser ini.');
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setMarker(pos.coords.latitude, pos.coords.longitude);
      },
      (err) => {
        alert('Gagal mendapatkan lokasi: ' + err.message);
      },
      { enableHighAccuracy: true, timeout: 10000 }
    );
  });

  // ===== AUTOCOMPLETE SEARCH =====
  const searchBox = document.getElementById('searchInput');
  const resultsList = document.getElementById('autocompleteResults');
  let acTimeout = null;

  searchBox.addEventListener('input', () => {
    const q = searchBox.value.trim();
    if (acTimeout) clearTimeout(acTimeout);

    if (!q) {
      resultsList.classList.remove('show');
      return;
    }

    acTimeout = setTimeout(async () => {
      try {
        const url = `https://nominatim.openstreetmap.org/search?format=jsonv2&q=${encodeURIComponent(q)}&addressdetails=1&limit=8`;
        const res = await fetch(url);
        const items = await res.json();

        resultsList.innerHTML = '';

        items.forEach(item => {
          const div = document.createElement('div');
          div.className = 'autocomplete-item';
          div.textContent = item.display_name;
          div.style.cursor = 'pointer';

          div.addEventListener('click', () => {
            resultsList.classList.remove('show');
            searchBox.value = item.display_name;
            setMarker(parseFloat(item.lat), parseFloat(item.lon));
            document.querySelector('textarea[name="address"]').value = item.display_name;
          });

          resultsList.appendChild(div);
        });

        if (items.length > 0) {
          resultsList.classList.add('show');
        } else {
          resultsList.classList.remove('show');
        }
      } catch (err) {
        console.error('Autocomplete error:', err);
      }
    }, 400);
  });

  // ===== CLOSE AUTOCOMPLETE =====
  document.addEventListener('click', (e) => {
    if (!searchBox.contains(e.target) && !resultsList.contains(e.target)) {
      resultsList.classList.remove('show');
    }
  });

  // ===== FORM VALIDATION =====
  document.getElementById('storeForm').addEventListener('submit', function(e) {
    const lat = document.getElementById('latInput').value.trim();
    const lng = document.getElementById('lngInput').value.trim();
    const name = document.querySelector('input[name="name"]').value.trim();

    if (!name) {
      e.preventDefault();
      alert('Nama toko/gudang harus diisi.');
      return false;
    }

    if (!lat || !lng) {
      e.preventDefault();
      alert('Silakan pilih titik di peta atau gunakan "Gunakan Lokasi Saya" sebelum menyimpan.');
      return false;
    }

    // Additional validation
    if (isNaN(parseFloat(lat)) || isNaN(parseFloat(lng))) {
      e.preventDefault();
      alert('Koordinat tidak valid.');
      return false;
    }
  });

  // ===== IF EDITING, SHOW EXISTING MARKER =====
  const latVal = document.getElementById('latInput').value.trim();
  const lngVal = document.getElementById('lngInput').value.trim();
  if (latVal && lngVal) {
    setMarker(parseFloat(latVal), parseFloat(lngVal));
  }
});
</script>

</body>
</html>
