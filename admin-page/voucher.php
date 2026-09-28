<?php
session_start();
require_once __DIR__ . '/../database/config.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: index.php');
    exit();
}

$admin = $_SESSION['admin_name'];
$message = '';

function generateVoucherCode($conn) {
    $prefix = 'VC';
    for ($i = 0; $i < 10; $i++) {
        $candidate = $prefix . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $stmt = $conn->prepare("SELECT id FROM vouchers WHERE code = ?");
        if ($stmt) {
            $stmt->bind_param('s', $candidate);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $stmt->close();
                return $candidate;
            }
            $stmt->close();
        }
    }
    return $prefix . strtoupper(uniqid());
}

$createVoucherTableSql = "CREATE TABLE IF NOT EXISTS vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'percent',
    discount_value DECIMAL(10,2) NOT NULL DEFAULT 0,
    min_spend INT NOT NULL DEFAULT 0,
    max_discount INT NOT NULL DEFAULT 0,
    quota INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    valid_from DATE DEFAULT NULL,
    valid_until DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createVoucherTableSql);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_voucher'])) {
    $code = generateVoucherCode($conn);
    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['type'] ?? 'percent');
    $discountValue = floatval($_POST['discount_value'] ?? 0);
    $minSpend = intval($_POST['min_spend'] ?? 0);
    $maxDiscount = intval($_POST['max_discount'] ?? 0);
    $quota = intval($_POST['quota'] ?? 0);
    $active = isset($_POST['active']) ? 1 : 0;
    $validFrom = trim($_POST['valid_from'] ?? '');
    $validUntil = trim($_POST['valid_until'] ?? '');

    if ($name === '' || ($type !== 'shipping' && $discountValue <= 0)) {
        $message = '<div class="alert alert-danger">Nama voucher wajib diisi. Untuk tipe Persen/Tetap, nilai diskon harus lebih dari 0.</div>';
    } else {
        $stmt = $conn->prepare("INSERT INTO vouchers (code, name, type, discount_value, min_spend, max_discount, quota, active, valid_from, valid_until)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                type = VALUES(type),
                discount_value = VALUES(discount_value),
                min_spend = VALUES(min_spend),
                max_discount = VALUES(max_discount),
                quota = VALUES(quota),
                active = VALUES(active),
                valid_from = VALUES(valid_from),
                valid_until = VALUES(valid_until)");

        if ($stmt) {
            $validFromParam = $validFrom !== '' ? $validFrom : null;
            $validUntilParam = $validUntil !== '' ? $validUntil : null;
            $stmt->bind_param('sssdiiiiss', $code, $name, $type, $discountValue, $minSpend, $maxDiscount, $quota, $active, $validFromParam, $validUntilParam);
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Voucher berhasil disimpan. Kode: <strong>' . htmlspecialchars($code) . '</strong></div>';
            } else {
                $message = '<div class="alert alert-danger">Gagal menyimpan voucher: ' . htmlspecialchars($stmt->error) . '</div>';
            }
            $stmt->close();
        } else {
            $message = '<div class="alert alert-danger">Gagal mempersiapkan query voucher: ' . htmlspecialchars($conn->error) . '</div>';
        }
    }
}

$vouchers = [];
$orderTableExists = false;
$orderTableCheck = $conn->query("SHOW TABLES LIKE 'orders'");
if ($orderTableCheck && $orderTableCheck->num_rows > 0) {
    $orderTableExists = true;
}

if ($orderTableExists) {
    $voucherQuery = "SELECT v.*, COUNT(o.order_id) AS used_count
        FROM vouchers v
        LEFT JOIN orders o ON o.voucher = v.code
        GROUP BY v.id
        ORDER BY v.id DESC";
} else {
    $voucherQuery = "SELECT v.*, 0 AS used_count
        FROM vouchers v
        ORDER BY v.id DESC";
}

$result = $conn->query($voucherQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $vouchers[] = $row;
    }
}

function formatCurrency($value) {
    return 'Rp ' . number_format((int)$value, 0, ',', '.');
}

function formatDate($date) {
    if (!$date || $date === '0000-00-00') {
        return '-';
    }
    return date('d-m-Y', strtotime($date));
}

