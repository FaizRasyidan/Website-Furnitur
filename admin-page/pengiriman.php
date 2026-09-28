<?php
session_start();
require_once __DIR__ . '/../database/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_name'])) {
    header('Location: ../index.php');
    exit();
}

// Create delivery_personnel table if it doesn't exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS delivery_personnel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_type VARCHAR(50) NOT NULL,
    vehicle_number VARCHAR(50) NOT NULL UNIQUE,
    driver_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(30) NOT NULL,
    status ENUM('active', 'inactive', 'on_delivery') DEFAULT 'active',
    current_location VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createTableSQL);

// Add delivery_personnel_id column to orders table if it doesn't exist
$checkColumn = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='orders' AND COLUMN_NAME='delivery_personnel_id' AND TABLE_SCHEMA=DATABASE()";
$result = $conn->query($checkColumn);
if (!$result || $result->num_rows === 0) {
    $conn->query("ALTER TABLE orders ADD COLUMN delivery_personnel_id INT DEFAULT NULL");
}

// Handle adding new delivery personnel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_delivery_person') {
        $vehicle_type = trim($_POST['vehicle_type'] ?? '');
        $vehicle_number = trim($_POST['vehicle_number'] ?? '');
        $driver_name = trim($_POST['driver_name'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');

        if (!empty($vehicle_type) && !empty($vehicle_number) && !empty($driver_name) && !empty($phone_number)) {
            $stmt = $conn->prepare("INSERT INTO delivery_personnel (vehicle_type, vehicle_number, driver_name, phone_number) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $vehicle_type, $vehicle_number, $driver_name, $phone_number);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Pengirim berhasil ditambahkan';
            } else {
                $_SESSION['error'] = 'Error: ' . $stmt->error;
            }
            $stmt->close();
            header('Location: pengiriman.php');
            exit();
        }
    }

    if ($_POST['action'] === 'update_status') {
        $id = intval($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? 'active');
        if ($id > 0 && in_array($status, ['active', 'inactive', 'on_delivery'])) {
            $stmt = $conn->prepare("UPDATE delivery_personnel SET status = ? WHERE id = ?");
            $stmt->bind_param('si', $status, $id);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Status pengirim diperbarui';
            }
            $stmt->close();
            header('Location: pengiriman.php');
            exit();
        }
    }

    if ($_POST['action'] === 'delete_delivery_person') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM delivery_personnel WHERE id = ?");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Pengirim berhasil dihapus';
            }
            $stmt->close();
            header('Location: pengiriman.php');
            exit();
        }
    }
}

// Fetch all delivery personnel
$deliveryPersonnel = [];
$result = $conn->query("SELECT * FROM delivery_personnel ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $deliveryPersonnel[] = $row;
    }
}

