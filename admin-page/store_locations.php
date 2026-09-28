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
    header('Location: store_locations.php');
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
    header('Location: store_locations.php?deleted=1');
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
            header('Location: store_locations.php?saved=1');
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
  <link href='https://cdn.jsdelivr.net/npm/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <!-- Styles moved to ../css/admin.css -->
</head>
<body>
<!-- SIDEBAR / NAVBAR -->
  <nav class="sidebar">
      <div class="logo">
          <i class='bx bxs-couch'></i>
          <span>Offo Living</span>
      </div>

      <div class="menu-section">
          <div class="menu-title">Menu Utama</div>
          <ul class="nav-links">
              <li class="nav-item">
                  <a class="nav-link" onclick="toggleSubmenu(this)" href="admin_page.php">
                      <i class='bx bxs-home'></i>
                      <span>Beranda</span>
                  </a>
              <!-- Menu Toko with Submenu -->
              <li class="nav-item">
                  <a class="nav-link" onclick="toggleSubmenu(this)">
                      <i class='bx bxs-store'></i>
                      <span>Toko</span>
                      <i class='bx bxs-chevron-down arrow'></i>
                  </a>
                  <ul class="submenu">
                      <li><a href="laporan_pemasukan.php">Laporan Pemasukan</a></li>
                      <li><a href="data_pesanan.php">Data Pesanan</a></li>
                      <li><a href="data_produk.php">Data Produk</a></li>
                      <li><a href="voucher.php">Voucher</a></li>
                      <li><a href="pengiriman.php">Pengiriman</a></li>
                  </ul>
              </li>

              <!-- Menu Blog with Submenu -->
              <li class="nav-item">
                  <a class="nav-link" onclick="toggleSubmenu(this)">
                      <i class='bx bxs-edit'></i>
                      <span>Blog</span>
                      <i class='bx bxs-chevron-down arrow'></i>
                  </a>
                  <ul class="submenu">
                      <li><a href="kategori_produk.php">Kategori Produk</a></li>
                      <li><a href="store_locations.php">Lokasi Toko</a></li>
                      <li><a href="pelanggan.php">Pelanggan</a></li>
                  </ul>
              </li>

              <!-- Menu Chat -->
              <li class="nav-item">
                  <a class="nav-link" href="chat.php">
                      <i class='bx bxs-message-rounded-dots'></i>
                      <span>Chat</span>
                  </a>
              </li>
              
              <li class="nav-item">
                  <a class="nav-link" href="../database/logout.php">
                      <i class='bx bxs-log-out'></i>
                      <span>Logout</span>
                  </a>
              </li>
          </ul>
      </div>
  </nav>
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
          <a href="store_locations.php?clear_import_preview=1" class="btn btn-secondary" style="flex: 1; text-align: center;">
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
                <a href="store_locations.php?edit=<?php echo $loc['id']; ?>" class="edit-btn">
                  <i class="fas fa-edit"></i> Edit
                </a>
                <a href="store_locations.php?delete=<?php echo $loc['id']; ?>" class="delete-btn" onclick="return confirm('Hapus lokasi ini?');">
                  <i class="fas fa-trash"></i> Hapus
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../js/dashboard.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const mapContainer = document.getElementById('adminMap');
  if (!mapContainer || typeof L === 'undefined') {
    return;
  }

  const map = L.map('adminMap').setView([-6.200000, 106.816666], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19
  }).addTo(map);

  let marker = null;
  const latInput = document.getElementById('latInput');
  const lngInput = document.getElementById('lngInput');
  const addressTextarea = document.querySelector('textarea[name="address"]');
  const searchBox = document.getElementById('searchInput');
  const resultsList = document.getElementById('autocompleteResults');

  function refreshMap() {
    if (map && map.invalidateSize) {
      map.invalidateSize();
    }
  }

  refreshMap();
  setTimeout(refreshMap, 500);
  setTimeout(refreshMap, 1000);
  window.addEventListener('resize', refreshMap);

  function setMarker(lat, lng, draggable = true) {
    if (marker) {
      marker.remove();
    }
    marker = L.marker([lat, lng], { draggable }).addTo(map);
    marker.on('dragend', onMarkerMoved);
    map.setView([lat, lng], 16);
    if (latInput) latInput.value = parseFloat(lat).toFixed(7);
    if (lngInput) lngInput.value = parseFloat(lng).toFixed(7);
    reverseGeocode(lat, lng);
  }

  function onMarkerMoved() {
    const pos = marker.getLatLng();
    if (latInput) latInput.value = pos.lat.toFixed(7);
    if (lngInput) lngInput.value = pos.lng.toFixed(7);
    reverseGeocode(pos.lat, pos.lng);
  }

  map.on('click', (e) => {
    setMarker(e.latlng.lat, e.latlng.lng);
  });

  async function reverseGeocode(lat, lng) {
    try {
      const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}`;
      const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
      const data = await res.json();
      if (data && data.display_name && addressTextarea && !addressTextarea.value.trim()) {
        addressTextarea.value = data.display_name;
      }
    } catch (err) {
      console.error('reverseGeocode error:', err);
    }
  }

  const centerBtn = document.getElementById('centerMyLocation');
  if (centerBtn) {
    centerBtn.addEventListener('click', () => {
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
  }

  if (searchBox && resultsList) {
    let acTimeout = null;

    function clearSearchResults() {
      resultsList.innerHTML = '';
      resultsList.classList.remove('show');
    }

    searchBox.addEventListener('input', () => {
      const q = searchBox.value.trim();
      if (acTimeout) clearTimeout(acTimeout);
      if (!q) {
        clearSearchResults();
        return;
      }

      acTimeout = setTimeout(async () => {
        try {
          const url = `https://nominatim.openstreetmap.org/search?format=jsonv2&q=${encodeURIComponent(q)}&addressdetails=1&limit=10&accept-language=id`;
          const res = await fetch(url);
          const items = await res.json();

          resultsList.innerHTML = '';
          if (!items || items.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'autocomplete-item';
            empty.textContent = 'Tidak ditemukan hasil. Coba alamat yang lebih spesifik.';
            empty.style.cursor = 'default';
            resultsList.appendChild(empty);
            resultsList.classList.add('show');
            return;
          }

          items.forEach((item) => {
            const div = document.createElement('div');
            div.className = 'autocomplete-item';
            div.textContent = item.display_name;
            div.style.cursor = 'pointer';
            div.addEventListener('click', () => {
              resultsList.classList.remove('show');
              searchBox.value = item.display_name;
              setMarker(parseFloat(item.lat), parseFloat(item.lon));
              if (addressTextarea) addressTextarea.value = item.display_name;
            });
            resultsList.appendChild(div);
          });

          if (items.length > 0) {
            resultsList.classList.add('show');
          }
        } catch (err) {
          console.error('Autocomplete error:', err);
        }
      }, 300);
    });

    searchBox.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        const firstItem = resultsList.querySelector('.autocomplete-item');
        if (firstItem && firstItem.style.cursor !== 'default') {
          event.preventDefault();
          firstItem.click();
        }
      }
    });

    document.addEventListener('click', (e) => {
      if (!searchBox.contains(e.target) && !resultsList.contains(e.target)) {
        resultsList.classList.remove('show');
      }
    });
  }

  const storeForm = document.getElementById('storeForm');
  if (storeForm) {
    storeForm.addEventListener('submit', (e) => {
      const lat = latInput ? latInput.value.trim() : '';
      const lng = lngInput ? lngInput.value.trim() : '';
      const name = document.querySelector('input[name="name"]') ? document.querySelector('input[name="name"]').value.trim() : '';
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
      if (isNaN(parseFloat(lat)) || isNaN(parseFloat(lng))) {
        e.preventDefault();
        alert('Koordinat tidak valid.');
        return false;
      }
    });
  }

  if (latInput && lngInput && latInput.value.trim() && lngInput.value.trim()) {
    setMarker(parseFloat(latInput.value), parseFloat(lngInput.value));
  }
});
</script>

</body>
</html>
