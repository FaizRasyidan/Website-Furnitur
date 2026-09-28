<?php
session_start();
require_once __DIR__ . '/../database/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Ensure address table exists
$sql = "CREATE TABLE IF NOT EXISTS user_addresses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  label VARCHAR(50) NOT NULL DEFAULT 'Rumah',
  recipient_name VARCHAR(100) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  province VARCHAR(100) DEFAULT NULL,
  city VARCHAR(100) DEFAULT NULL,
  district VARCHAR(100) DEFAULT NULL,
  postal_code VARCHAR(20) DEFAULT NULL,
  address TEXT NOT NULL,
  latitude DECIMAL(10,7) DEFAULT NULL,
  longitude DECIMAL(10,7) DEFAULT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($sql);

$errors = [];
$success = null;
$form = [
    'label' => 'Rumah',
    'recipient_name' => '',
    'phone' => '',
    'province' => '',
    'city' => '',
    'district' => '',
    'postal_code' => '',
    'address' => '',
    'is_primary' => 0,
];

// If redirected back from save_address.php, restore form values and errors from session
if (!empty($_SESSION['address_errors'])) {
    $errors = $_SESSION['address_errors'];
    unset($_SESSION['address_errors']);
}
if (!empty($_SESSION['address_form'])) {
    $form = $_SESSION['address_form'];
    unset($_SESSION['address_form']);
}

