<?php
session_start();
require_once __DIR__ . '/../database/config.php';

// Handle API request to update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (!isset($_SESSION['admin_name'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }
    
    $orderId = trim($_POST['order_id'] ?? '');
    $newStatus = trim($_POST['status'] ?? '');
    $note = trim($_POST['note'] ?? '');
    
    if (!$orderId || !$newStatus) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit();
    }
    
    // Update orders table
    $updateSql = "UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE order_id = ?";
    $stmt = $conn->prepare($updateSql);
    if ($stmt) {
        $stmt->bind_param('ss', $newStatus, $orderId);
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Status pesanan berhasil diperbarui']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Database update failed: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
    }
    exit();
}

if (!isset($_SESSION['admin_name'])) {
    header('Location: ../index.php');
    exit();
}
$admin = $_SESSION['admin_name'];

// Fetch all orders from database
$ordersData = [];
$tableCheck = $conn->query("SHOW TABLES LIKE 'orders'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $stmt = $conn->prepare("SELECT o.*, u.name AS customer, dp.id AS delivery_person_id, dp.driver_name AS delivery_driver_name, dp.phone_number AS delivery_phone, dp.vehicle_number AS delivery_vehicle FROM orders o LEFT JOIN users u ON o.user_id = u.id LEFT JOIN delivery_personnel dp ON o.delivery_personnel_id = dp.id ORDER BY o.timestamp DESC");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Parse JSON fields
            if (isset($row['items']) && is_string($row['items'])) {
                $row['items'] = json_decode($row['items'], true) ?: [];
            }
            if (isset($row['address']) && is_string($row['address'])) {
                $row['address'] = json_decode($row['address'], true) ?: [];
            }
            if (isset($row['payment_result']) && is_string($row['payment_result'])) {
                $row['payment_result'] = json_decode($row['payment_result'], true) ?: [];
            }
            if (isset($row['tracking_data']) && is_string($row['tracking_data'])) {
                $row['tracking_data'] = json_decode($row['tracking_data'], true) ?: [];
            }

            // Normalize address fields to canonical keys used by admin UI
            if (isset($row['address']) && is_array($row['address'])) {
                $a = $row['address'];
                $normalized = [];
                $normalized['address'] = $a['address'] ?? $a['jalan'] ?? $a['alamat'] ?? $a['street'] ?? $a['full_address'] ?? '';
                $normalized['city'] = $a['city'] ?? $a['kota'] ?? $a['kabupaten'] ?? $a['regency'] ?? '';
                $normalized['province'] = $a['province'] ?? $a['provinsi'] ?? $a['prov'] ?? '';
                $normalized['postal_code'] = $a['postal_code'] ?? $a['kodepos'] ?? $a['zip'] ?? '';
                $normalized['phone'] = $a['phone'] ?? $a['telepon'] ?? $a['phone_number'] ?? $a['hp'] ?? $a['telephone'] ?? '';
                $normalized['recipient_name'] = $a['recipient_name'] ?? $a['name'] ?? $a['nama'] ?? $a['recipient'] ?? '';
                // Preserve original for debugging if needed
                $normalized['_raw'] = $a;
                $row['address'] = $normalized;
            }

            // Normalize database column names for JavaScript
            $row['orderId'] = $row['order_id'];
            $row['userId'] = $row['user_id'];
            $row['paymentStatus'] = $row['payment_status'];
            $row['paymentMethod'] = $row['payment_method'];
            $row['createdAt'] = $row['created_at'];

            // Attach delivery person info if available
            if (!empty($row['delivery_person_id'])) {
                $row['deliveryPerson'] = [
                    'id' => $row['delivery_person_id'],
                    'name' => $row['delivery_driver_name'] ?? null,
                    'phone' => $row['delivery_phone'] ?? null,
                    'vehicle' => $row['delivery_vehicle'] ?? null,
                ];
            } else {
                $row['deliveryPerson'] = null;
            }

            $ordersData[] = $row;
        }
        $stmt->close();
    }
}