function typeLabel($type) {
    return $type === 'fixed' ? 'Tetap' : ($type === 'shipping' ? 'Gratis Ongkir' : 'Persen');
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher - Admin Offo Living</title>
    <link href='https://cdn.jsdelivr.net/npm/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .voucher-page .page-header { margin-bottom: 20px; }
        .voucher-card { padding: 20px; }
        .voucher-form .form-group { margin-bottom: 18px; }
        .voucher-form label { display: block; font-weight: 600; margin-bottom: 6px; }
        .voucher-form input,
        .voucher-form select { width: 100%; padding: 12px 14px; border: 1px solid #d1d5db; border-radius: 10px; }
        .voucher-form .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; }
        .voucher-table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
        .voucher-table th,
        .voucher-table td { padding: 14px 12px; border: 1px solid #e2e8f0; }
        .voucher-table th { background: #f8fafc; color: #334155; }
        .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; font-weight: 600; font-size: 0.85rem; }
        .status-badge.active { background: rgba(16, 185, 129, .12); color: #059669; }
        .status-badge.inactive { background: rgba(248, 113, 113, .12); color: #b91c1c; }
        .action-button { border: none; background: transparent; color: #64748b; cursor: pointer; }
        .voucher-meta { font-size: 0.85rem; color: #64748b; }
        .voucher-notice { margin-bottom: 20px; }
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 18px; }
        .alert-danger { background: #fee2e2; color: #b91c1c; }
        .alert-success { background: #dcfce7; color: #166534; }
        .voucher-help { font-size: 0.85rem; color: #6b7280; margin-top: 6px; }
        .voucher-help small { color: #6b7280; }
    </style>
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
        <header>
            <div class="header-title">Voucher Management</div>
            <div class="user-profile">
                <div>
                    <span style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($admin); ?></span><br>
                    <span style="font-size: 12px; color: #888;">Super Admin</span>
                </div>
                <div class="avatar"><i class='bx bxs-user'></i></div>
            </div>
        </header>

        <?php echo $message; ?>

        <div class="card voucher-card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Tambah Voucher Baru</h3>
                    <p class="card-subtitle">Tambahkan voucher promosi yang akan digunakan oleh pelanggan di halaman checkout.</p>
                </div>
            </div>
            <form method="POST" class="voucher-form">
                <div class="voucher-form form-row">
                    <div class="form-group">
                        <label for="name">Nama Voucher</label>
                        <input type="text" id="name" name="name" maxlength="255" required>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Kode Voucher</label>
                        <div style="padding: 12px 14px; border: 1px solid #d1d5db; border-radius: 10px; background: #f8fafc; color: #334155;">
                            Kode dibuat otomatis saat voucher disimpan.
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="type">Tipe Diskon</label>
                        <select id="type" name="type" required>
                            <option value="percent">Persen</option>
                            <option value="fixed">Tetap</option>
                            <option value="shipping">Gratis Ongkir</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="discount_value">Nilai Diskon</label>
                        <input type="number" id="discount_value" name="discount_value" min="0" step="0.01" placeholder="Contoh: 0.10 (10%) atau 50000" required>
                        <div id="discountHelp" class="voucher-help"><small>Jika <strong>Persen</strong>: masukkan desimal (mis. 0.10 = 10%). Jika <strong>Tetap</strong>: masukkan nominal dalam Rupiah (mis. 50000). Untuk <strong>Gratis Ongkir</strong>, isi 0.</small></div>
                    </div>
                    <div class="form-group">
                        <label for="min_spend">Min. Belanja</label>
                        <input type="number" id="min_spend" name="min_spend" min="0" value="0" placeholder="0">
                        <div id="minSpendHelp" class="voucher-help"><small>Jumlah minimum (dalam Rupiah) supaya voucher dapat digunakan. Isi 0 jika tidak ada syarat minimum.</small></div>
                    </div>
                    <div class="form-group">
                        <label for="max_discount">Max. Diskon</label>
                        <input type="number" id="max_discount" name="max_discount" min="0" value="0" placeholder="0">
                        <div id="maxDiscountHelp" class="voucher-help"><small>Batas maksimal potongan (dalam Rupiah) untuk tipe <em>Persen</em>. Isi 0 jika tanpa batas.</small></div>
                    </div>
                    <div class="form-group">
                        <label for="quota">Kuota</label>
                        <input type="number" id="quota" name="quota" min="0" value="0" placeholder="0">
                        <div id="quotaHelp" class="voucher-help"><small>Jumlah maksimal penggunaan voucher. Isi 0 untuk tak terbatas.</small></div>
                    </div>
                    <div class="form-group">
                        <label for="active">Status Aktif</label>
                        <select id="active" name="active">
                            <option value="1" selected>Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="valid_from">Tanggal Mulai</label>
                        <input type="date" id="valid_from" name="valid_from">
                    </div>
                    <div class="form-group">
                        <label for="valid_until">Tanggal Berakhir</label>
                        <input type="date" id="valid_until" name="valid_until">
                    </div>
                </div>
                <button type="submit" name="save_voucher" class="btn btn-primary" style="margin-top: 12px;">Simpan Voucher</button>
            </form>
        </div>

        <div class="card voucher-card" style="margin-top: 24px;">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Daftar Voucher</h3>
                    <p class="card-subtitle">Kelola voucher yang tersedia untuk checkout pelanggan.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="voucher-table">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Voucher</th>
                                <th>Tipe Diskon</th>
                                <th>Nilai Diskon</th>
                                <th>Min. Belanja</th>
                                <th>Max. Diskon</th>
                                <th>Kuota</th>
                                <th>Digunakan</th>
                                <th>Aktif</th>
                                <th>Tgl Mulai</th>
                                <th>Tgl Berakhir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($vouchers)): ?>
                                <tr><td colspan="11" style="text-align:center; padding: 18px;">Belum ada voucher.</td></tr>
                            <?php else: ?>
                                <?php foreach ($vouchers as $voucher): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($voucher['code']); ?></td>
                                        <td><?php echo htmlspecialchars($voucher['name']); ?></td>
                                        <td><?php echo typeLabel($voucher['type']); ?></td>
                                        <td>
                                            <?php
                                                if ($voucher['type'] === 'percent') {
                                                    echo htmlspecialchars($voucher['discount_value'] * 100) . '%';
                                                } elseif ($voucher['type'] === 'fixed') {
                                                    echo formatCurrency($voucher['discount_value']);
                                                } else {
                                                    echo 'Gratis Ongkir';
                                                }
                                            ?>
                                        </td>
                                        <td><?php echo formatCurrency($voucher['min_spend']); ?></td>
                                        <td><?php echo $voucher['max_discount'] > 0 ? formatCurrency($voucher['max_discount']) : '-'; ?></td>
                                        <td><?php echo number_format($voucher['quota']); ?></td>
                                        <td><?php echo number_format($voucher['used_count']); ?></td>
                                        <td><span class="status-badge <?php echo ($voucher['active'] ? 'active' : 'inactive'); ?>"><?php echo ($voucher['active'] ? 'Ya' : 'Tidak'); ?></span></td>
                                        <td><?php echo formatDate($voucher['valid_from']); ?></td>
                                        <td><?php echo formatDate($voucher['valid_until']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSubmenu(element) {
            const submenu = element.nextElementSibling;
            const arrow = element.querySelector('.arrow');
            if (submenu) submenu.classList.toggle('show');
            if (arrow) arrow.classList.toggle('rotate');
        }
    </script>
</body>
</html>
<script>
    // Dynamic helper/placeholder based on selected type
    (function(){
        const typeEl = document.getElementById('type');
        const discountEl = document.getElementById('discount_value');
        const discountHelp = document.getElementById('discountHelp');

        function updateHelp() {
            const t = typeEl.value;
            if (t === 'percent') {
                discountEl.placeholder = 'Contoh: 0.10 (10%)';
                discountEl.step = '0.01';
                discountHelp.innerHTML = '<small>Masukkan desimal, mis. 0.10 = 10%.</small>';
            } else if (t === 'fixed') {
                discountEl.placeholder = 'Contoh: 50000 (Rupiah)';
                discountEl.step = '1';
                discountHelp.innerHTML = '<small>Masukkan nominal potongan dalam Rupiah, mis. 50000 = Rp 50.000.</small>';
            } else {
                discountEl.placeholder = '0';
                discountEl.step = '1';
                discountHelp.innerHTML = '<small>Tipe Gratis Ongkir tidak memerlukan nilai diskon.</small>';
            }
        }

        if (typeEl) {
            typeEl.addEventListener('change', updateHelp);
            updateHelp();
        }
    })();
</script>