$editing = false;
$editId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $label = trim($_POST['label'] ?? 'Rumah');
    $recipient_name = trim($_POST['recipient_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $is_primary = isset($_POST['is_primary']) ? 1 : 0;
    $editId = isset($_POST['address_id']) ? intval($_POST['address_id']) : 0;

    if ($recipient_name === '') {
        $errors[] = 'Nama penerima harus diisi.';
    }
    if ($phone === '') {
        $errors[] = 'Nomor telepon harus diisi.';
    }
    if ($address === '') {
        $errors[] = 'Alamat lengkap harus diisi.';
    }

    if (empty($errors)) {
        if ($is_primary) {
            $stmt = $conn->prepare("UPDATE user_addresses SET is_primary = 0 WHERE user_id = ?");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }

        if ($editId > 0) {
            $stmt = $conn->prepare("UPDATE user_addresses SET label = ?, recipient_name = ?, phone = ?, province = ?, city = ?, district = ?, postal_code = ?, address = ?, is_primary = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ssssssssiii', $label, $recipient_name, $phone, $province, $city, $district, $postal_code, $address, $is_primary, $editId, $userId);
            if (!$stmt->execute()) {
                $errors[] = 'Gagal memperbarui alamat: ' . $stmt->error;
            } else {
                $success = 'Alamat berhasil diperbarui.';
            }
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO user_addresses (user_id, label, recipient_name, phone, province, city, district, postal_code, address, is_primary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('issssssssi', $userId, $label, $recipient_name, $phone, $province, $city, $district, $postal_code, $address, $is_primary);
            if (!$stmt->execute()) {
                $errors[] = 'Gagal menyimpan alamat: ' . $stmt->error;
            } else {
                $success = 'Alamat berhasil ditambahkan.';
            }
            $stmt->close();
        }

        if (!$errors && $success) {
            header('Location: addresses.php?saved=1');
            exit();
        }
    }

    $form = [
        'label' => $label,
        'recipient_name' => $recipient_name,
        'phone' => $phone,
        'province' => $province,
        'city' => $city,
        'district' => $district,
        'postal_code' => $postal_code,
        'address' => $address,
        'is_primary' => $is_primary,
    ];
}

if (isset($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $deleteId, $userId);
    $stmt->execute();
    $stmt->close();

    // set another address primary if deleted primary
    $stmt = $conn->prepare("SELECT id FROM user_addresses WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if ($row) {
        $stmt2 = $conn->prepare("UPDATE user_addresses SET is_primary = 1 WHERE id = ? AND user_id = ?");
        $stmt2->bind_param('ii', $row['id'], $userId);
        $stmt2->execute();
        $stmt2->close();
    }
    $stmt->close();
    header('Location: addresses.php?deleted=1');
    exit();
}

if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param('ii', $editId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $editing = true;
        $form = [
            'label' => $row['label'],
            'recipient_name' => $row['recipient_name'],
            'phone' => $row['phone'],
            'province' => $row['province'],
            'city' => $row['city'],
            'district' => $row['district'],
            'postal_code' => $row['postal_code'],
            'address' => $row['address'],
            'is_primary' => $row['is_primary'],
        ];
    }
    $stmt->close();
}

// Handle selecting an address for checkout
if (isset($_GET['select'])) {
  $selectId = intval($_GET['select']);
  // verify address belongs to user
  $stmt = $conn->prepare("SELECT id FROM user_addresses WHERE id = ? AND user_id = ? LIMIT 1");
  $stmt->bind_param('ii', $selectId, $userId);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result && $result->fetch_assoc()) {
    $_SESSION['selected_address_id'] = $selectId;
  }
  $stmt->close();
  // Redirect to checkout so user can continue
  header('Location: checkout.php');
  exit();
}

if (isset($_GET['saved'])) {
    $success = 'Alamat berhasil disimpan.';
}
if (isset($_GET['deleted'])) {
    $success = 'Alamat berhasil dihapus.';
}

$addresses = [];
$stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_primary DESC, updated_at DESC, id DESC");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $addresses[] = $row;
}
$stmt->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link href="../css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <link href="../css/style.css" rel="stylesheet">
  <title>Alamat Pengiriman</title>
  <style>
    .address-header { background: #00ff33; padding: 12px 0; text-align:center; font-weight:700; }
    .address-card { border: 1px solid #ddd; border-radius: 15px; padding: 18px; background: #fff; position: relative; }
    .address-card.primary { border-color: #3b5d50; }
    .address-card .badge-primary { background: #3b5d50; }
    .address-actions { gap: 0.5rem; }
    .address-card h5 { margin-top: 0; }
    .address-card .address-meta { margin-bottom: 0.75rem; }
    .address-actions a { font-size: 0.9rem; }
    .address-form .form-label { font-weight: 600; }
    .address-form .btn-save { background: #00c050; border-color: #00c050; }
    #address-map { height: 320px; border-radius: 12px; border: 1px solid #e6e6e6; }
    #autocompleteResults { max-height: 240px; overflow:auto; }
  </style>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body>
<nav class="custom-navbar navbar navbar-expand-md navbar-dark bg-dark" aria-label="navigation bar">

    		<div class="container">

        <!-- Logo -->
			<a class="navbar-brand" href="user_page.php">
				Offo<span>Living</span>
			</a>

			<!-- Toggle Mobile -->
			<button class="navbar-toggler" 
					type="button" 
					data-bs-toggle="collapse"
					data-bs-target="#navbars"
					aria-controls="navbars"
					aria-expanded="false"
					aria-label="Toggle navigation">

				<span class="navbar-toggler-icon"></span>
			</button>

			<!-- Navbar Content -->
			<div class="collapse navbar-collapse" id="navbars">

				<!-- Menu -->
				<ul class="custom-navbar-nav navbar-nav me-auto mb-2 mb-md-0">

					<li class="nav-item active">
						<a class="nav-link" href="user_page.php">Home</a>
					</li>

					<li class="nav-item">
						<a class="nav-link" href="shop.php">Shop</a>
					</li>

					<li class="nav-item">
						<a class="nav-link" href="about.php">About Us</a>
					</li>

					<li class="nav-item">
						<a class="nav-link" href="pesanan.php">Pesanan Saya</a>
					</li>
				<div class="navbar-search">
					<input 
						type="text" 
						class="navbar-search-input" 
						placeholder="Search products...">

					<i class="fa fa-search navbar-search-icon"></i>
				</div>
				<!-- Right Section -->
				<div class="navbar-right d-flex align-items-center justify-content-end"></div>
					<!-- Icons -->
					<ul class="navbar-nav navbar-icons">
						<!-- Chat -->
						<li class="nav-item">
							<a class="nav-link nav-icon-btn position-relative" href="chat.php">

								<span class="fa fa-comment"></span>

								<span class="notif-badge" id="chatBadge">0</span>

							</a>
						</li>

						<!-- Notification -->
						<div class="navbar-right d-flex align-items-center justify-content-end">
					<ul class="navbar-nav navbar-icons">
						<li class="nav-item">
							<a class="nav-link nav-icon-btn position-relative" href="#" data-bs-toggle="dropdown" data-bs-auto-close="outside">
								<span class="fa fa-bell"></span>
								<span class="notif-badge" id="notifBadge">0</span>
							</a>
							<ul class="dropdown-menu dropdown-menu-end" id="notificationDropdown" style="width: 350px; max-height: 400px; overflow-y: auto;">
								<li><h6 class="dropdown-header">Notifikasi Transaksi</h6></li>
								<li id="notificationItems"></li>
							</ul>
						</li>

						<li class="nav-item">
							<a class="nav-link nav-icon-btn position-relative" href="cart.php">
								<span class="fa fa-shopping-bag"></span>
                <span class="notif-badge" id="cartBadge">0</span>
              </a>
            </li>

            <!-- User -->
            <div class="user-auth">
							<div class="profil-box">
								<div class="avatar-circle">
							<img src="../images/user.svg" alt="User">
						</div>
							<div class ="dropdown" style="pointer-events:auto; z-index: 9999;">
								<a href="profile.php" style="pointer-events:auto;">Profil</a>
								<a href="../database/logout.php" style="pointer-events:auto;">Log Out</a>
							</div>
					</div>
					</div>
				</ul>
				</div>
			</div>
		</div>
	</nav>

<div class="address-header">
  <div class="container">
    <h2>Alamat Pengiriman Saya</h2>
  </div>
</div>

<div class="container my-4">
  <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
  <?php endif; ?>

  <div class="row gy-4">
    <div class="col-lg-6">
      <div class="address-form profile-card p-4">
        <h4><?php echo $editing ? 'Edit Alamat' : 'Tambah Alamat Baru'; ?></h4>
        <form method="post" action="../database/save_address.php" id="addressForm">
          <?php if ($editing): ?>
            <input type="hidden" name="address_id" value="<?php echo htmlspecialchars($editId); ?>">
          <?php endif; ?>
          <div class="mb-3">
            <label class="form-label">Label Alamat</label>
            <input type="text" name="label" class="form-control" value="<?php echo htmlspecialchars($form['label']); ?>" placeholder="Contoh: Rumah / Kantor">
          </div>
          <div class="mb-3">
            <label class="form-label">Nama Penerima</label>
            <input type="text" name="recipient_name" class="form-control" value="<?php echo htmlspecialchars($form['recipient_name']); ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Nomor Telepon</label>
            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($form['phone']); ?>">
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Provinsi</label>
              <input type="text" name="province" class="form-control" value="<?php echo htmlspecialchars($form['province']); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Kota/Kabupaten</label>
              <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($form['city']); ?>">
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Kecamatan</label>
              <input type="text" name="district" class="form-control" value="<?php echo htmlspecialchars($form['district']); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Kode Pos</label>
              <input type="text" name="postal_code" class="form-control" value="<?php echo htmlspecialchars($form['postal_code']); ?>">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Alamat Lengkap</label>
            <textarea id="address_detail" name="address" class="form-control" rows="4"><?php echo htmlspecialchars($form['address']); ?></textarea>
          </div>
          <div class="mb-3">
            <div class="d-flex gap-2">
              <input id="searchBox" class="form-control" placeholder="Cari alamat..." autocomplete="off">
              <button type="button" id="useLocationBtn" class="btn btn-outline-primary">Gunakan Lokasi Saya</button>
            </div>
            <ul id="autocompleteResults" class="list-group" style="display:none; position:relative; z-index:9999; margin-top:6px;"></ul>
          </div>

          <div class="mb-3">
            <div id="address-map"></div>
          </div>

          <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($form['latitude'] ?? ''); ?>" />
          <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($form['longitude'] ?? ''); ?>" />
          <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="is_primary" id="isPrimary" value="1" <?php echo $form['is_primary'] ? 'checked' : ''; ?>>
            <label class="form-check-label" for="isPrimary">Jadikan alamat utama</label>
          </div>
          <button type="submit" class="btn btn-save text-white w-100"><?php echo $editing ? 'Perbarui Alamat' : 'Simpan Alamat'; ?></button>
        </form>
      </div>
    </div>

    <div class="col-lg-6">
      <?php if (count($addresses) === 0): ?>
        <div class="profile-card p-4 text-center">
          <p class="mb-0">Belum ada alamat pengiriman tersimpan. Tambahkan alamat baru di samping kanan.</p>
        </div>
      <?php else: ?>
        <div class="row g-3">
          <?php foreach ($addresses as $address): ?>
            <div class="col-12">
              <div class="address-card <?php echo $address['is_primary'] ? 'primary' : ''; ?>">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <div>
                    <h5 class="mb-1"><?php echo htmlspecialchars($address['label']); ?></h5>
                    <?php if ($address['is_primary']): ?>
                      <span class="badge bg-primary">UTAMA</span>
                    <?php endif; ?>
                  </div>
                  <div class="address-actions d-flex">
                    <a href="addresses.php?edit=<?php echo $address['id']; ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <a href="addresses.php?delete=<?php echo $address['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus alamat ini?');">Hapus</a>
                    <?php $selectedId = $_SESSION['selected_address_id'] ?? null; ?>
                    <?php if ($selectedId == $address['id']): ?>
                      <span class="btn btn-sm btn-success">Terpilih</span>
                    <?php else: ?>
                      <a href="addresses.php?select=<?php echo $address['id']; ?>" class="btn btn-sm btn-primary">Pilih</a>
                    <?php endif; ?>
                  </div>
                </div>
                <p class="address-meta mb-1"><strong><?php echo htmlspecialchars($address['recipient_name']); ?></strong> • <?php echo htmlspecialchars($address['phone']); ?></p>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($address['address'])); ?></p>
                <p class="mb-0 text-muted"><?php echo htmlspecialchars($address['district']); ?>, <?php echo htmlspecialchars($address['city']); ?>, <?php echo htmlspecialchars($address['province']); ?> <?php echo htmlspecialchars($address['postal_code']); ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const map = L.map('address-map').setView([-6.200000, 106.816666], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(map);

  let marker = null;

  function setMarker(lat, lng, draggable = true) {
    if (marker) marker.remove();
    marker = L.marker([lat, lng], {draggable: draggable}).addTo(map);
    marker.on('dragend', onMarkerMoved);
    map.setView([lat, lng], 16);
    document.getElementById('latitude').value = parseFloat(lat).toFixed(7);
    document.getElementById('longitude').value = parseFloat(lng).toFixed(7);
    reverseGeocode(lat, lng);
  }

  function onMarkerMoved() {
    const pos = marker.getLatLng();
    document.getElementById('latitude').value = pos.lat.toFixed(7);
    document.getElementById('longitude').value = pos.lng.toFixed(7);
    reverseGeocode(pos.lat, pos.lng);
  }

  map.on('click', (e) => {
    setMarker(e.latlng.lat, e.latlng.lng);
  });

  async function reverseGeocode(lat, lng) {
    try {
      const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}`;
      const res = await fetch(url, {headers: {'Accept': 'application/json'}});
      const data = await res.json();
      if (data && data.display_name) {
        const addr = data.display_name;
        document.getElementById('address_detail').value = addr;
      }
    } catch (err) { console.error('reverseGeocode', err); }
  }

  document.getElementById('useLocationBtn').addEventListener('click', () => {
    if (!navigator.geolocation) return alert('Geolocation tidak didukung pada browser ini.');
    navigator.geolocation.getCurrentPosition((pos) => {
      setMarker(pos.coords.latitude, pos.coords.longitude);
    }, (err) => {
      alert('Gagal mendapatkan lokasi: ' + err.message);
    }, {enableHighAccuracy: true});
  });

  // Autocomplete
  const searchBox = document.getElementById('searchBox');
  const resultsList = document.getElementById('autocompleteResults');
  let acTimeout = null;
  searchBox.addEventListener('input', () => {
    const q = searchBox.value.trim();
    if (acTimeout) clearTimeout(acTimeout);
    if (!q) { resultsList.style.display = 'none'; return; }
    acTimeout = setTimeout(async () => {
      try {
        const url = `https://nominatim.openstreetmap.org/search?format=jsonv2&q=${encodeURIComponent(q)}&addressdetails=1&limit=6`;
        const res = await fetch(url);
        const items = await res.json();
        resultsList.innerHTML = '';
        items.forEach(it => {
          const li = document.createElement('li');
          li.className = 'list-group-item list-group-item-action';
          li.style.cursor = 'pointer';
          li.textContent = it.display_name;
          li.addEventListener('click', () => {
            resultsList.style.display = 'none';
            searchBox.value = it.display_name;
            setMarker(it.lat, it.lon);
            document.getElementById('address_detail').value = it.display_name;
          });
          resultsList.appendChild(li);
        });
        resultsList.style.display = items.length ? 'block' : 'none';
      } catch (err) { console.error(err); }
    }, 300);
  });

  document.addEventListener('click', (e) => {
    if (!searchBox.contains(e.target) && !resultsList.contains(e.target)) {
      resultsList.style.display = 'none';
    }
  });

  // form validation
  document.getElementById('addressForm').addEventListener('submit', function(e){
    const lat = document.getElementById('latitude').value;
    const lng = document.getElementById('longitude').value;
    const addr = document.getElementById('address_detail').value.trim();
    if (!lat || !lng || !addr) {
      e.preventDefault();
      alert('Silakan pilih titik di peta atau gunakan "Gunakan Lokasi Saya" sebelum menyimpan alamat.');
      return false;
    }
  });

  // If form has existing coords, show marker
  const latVal = document.getElementById('latitude').value;
  const lngVal = document.getElementById('longitude').value;
  if (latVal && lngVal) setMarker(latVal, lngVal);
});
</script>

<script src="../js/bootstrap.bundle.min.js"></script>
<script src="../js/custom.js"></script>
</body>
</html>