$storeLocations = [];
$storeTableCheck = $conn->query("SHOW TABLES LIKE 'store_locations'");
if ($storeTableCheck && $storeTableCheck->num_rows > 0) {
    $storeStmt = $conn->prepare("SELECT id, name, latitude, longitude FROM store_locations ORDER BY name ASC");
    if ($storeStmt) {
        $storeStmt->execute();
        $storeResult = $storeStmt->get_result();
        while ($storeRow = $storeResult->fetch_assoc()) {
            $storeLocations[] = $storeRow;
        }
        $storeStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pesanan</title>
    <link href='https://cdn.jsdelivr.net/npm/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        .container { max-width: 1280px; margin: 24px auto; padding: 0 20px; }
        .page-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 24px; background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 24px 28px; box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08); }
        .page-title { color: #142850; font-size: 30px; font-weight: 800; line-height: 1.1; }
        .page-subtitle { color: #64748b; font-size: 15px; max-width: 620px; line-height: 1.8; }
        .header-group { display: flex; flex-direction: column; gap: 8px; }
        .action-group { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
        .btn { border-radius: 14px; border: none; padding: 12px 22px; cursor: pointer; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; transition: all 0.25s ease; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); }
        .btn-primary { background: #142850; color: #fff; }
        .btn-primary:hover { background: #0f1f38; }
        .btn-danger { background: #ef4444; color: #fff; }
        .btn-danger:hover { background: #dc2626; }
        .btn-success { background: #10b981; color: #fff; }
        .btn-success:hover { background: #059669; }
        .btn-warning { background: #f59e0b; color: #fff; }
        .btn-warning:hover { background: #d97706; }
        .btn-sm { padding: 9px 14px; font-size: 13px; }
        .search-box { display: flex; gap: 8px; align-items: center; flex: 1; min-width: 280px; max-width: 480px; background: #f8fafc; border: 1px solid #d1d5db; border-radius: 14px; padding: 10px 14px; }
        .search-box i { color: #667085; font-size: 18px; }
        .search-box input { flex: 1; border: none; padding: 10px 0; background: transparent; color: #1f2937; font-size: 14px; }
        .search-box input::placeholder { color: #94a3b8; }
        .search-box input:focus { outline: none; }
        .table-section { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; overflow: hidden; box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08); }
        .table-toolbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; padding: 20px 24px; gap: 16px; border-bottom: 1px solid #e2e8f0; }
        .table-toolbar .toolbar-title { font-size: 16px; font-weight: 700; color: #142850; }
        .table-toolbar .toolbar-note { color: #64748b; font-size: 14px; }
        .table-wrapper { overflow-x: auto; width: 100%; }
        .table-wrapper table { width: 100%; max-width: 100%; border-collapse: collapse; min-width: 0; table-layout: fixed; background: #fff; }
        table th, table td { word-break: break-word; white-space: normal; min-width: 0; }
        table th { background: #142850; color: #fff; padding: 16px 18px; text-align: left; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; position: sticky; top: 0; z-index: 2; }
        table td { padding: 16px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #334155; }
        table tbody tr { transition: background-color 0.2s ease; }
        table tbody tr:hover { background-color: #f8fafc; }
        .badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-processing { background: #dbeafe; color: #1e40af; }
        .badge-ready { background: #c7f0d8; color: #065f46; }
        .badge-delivered { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .badge-rejected { background: #fee2e2; color: #7f1d1d; border-left: 4px solid #ef4444; }
        .badge-preorder { background: #e9d5ff; color: #6b21a8; }
        .badge-payment-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .badge-payment-pending { background: #fed7aa; color: #92400e; border-left: 4px solid #f59e0b; }
        .badge-payment-notdone { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        .action-buttons { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: flex-start; }
        .action-buttons button { padding: 8px 10px; border: none; border-radius: 8px; cursor: pointer; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; white-space: nowrap; }
        .action-buttons button i { font-size: 16px; }
        .action-btn-status { background: #e0e7ff; color: #3730a3; }
        .action-btn-status:hover { background: #c7d2fe; }
        .action-btn-print { background: #fef3c7; color: #92400e; }
        .action-btn-print:hover { background: #fde68a; }
        .action-btn-delete { background: #fee2e2; color: #991b1b; }
        .action-btn-delete:hover { background: #fecaca; }
        .pagination { display: flex; justify-content: space-between; align-items: center; padding: 16px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
        .pagination-info { color: #64748b; font-size: 13px; }
        .pagination-controls { display: flex; gap: 8px; }
        .pagination-btn { background: #fff; border: 1px solid #cbd5e0; color: #334155; padding: 6px 10px; border-radius: 6px; cursor: pointer; }
        .pagination-btn:hover { background: #f1f5f9; }
        .pagination-btn.active { background: #142850; color: #fff; border-color: #142850; }
        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: #fff; border-radius: 16px; padding: 32px; max-width: 500px; width: 90%; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); }
        .modal-header { font-size: 22px; font-weight: 700; color: #142850; margin-bottom: 16px; }
        .modal-body { margin-bottom: 24px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 600; color: #334155; margin-bottom: 8px; font-size: 14px; }
        .form-group select, .form-group input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e0; border-radius: 8px; font-size: 14px; }
        .form-group select:focus, .form-group input:focus { outline: none; border-color: #142850; box-shadow: 0 0 0 3px rgba(20, 40, 80, 0.1); }
        .modal-footer { display: flex; gap: 12px; justify-content: flex-end; }
        .no-data { text-align: center; padding: 40px; color: #64748b; }
        .no-data i { font-size: 48px; color: #cbd5e0; margin-bottom: 16px; }
        .receipt-content { background: #f9fafb; padding: 20px; border-radius: 8px; border: 1px dashed #cbd5e0; }
        .receipt-header { text-align: center; margin-bottom: 20px; font-weight: 700; color: #142850; }
        .receipt-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
        .receipt-item:last-child { border-bottom: none; }
        @media (max-width: 1024px) {
            table { font-size: 12px; }
            table th, table td { padding: 10px; }
            .action-buttons { flex-wrap: wrap; }
        }
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: stretch; }
            .search-box { max-width: 100%; }
            .action-group { flex-direction: column; }
        }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .sidebar, .header, .pagination, .action-buttons { display: none !important; }
            .main-content { margin-left: 0 !important; }
            .modal { display: none !important; }
        }
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
        <header>
            <div class="header-title">Data Pesanan</div>
            <div class="user-profile">
                <div>
                    <span style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($admin); ?></span><br>
                    <span style="font-size: 12px; color: #888;">Admin</span>
                </div>
                <div class="avatar"><i class='bx bxs-user'></i></div>
            </div>
        </header>
        <div class="container">
            <div class="page-header no-print">
                <div class="header-group">
                    <div class="page-title">Data Pesanan</div>
                    <div class="page-subtitle">Kelola dan pantau semua pesanan pelanggan</div>
                </div>
                <div class="action-group">
                    <div class="search-box">
                        <i class='bx bx-search'></i>
                        <input type="text" id="searchInput" placeholder="Cari invoice atau pembeli...">
                    </div>
                    <button class="btn btn-primary" onclick="location.reload()">
                        <i class='bx bx-refresh'></i> Refresh
                    </button>
                </div>
            </div>
            <div class="table-section">
                <div class="table-toolbar no-print">
                    <div class="toolbar-title">Daftar Pesanan</div>
                    <div class="toolbar-note">Ringkasan pesanan terbaru ditampilkan di bawah, dengan kontrol pencarian dan halaman yang mudah.</div>
                </div>
                <div class="table-wrapper">
                    <table id="ordersTable">
                        <thead>
                            <tr>
                                <th>No Invoice</th>
                                <th>Nama Pembeli</th>
                                <th>Tanggal</th>
                                <th>Total Pembayaran</th>
                                <th>Status Pembayaran</th>
                                <th>Status Pesanan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="ordersTableBody">
                        </tbody>
                    </table>
                </div>
                <div class="pagination no-print">
                    <div class="pagination-info">
                        Menampilkan <span id="pageInfo">0 - 0</span> dari <span id="totalInfo">0</span> hasil
                    </div>
                    <div class="pagination-controls">
                        <button class="pagination-btn" onclick="previousPage()">&laquo; Sebelumnya</button>
                        <span id="pageNumbers"></span>
                        <button class="pagination-btn" onclick="nextPage()">Selanjutnya &raquo;</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Ubah Status -->
    <div class="modal" id="statusModal">
        <div class="modal-content">
            <div class="modal-header">Ubah Status Pesanan</div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Invoice</label>
                    <input type="text" id="modalInvoice" readonly style="background-color: #f3f4f6; cursor: not-allowed;">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select id="statusSelect">
                        <option value="pending">Menunggu Pembayaran</option>
                        <option value="processing">Pesanan Diproses</option>
                        <option value="dikirim">Pesanan Dikirim</option>
                        <option value="delivered">Pesanan Sampai</option>
                        <option value="rejected">Ditolak</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Lokasi Toko Asal</label>
                    <select id="storeSelect">
                        <option value="">-- Pilih Toko Pengirim --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Catatan (Opsional)</label>
                    <input type="text" id="statusNote" placeholder="Tambahkan catatan...">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeStatusModal()" style="background: #e2e8f0; color: #334155;">Batal</button>
                <button class="btn btn-primary" onclick="saveStatus()">Simpan Perubahan</button>
            </div>
        </div>
    </div>

    <!-- Modal Cetak Resi -->
    <div class="modal" id="receiptModal">
        <div class="modal-content">
            <div class="modal-header">Resi Pengiriman</div>
            <div class="modal-body">
                <div class="receipt-content" id="receiptContent">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeReceiptModal()" style="background: #e2e8f0; color: #334155;">Tutup</button>
                <button class="btn btn-primary" onclick="printReceipt()">
                    <i class='bx bx-printer'></i> Cetak
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Ubah Status Pembayaran -->
    <div class="modal" id="paymentModal">
        <div class="modal-content">
            <div class="modal-header">Ubah Status Pembayaran</div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Invoice</label>
                    <input type="text" id="paymentInvoice" readonly style="background-color: #f3f4f6; cursor: not-allowed;">
                </div>
                <div class="form-group">
                    <label>Status Pembayaran</label>
                    <select id="paymentStatusSelect">
                        <option value="success">Pembayaran Sukses</option>
                        <option value="pending">Pembayaran Pending</option>
                        <option value="notdone">Pembayaran Belum Dilakukan</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closePaymentModal()" style="background: #e2e8f0; color: #334155;">Batal</button>
                <button class="btn btn-primary" onclick="savePaymentStatus()">Simpan Perubahan</button>
            </div>
        </div>
    </div>

    <!-- Modal Assign Delivery Person -->
    <div class="modal" id="assignModal">
        <div class="modal-content">
            <div class="modal-header">Assign Pengirim</div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Invoice</label>
                    <input type="text" id="assignInvoice" readonly style="background-color: #f3f4f6; cursor: not-allowed;">
                </div>
                <div class="form-group">
                    <label>Pilih Pengirim</label>
                    <select id="deliverySelect">
                        <option value="">-- Pilih Pengirim --</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeAssignModal()" style="background: #e2e8f0; color: #334155;">Batal</button>
                <button class="btn btn-primary" onclick="saveAssignment()">Assign Pengirim</button>
            </div>
        </div>
    </div>

    <script>
        // Initialize from database (injected by PHP)
        const orders = <?php echo json_encode($ordersData); ?>;
        let filteredOrders = [...orders];
        let currentPage = 1;
        const itemsPerPage = 10;
        let currentEditIndex = -1;

        const statusConfig = {
            pending: { label: 'Menunggu Pembayaran', class: 'badge-pending' },
            processing: { label: 'Pesanan Diproses', class: 'badge-processing' },
            ready: { label: 'Pesanan Dikirim', class: 'badge-ready' },
            on_delivery: { label: 'Pesanan Dikirim', class: 'badge-ready' },
            delivered: { label: 'Pesanan Sampai', class: 'badge-delivered' },
            completed: { label: 'Selesai', class: 'badge-completed' },
            rejected: { label: 'Ditolak', class: 'badge-rejected' }
        };

        const paymentStatusConfig = {
            success: { label: 'Pembayaran Sukses', class: 'badge-payment-success' },
            pending: { label: 'Pembayaran Pending', class: 'badge-payment-pending' },
            notdone: { label: 'Pembayaran Belum Dilakukan', class: 'badge-payment-notdone' }
        };

        const storeLocations = <?php echo json_encode($storeLocations); ?>;

        function formatRupiah(value) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
        }

        function formatDate(timestamp) {
            if (!timestamp) return '-';
            const date = new Date(timestamp);
            if (isNaN(date.getTime())) return '-';
            return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        }

        function buildOrderNumber(order, index) {
            const date = order.timestamp ? new Date(order.timestamp) : new Date();
            const yyyy = date.getFullYear();
            const mm = String(date.getMonth() + 1).padStart(2, '0');
            const dd = String(date.getDate()).padStart(2, '0');
            return `INV-${yyyy}${mm}${dd}-${String(index + 1).padStart(4, '0')}`;
        }

        function getCustomerLabel(order) {
            if (order.customer) return order.customer;
            if (order.address && order.address.recipient_name) return order.address.recipient_name;
            if (order.address && order.address.name) return order.address.name;
            return `User #${order.userId ?? 'N/A'}`;
        }

        function getStatusBadge(status) {
            const config = statusConfig[status] || statusConfig['pending'];
            return `<span class="badge ${config.class}">${config.label}</span>`;
        }

        function getPaymentStatusBadge(paymentStatus) {
            const config = paymentStatusConfig[paymentStatus] || paymentStatusConfig['notdone'];
            return `<span class="badge ${config.class}">${config.label}</span>`;
        }

        function getPreOrderStatus(order) {
            return order.isPreOrder ? '<span class="badge badge-preorder">Pre-Order</span>' : '<span style="color: #888;">-</span>';
        }

        function openStatusModal(index) {
            currentEditIndex = index;
            const order = orders[index];
            const invoiceNumber = buildOrderNumber(order, index);
            document.getElementById('modalInvoice').value = invoiceNumber;
            const normalizedStatus = ['ready', 'siap_dikirim'].includes(order.status) ? 'dikirim' : (order.status || 'pending');
            document.getElementById('statusSelect').value = normalizedStatus;
            document.getElementById('statusNote').value = order.statusNote || '';

            const storeSelect = document.getElementById('storeSelect');
            storeSelect.innerHTML = '<option value="">-- Pilih Toko Pengirim --</option>';
            storeLocations.forEach(store => {
                const option = document.createElement('option');
                option.value = store.id;
                option.textContent = `${store.name} (${store.latitude}, ${store.longitude})`;
                storeSelect.appendChild(option);
            });

            if (order.tracking_data && order.tracking_data.store_id) {
                storeSelect.value = order.tracking_data.store_id;
            }

            document.getElementById('statusModal').classList.add('active');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.remove('active');
            currentEditIndex = -1;
        }

        function saveStatus() {
            if (currentEditIndex === -1) return;
            
            const order = orders[currentEditIndex];
            const newStatus = document.getElementById('statusSelect').value;
            const note = document.getElementById('statusNote').value;
            const selectedStoreId = document.getElementById('storeSelect').value;
            
            // Map status values for database compatibility
            let dbStatus = newStatus;
            if (newStatus === 'processing') dbStatus = 'processing';
            if (newStatus === 'ready') dbStatus = 'on_delivery';
            if (newStatus === 'siap_dikirim') dbStatus = 'on_delivery';
            if (newStatus === 'dikirim') dbStatus = 'on_delivery';
            if (newStatus === 'on_delivery') dbStatus = 'on_delivery';
            if (newStatus === 'delivered') dbStatus = 'delivered';
            if (newStatus === 'completed') dbStatus = 'completed';
            
            // Update UI immediately for feedback
            order.status = dbStatus;
            order.statusNote = note;
            if (!order.tracking_data) order.tracking_data = {};
            if (selectedStoreId) order.tracking_data.store_id = selectedStoreId;
            order.statusUpdated = new Date().toISOString();
            
            // Send to server to update database via update_order_status.php
            fetch('../database/update_order_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    order_id: order.order_id || order.orderId,
                    new_status: dbStatus,
                    user_id: order.user_id || 0,
                    note: note,
                    store_id: selectedStoreId ? parseInt(selectedStoreId, 10) : null
                })
            })
            .then(async response => {
                const text = await response.text();
                try {
                    return JSON.parse(text);
                } catch (err) {
                    console.error('Invalid JSON response:', text);
                    throw new Error(text || err.message);
                }
            })
            .then(data => {
                if (data.ok) {
                    console.log('Status pesanan berhasil disimpan ke database:', data);
                    alert('Status berhasil diperbarui!');
                } else {
                    console.error('Error saving status:', data.error);
                    alert('Gagal menyimpan ke database: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('Error saving status: ' + error.message);
            });
            
            closeStatusModal();
            renderOrders();
        }

        function openReceiptModal(index) {
            const order = orders[index];
            const invoiceNumber = buildOrderNumber(order, index);
            const customer = getCustomerLabel(order);
            const address = order.address || {};
            const statusLabel = statusConfig[order.status || 'pending'].label;

            // Determine store name from tracking_data if available
            let storeName = '-';
            try {
                if (order.tracking_data && order.tracking_data.store_id) {
                    const s = storeLocations.find(x => parseInt(x.id, 10) === parseInt(order.tracking_data.store_id, 10));
                    if (s) storeName = s.name;
                }
            } catch (e) { console.warn(e); }

            // Determine delivery person info if available
            const delivery = order.deliveryPerson || order.delivery_person || null;
            const deliveryInfoHtml = delivery ? `
                <div class="receipt-item">
                    <span>Pengirim:</span>
                    <strong>${delivery.name || '-'} ${delivery.vehicle ? '(' + delivery.vehicle + ')' : ''}</strong>
                </div>
                <div class="receipt-item">
                    <span>Telepon Pengirim:</span>
                    <strong>${delivery.phone || '-'}</strong>
                </div>
            ` : '';

            let receiptHTML = `
                <div class="receipt-header">📋 RESI PENGIRIMAN</div>
                <div class="receipt-item">
                    <span>Nomor Invoice:</span>
                    <strong>${invoiceNumber}</strong>
                </div>
                <div class="receipt-item">
                    <span>Tanggal Pesanan:</span>
                    <strong>${formatDate(order.timestamp)}</strong>
                </div>
                <div class="receipt-item">
                    <span>Penerima:</span>
                    <strong>${customer}</strong>
                </div>
                <div class="receipt-item">
                    <span>Alamat:</span>
                    <strong>${address.address || '-'}</strong>
                </div>
                <div class="receipt-item">
                    <span>Kota:</span>
                    <strong>${address.city || '-'}</strong>
                </div>
                <div class="receipt-item">
                    <span>Provinsi:</span>
                    <strong>${address.province || '-'}</strong>
                </div>
                <div class="receipt-item">
                    <span>Kode Pos:</span>
                    <strong>${address.postal_code || '-'}</strong>
                </div>
                <div class="receipt-item">
                    <span>Telepon:</span>
                    <strong>${address.phone || '-'}</strong>
                </div>
                ${deliveryInfoHtml}
                <div class="receipt-item">
                    <span>Toko Pengirim:</span>
                    <strong>${storeName}</strong>
                </div>
                <div style="border-top: 2px dashed #cbd5e0; margin: 12px 0; padding-top: 12px;">
                    <div class="receipt-item">
                        <span>Total Biaya:</span>
                        <strong>${formatRupiah(order.total || 0)}</strong>
                    </div>
                    <div class="receipt-item">
                        <span>Status Pengiriman:</span>
                        <strong>${statusLabel}</strong>
                    </div>
                </div>
                <div style="text-align: center; margin-top: 16px; font-size: 12px; color: #888;">
                    Dicetak: ${new Date().toLocaleString('id-ID')}<br>
                    Terima kasih telah berbelanja!
                </div>
            `;

            document.getElementById('receiptContent').innerHTML = receiptHTML;
            document.getElementById('receiptModal').classList.add('active');
        }

        function closeReceiptModal() {
            document.getElementById('receiptModal').classList.remove('active');
        }

        function printReceipt() {
            window.print();
        }

        function openPaymentModal(index) {
            currentEditIndex = index;
            const order = orders[index];
            const invoiceNumber = buildOrderNumber(order, index);
            document.getElementById('paymentInvoice').value = invoiceNumber;
            document.getElementById('paymentStatusSelect').value = order.paymentStatus || 'notdone';
            document.getElementById('paymentModal').classList.add('active');
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').classList.remove('active');
            currentEditIndex = -1;
        }

        function savePaymentStatus() {
            if (currentEditIndex === -1) return;
            const order = orders[currentEditIndex];
            const newPaymentStatus = document.getElementById('paymentStatusSelect').value;
            const orderId = order.order_id || order.orderId;

            fetch('../database/update_order_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    order_id: orderId,
                    payment_status: newPaymentStatus
                })
            })
            .then(async response => {
                const text = await response.text();
                try {
                    return JSON.parse(text);
                } catch (err) {
                    console.error('Invalid JSON response:', text);
                    throw new Error(text || err.message);
                }
            })
            .then(data => {
                if (data.ok) {
                    order.paymentStatus = newPaymentStatus;
                    order.paymentStatusUpdated = new Date().toISOString();
                    renderOrders();
                    alert('Status pembayaran berhasil diperbarui');
                } else {
                    console.error('Error saving payment status:', data.error);
                    alert('Gagal menyimpan status pembayaran: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Fetch error saving payment status:', error);
                alert('Error menyimpan status pembayaran: ' + error.message);
            })
            .finally(() => {
                closePaymentModal();
            });
        }

        function openAssignModal(index) {
            currentEditIndex = index;
            const order = orders[index];
            const invoiceNumber = buildOrderNumber(order, index);
            document.getElementById('assignInvoice').value = invoiceNumber;
            
            // Fetch available delivery personnel
            fetch('./assign_delivery.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_available'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const select = document.getElementById('deliverySelect');
                    select.innerHTML = '<option value="">-- Pilih Pengirim --</option>';
                    data.data.forEach(driver => {
                        const option = document.createElement('option');
                        option.value = driver.id;
                        option.textContent = `${driver.driver_name} (${driver.vehicle_number}) - ${driver.phone_number}`;
                        select.appendChild(option);
                    });
                    // Set current if already assigned
                    if (order.delivery_personnel_id) {
                        select.value = order.delivery_personnel_id;
                    }
                } else {
                    alert('Gagal memuat data pengirim');
                }
            })
            .catch(error => {
                console.error('Error fetching drivers:', error);
                alert('Error: ' + error.message);
            });
            
            document.getElementById('assignModal').classList.add('active');
        }

        function closeAssignModal() {
            document.getElementById('assignModal').classList.remove('active');
            currentEditIndex = -1;
        }

        function saveAssignment() {
            if (currentEditIndex === -1) return;
            
            const order = orders[currentEditIndex];
            const deliveryPersonnelId = document.getElementById('deliverySelect').value;
            
            if (!deliveryPersonnelId) {
                alert('Pilih pengirim terlebih dahulu');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'assign');
            formData.append('order_id', order.order_id || order.orderId);
            formData.append('delivery_personnel_id', deliveryPersonnelId);

            fetch('./assign_delivery.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update local order
                    order.delivery_personnel_id = parseInt(deliveryPersonnelId);
                    alert('Pengirim berhasil di-assign!');
                    closeAssignModal();
                    renderOrders();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving assignment: ' + error.message);
            });
        }

        function deleteOrder(index) {
            if (!confirm('Yakin ingin menghapus pesanan ini?')) return;
            const order = orders[index];
            const orderId = order.order_id || order.orderId;

            fetch('../database/delete_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'order_id=' + encodeURIComponent(orderId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    orders.splice(index, 1);
                    renderOrders();
                    alert('Pesanan berhasil dihapus dari database');
                } else {
                    alert('Gagal menghapus pesanan: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error deleting order:', error);
                alert('Error menghapus pesanan: ' + error.message);
            });
        }

        function renderOrders() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            filteredOrders = orders.map((order, index) => ({ ...order, _index: index })).filter(order => {
                const invoiceNumber = buildOrderNumber(order, order._index).toLowerCase();
                const customer = getCustomerLabel(order).toLowerCase();
                return invoiceNumber.includes(search) || customer.includes(search);
            });

            const totalPages = Math.ceil(filteredOrders.length / itemsPerPage);
            if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            const startIdx = (currentPage - 1) * itemsPerPage;
            const endIdx = startIdx + itemsPerPage;
            const pageOrders = filteredOrders.slice(startIdx, endIdx);

            const tbody = document.getElementById('ordersTableBody');
            if (filteredOrders.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7"><div class="no-data"><i class="bx bx-inbox"></i><p>Tidak ada pesanan ditemukan</p></div></td></tr>';
            } else {
                tbody.innerHTML = pageOrders.map(order => {
                    const status = order.status || 'pending';
                    const paymentStatus = order.paymentStatus || 'notdone';
                    return `
                        <tr>
                            <td><strong>${buildOrderNumber(order, order._index)}</strong></td>
                            <td>${getCustomerLabel(order)}</td>
                            <td>${formatDate(order.timestamp)}</td>
                            <td>${formatRupiah(order.total || 0)}</td>
                                        <td>${getPaymentStatusBadge(paymentStatus)}</td>
                            <td>${getStatusBadge(status)}</td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn-status" onclick="openStatusModal(${order._index})" title="Ubah Status">
                                        <i class='bx bx-edit'></i> Status
                                    </button>
                                    <button class="action-btn-status" onclick="openAssignModal(${order._index})" title="Assign Pengirim" style="background: #fef3c7; color: #b45309;">
                                        <i class='bx bxs-truck'></i> Pengirim
                                    </button>
                                    <button class="action-btn-print" onclick="openReceiptModal(${order._index})" title="Cetak Resi">
                                        <i class='bx bx-printer'></i> Resi
                                    </button>
                                    <button class="action-btn-delete" onclick="deleteOrder(${order._index})" title="Hapus">
                                        <i class='bx bx-trash'></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                }).join('');
            }

            document.getElementById('totalInfo').textContent = filteredOrders.length;
            document.getElementById('pageInfo').textContent = filteredOrders.length === 0 ? '0 - 0' : `${startIdx + 1} - ${Math.min(endIdx, filteredOrders.length)}`;

            renderPageNumbers();
        }

        function renderPageNumbers() {
            const totalPages = Math.ceil(filteredOrders.length / itemsPerPage);
            const container = document.getElementById('pageNumbers');
            container.innerHTML = '';
            
            for (let i = 1; i <= totalPages; i++) {
                const btn = document.createElement('button');
                btn.className = `pagination-btn ${i === currentPage ? 'active' : ''}`;
                btn.textContent = i;
                btn.onclick = () => { currentPage = i; renderOrders(); };
                container.appendChild(btn);
            }
        }

        function previousPage() {
            if (currentPage > 1) {
                currentPage--;
                renderOrders();
            }
        }

        function nextPage() {
            const totalPages = Math.ceil(filteredOrders.length / itemsPerPage);
            if (currentPage < totalPages) {
                currentPage++;
                renderOrders();
            }
        }

        document.getElementById('searchInput').addEventListener('input', () => {
            currentPage = 1;
            renderOrders();
        });

        document.addEventListener('DOMContentLoaded', renderOrders);

        // Close modals on background click
        document.getElementById('statusModal').addEventListener('click', (e) => {
            if (e.target.id === 'statusModal') closeStatusModal();
        });
        document.getElementById('receiptModal').addEventListener('click', (e) => {
            if (e.target.id === 'receiptModal') closeReceiptModal();
        });
        document.getElementById('paymentModal').addEventListener('click', (e) => {
            if (e.target.id === 'paymentModal') closePaymentModal();
        });
        document.getElementById('assignModal').addEventListener('click', (e) => {
            if (e.target.id === 'assignModal') closeAssignModal();
        });
    </script>
        <script src="../js/dashboard.js"></script>
</body>
</html>