// Get delivery person details if viewing detail
$deliveryDetail = null;
$deliveryOrders = [];
if (isset($_GET['detail'])) {
    $detail_id = intval($_GET['detail']);
    $stmt = $conn->prepare("SELECT * FROM delivery_personnel WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $detail_id);
    $stmt->execute();
    $deliveryDetail = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Fetch orders assigned to this delivery person (stored in delivery_id field in orders table)
    if ($deliveryDetail) {
        $ordersTableCheck = $conn->query("SHOW TABLES LIKE 'orders'");
        if ($ordersTableCheck && $ordersTableCheck->num_rows > 0) {
            $stmt = $conn->prepare("SELECT * FROM orders WHERE delivery_personnel_id = ? ORDER BY created_at DESC");
            $stmt->bind_param('i', $detail_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $row['items'] = !empty($row['items']) ? json_decode($row['items'], true) : [];
                $row['address'] = !empty($row['address']) ? json_decode($row['address'], true) : [];
                $deliveryOrders[] = $row;
            }
            $stmt->close();
        }
    }
}

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengiriman - Admin</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="../css/admin.css" rel="stylesheet">
    <style>
        .delivery-table { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        .delivery-table table { margin: 0; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .status-on_delivery { background: #fff3cd; color: #856404; }
        .action-buttons { display: flex; gap: 8px; }
        .btn-small { padding: 4px 12px; font-size: 0.85rem; }
        .add-form { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.1); margin-bottom: 20px; }
        .modal-custom { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-custom.active { display: flex; }
        .modal-content { background: white; border-radius: 12px; padding: 30px; max-width: 500px; width: 90%; }
        .detail-section { margin: 20px 0; }
        .detail-label { font-weight: 600; color: #333; }
        .detail-value { color: #666; margin-top: 4px; }
        .order-item { background: #f8f9fa; padding: 16px; border-radius: 8px; margin-bottom: 16px; border-left: 4px solid #007bff; }
        .order-item-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
        .order-item-title { font-weight: 700; color: #0d47a1; }
        .order-item-meta { color: #4b5563; font-size: 0.9rem; }
        .item-list { margin-top: 12px; }
        .item-row { display: grid; grid-template-columns: 1fr auto; gap: 12px; padding: 10px 12px; background: white; border-radius: 8px; margin-bottom: 10px; border: 1px solid #e5e7eb; }
        .item-row:last-child { margin-bottom: 0; }
        .item-name { font-weight: 600; color: #111827; }
        .item-qty { color: #6b7280; font-size: 0.9rem; }
        .destination-box { background: #f1f5f9; padding: 14px; border-radius: 10px; border: 1px solid #d1d5db; margin-top: 14px; }
        .destination-box strong { color: #111827; }
        .delivery-table table thead th { font-weight: 700; color: #1f2937; border-bottom: 2px solid #e5e7eb; }
        .delivery-table table tbody tr:hover { background: #f8fafc; }
        .detail-section { margin: 20px 0; }
        .detail-label { font-weight: 600; color: #333; }
        .detail-value { color: #666; margin-top: 4px; }
        .header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; color: #007bff; cursor: pointer; text-decoration: none; margin-bottom: 15px; }
        .back-btn:hover { color: #0056b3; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; margin-bottom: 24px; padding: 26px 30px; border-radius: 22px; background: #ffffff; border: 1px solid #e5e7eb; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08); }
        .page-title { font-size: 32px; font-weight: 800; color: #0f172a; margin-bottom: 8px; }
        .page-subtitle { color: #6b7280; font-size: 15px; line-height: 1.7; max-width: 640px; }
        .table-card { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 22px; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08); overflow: hidden; }
        .table-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 22px 24px; border-bottom: 1px solid #e5e7eb; }
        .table-title { font-size: 18px; font-weight: 700; color: #0f172a; }
        .table-desc { color: #6b7280; font-size: 14px; }
        .table-wrapper { overflow-x: auto; }
        .table-wrapper table { min-width: 760px; }
        .table-wrapper th, .table-wrapper td { vertical-align: middle; }
        .status-badge { display: inline-flex; align-items: center; justify-content: center; padding: 7px 14px; border-radius: 999px; font-size: 0.85rem; font-weight: 600; }
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #fee2e2; color: #991b1b; }
        .status-on_delivery { background: #ffedd5; color: #92400e; }
        .detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
        .detail-card { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 20px; padding: 22px; }
        .detail-card h4 { margin-bottom: 16px; font-size: 18px; color: #0f172a; }
        .order-item { background: #ffffff; padding: 20px; border-radius: 22px; margin-bottom: 18px; border: 1px solid #e5e7eb; }
        .order-item-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 18px; }
        .order-item-title { font-weight: 700; color: #0f172a; }
        .order-item-meta { color: #475569; font-size: 0.95rem; }
        .item-row { display: grid; grid-template-columns: 1fr auto; gap: 12px; padding: 14px 16px; border-radius: 16px; border: 1px solid #e5e7eb; margin-bottom: 12px; background: #f8fafc; }
        .destination-box { background: #f8fafc; padding: 18px; border-radius: 18px; border: 1px solid #e5e7eb; margin-bottom: 18px; }
        .destination-box strong { color: #0f172a; }
    </style>
</head>
<body>
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
        <?php if (!isset($_GET['detail'])): ?>
            <div class="page-header">
                <div>
                    <div class="page-title">Manajemen Pengiriman</div>
                    <div class="page-subtitle">Kelola informasi pengirim, status tugas, dan lihat daftar pengirim dengan tampilan yang konsisten seperti halaman admin lainnya.</div>
                </div>
                <button class="btn btn-primary" onclick="openAddForm()">
                    <i class="fas fa-plus"></i> Tambah Pengirim
                </button>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="table-card">
                <div class="table-toolbar">
                    <div>
                        <div class="table-title">Daftar Pengirim</div>
                        <div class="table-desc">Lihat status, nomor kendaraan, dan kontak supir dalam satu tampilan yang mudah dibaca.</div>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Jenis</th>
                                <th>No. Kendaraan</th>
                                <th>Supir</th>
                                <th>Nomor HP</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($deliveryPersonnel)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fas fa-inbox" style="font-size: 1.5rem;"></i>
                                        <div>Belum ada pengirim tersedia.</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($deliveryPersonnel as $person): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($person['vehicle_type']); ?></td>
                                        <td><?php echo htmlspecialchars($person['vehicle_number']); ?></td>
                                        <td><?php echo htmlspecialchars($person['driver_name']); ?></td>
                                        <td><?php echo htmlspecialchars($person['phone_number']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $person['status']; ?>">
                                                <?php 
                                                    $statusLabel = [
                                                        'active' => 'Aktif',
                                                        'inactive' => 'Tidak Aktif',
                                                        'on_delivery' => 'Sedang Kirim'
                                                    ];
                                                    echo $statusLabel[$person['status']] ?? $person['status'];
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="pengiriman.php?detail=<?php echo $person['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-eye"></i> Lihat
                                                </a>
                                                <button type="button" class="btn btn-outline-warning btn-sm" onclick="editPerson(<?php echo $person['id']; ?>, '<?php echo htmlspecialchars($person['status']); ?>')">
                                                    <i class="fas fa-edit"></i> Status
                                                </button>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus pengirim ini?');">
                                                    <input type="hidden" name="action" value="delete_delivery_person">
                                                    <input type="hidden" name="id" value="<?php echo $person['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <div class="page-header">
                <div>
                    <div class="page-title">Detail Pengirim</div>
                    <div class="page-subtitle">Informasi lengkap tentang supir dan pesanan yang ditangani.</div>
                </div>
                <a href="pengiriman.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>

            <?php if ($deliveryDetail): ?>
                <div class="detail-card">
                    <h4>Informasi Pengirim</h4>
                    <div class="detail-grid">
                        <div>
                            <div class="detail-label">Jenis Kendaraan</div>
                            <div class="detail-value"><?php echo htmlspecialchars($deliveryDetail['vehicle_type']); ?></div>
                        </div>
                        <div>
                            <div class="detail-label">Nomor Kendaraan</div>
                            <div class="detail-value"><?php echo htmlspecialchars($deliveryDetail['vehicle_number']); ?></div>
                        </div>
                        <div>
                            <div class="detail-label">Nama Supir</div>
                            <div class="detail-value"><?php echo htmlspecialchars($deliveryDetail['driver_name']); ?></div>
                        </div>
                        <div>
                            <div class="detail-label">Nomor HP</div>
                            <div class="detail-value"><a href="tel:<?php echo htmlspecialchars($deliveryDetail['phone_number']); ?>"><?php echo htmlspecialchars($deliveryDetail['phone_number']); ?></a></div>
                        </div>
                        <div>
                            <div class="detail-label">Status</div>
                            <div class="detail-value">
                                <span class="status-badge status-<?php echo $deliveryDetail['status']; ?>">
                                    <?php echo [
                                        'active' => 'Aktif',
                                        'inactive' => 'Tidak Aktif',
                                        'on_delivery' => 'Sedang Kirim'
                                    ][$deliveryDetail['status']] ?? $deliveryDetail['status']; ?>
                                </span>
                            </div>
                        </div>
                        <div>
                            <div class="detail-label">Lokasi Terakhir</div>
                            <div class="detail-value"><?php echo htmlspecialchars($deliveryDetail['current_location'] ?? 'Tidak ada'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="detail-card" style="margin-top: 24px;">
                    <h4>Pesanan yang Ditangani</h4>
                    <?php if (empty($deliveryOrders)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-box-open" style="font-size: 1.5rem;"></i>
                            <div>Tidak ada pesanan yang sedang dikirim.</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($deliveryOrders as $order): ?>
                            <div class="order-item">
                                <div class="order-item-header">
                                    <div>
                                        <div class="order-item-title">Pesanan <?php echo htmlspecialchars($order['order_id'] ?? 'N/A'); ?></div>
                                        <div class="order-item-meta">Dibuat: <?php echo isset($order['created_at']) ? date('d M Y H:i', strtotime($order['created_at'])) : 'N/A'; ?></div>
                                    </div>
                                    <div>
                                        <span class="status-badge status-<?php echo $order['status'] ?? 'processing'; ?>">
                                            <?php 
                                                $status = $order['status'] ?? 'processing';
                                                $statusLabel = [
                                                    'pending' => 'Menunggu',
                                                    'processing' => 'Sedang Dikemas',
                                                    'ready' => 'Siap Dikirim',
                                                    'delivered' => 'Dikirim',
                                                    'completed' => 'Selesai',
                                                    'rejected' => 'Ditolak'
                                                ];
                                                echo $statusLabel[$status] ?? ucfirst($status);
                                            ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="destination-box">
                                    <strong>Tujuan Pengiriman</strong>
                                    <div style="margin-top: 10px; color: #475569;">
                                        <?php
                                            $addr = $order['address'] ?? [];
                                            $destination = [];
                                            if (!empty($addr['recipient_name'])) $destination[] = $addr['recipient_name'];
                                            if (!empty($addr['jalan'])) $destination[] = $addr['jalan'];
                                            if (!empty($addr['kota'])) $destination[] = $addr['kota'];
                                            if (!empty($addr['provinsi'])) $destination[] = $addr['provinsi'];
                                            if (!empty($addr['kodepos'])) $destination[] = $addr['kodepos'];
                                            echo htmlspecialchars(implode(', ', $destination) ?: 'Alamat tidak lengkap');
                                        ?>
                                    </div>
                                </div>

                                <div class="item-list">
                                    <div style="font-weight: 700; margin-bottom: 10px; color: #111827;">Daftar Barang</div>
                                    <?php if (!empty($order['items']) && is_array($order['items'])): ?>
                                        <?php foreach ($order['items'] as $item): ?>
                                            <div class="item-row">
                                                <div>
                                                    <div class="item-name"><?php echo htmlspecialchars($item['name'] ?? 'Produk'); ?></div>
                                                    <div class="item-qty"><?php echo intval($item['quantity'] ?? 1); ?> × <?php echo htmlspecialchars($item['variant'] ?? $item['color'] ?? 'Standar'); ?></div>
                                                </div>
                                                <div style="text-align: right; color: #111827; font-weight: 600;">
                                                    <?php if (!empty($item['price'])): ?>
                                                        Rp<?php echo number_format($item['price'], 0, ',', '.'); ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-muted">Tidak ada detail barang.</div>
                                    <?php endif; ?>
                                </div>

                                <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-top: 16px; color: #475569;">
                                    <div>Total Biaya: <strong>Rp<?php echo isset($order['total']) ? number_format($order['total'], 0, ',', '.') : '0'; ?></strong></div>
                                    <div>Pembayaran: <strong><?php echo htmlspecialchars($order['payment_status'] ?? '-'); ?></strong></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">Pengirim tidak ditemukan</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Add Form Modal -->
    <div class="modal-custom" id="addFormModal">
        <div class="modal-content">
            <h3 style="margin-bottom: 20px; font-size: 24px; font-weight: 700; color: #0f172a;">Tambah Pengirim Baru</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_delivery_person">
                <div class="mb-3">
                    <label class="form-label" style="font-weight: 600; color: #0f172a;">Jenis Kendaraan</label>
                    <select name="vehicle_type" class="form-control" style="border-radius: 12px; padding: 10px 14px;" required>
                        <option value="">-- Pilih Jenis Kendaraan --</option>
                        <option value="Motor">Motor</option>
                        <option value="Mobil">Mobil</option>
                        <option value="Truk">Truk</option>
                        <option value="Pick-up">Pick-up</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" style="font-weight: 600; color: #0f172a;">Nomor Kendaraan</label>
                    <input type="text" name="vehicle_number" class="form-control" style="border-radius: 12px; padding: 10px 14px;" placeholder="Contoh: BA 1234 NT" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" style="font-weight: 600; color: #0f172a;">Nama Supir</label>
                    <input type="text" name="driver_name" class="form-control" style="border-radius: 12px; padding: 10px 14px;" placeholder="Nama lengkap supir" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" style="font-weight: 600; color: #0f172a;">Nomor HP</label>
                    <input type="text" name="phone_number" class="form-control" style="border-radius: 12px; padding: 10px 14px;" placeholder="0812345678" required>
                </div>
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" style="padding: 10px 22px;" onclick="closeAddForm()">Batal</button>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 22px;">Simpan Pengirim</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Status Modal -->
    <div class="modal-custom" id="editStatusModal">
        <div class="modal-content">
            <h3 class="mb-3">Ubah Status Pengirim</h3>
            <form method="POST" id="editStatusForm">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" id="editPersonId">
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" id="editStatusSelect" class="form-control" required>
                        <option value="active">Aktif</option>
                        <option value="inactive">Tidak Aktif</option>
                        <option value="on_delivery">Sedang Kirim</option>
                    </select>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Simpan</button>
                    <button type="button" class="btn btn-secondary flex-grow-1" onclick="closeEditStatus()">Batal</button>
                </div>
            </form>
        </div>
    </div>

<script src="../js/bootstrap.bundle.min.js"></script>
<script>
    function openAddForm() {
        const modal = document.getElementById('addFormModal');
        if (modal) modal.classList.add('active');
    }

    function closeAddForm() {
        const modal = document.getElementById('addFormModal');
        if (modal) modal.classList.remove('active');
    }

    function editPerson(id, status) {
        document.getElementById('editPersonId').value = id;
        document.getElementById('editStatusSelect').value = status;
        document.getElementById('editStatusModal').classList.add('active');
    }

    function closeEditStatus() {
        document.getElementById('editStatusModal').classList.remove('active');
    }

    // Close modal when clicking outside
    document.addEventListener('DOMContentLoaded', function() {
        const addModal = document.getElementById('addFormModal');
        if (addModal) {
            addModal.addEventListener('click', function(e) {
                if (e.target === this) closeAddForm();
            });
        }

        const editModal = document.getElementById('editStatusModal');
        if (editModal) {
            editModal.addEventListener('click', function(e) {
                if (e.target === this) closeEditStatus();
            });
        }
    });
</script>
<script src="../js/dashboard.js"></script>
</body>
</html>
