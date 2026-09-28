<?php
session_start();
require_once __DIR__ . '/../database/config.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ../index.php');
    exit();
}
$admin = $_SESSION['admin_name'];

$ordersData = [];
$tableCheck = $conn->query("SHOW TABLES LIKE 'orders'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $stmt = $conn->prepare("SELECT * FROM orders ORDER BY timestamp DESC");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if (isset($row['items']) && is_string($row['items'])) {
                $row['items'] = json_decode($row['items'], true) ?: [];
            }
            if (isset($row['address']) && is_string($row['address'])) {
                $row['address'] = json_decode($row['address'], true) ?: [];
            }
            if (isset($row['payment_result']) && is_string($row['payment_result'])) {
                $row['payment_result'] = json_decode($row['payment_result'], true) ?: [];
            }

            $row['orderId'] = $row['order_id'];
            $row['userId'] = $row['user_id'];
            $row['paymentStatus'] = $row['payment_status'];
            $row['paymentMethod'] = $row['payment_method'];
            $row['createdAt'] = $row['created_at'];

            $ordersData[] = $row;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pemasukan</title>
    <link href='https://cdn.jsdelivr.net/npm/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .container { width: min(1280px, 100%); max-width: 100%; margin: 20px auto; padding: 0 20px; min-width: 0; }
        .report-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 24px; min-width: 0; }
        .report-title { color: #142850; font-size: 28px; font-weight: 700; }
        .report-subtitle { color: #475569; }
        .report-summary { display: grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .report-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; box-shadow: 0 8px 30px rgba(15, 23, 42, 0.06); }
        .report-card h3 { margin: 0 0 12px; font-size: 16px; color: #0f172a; }
        .report-card p { margin: 0; font-size: 28px; font-weight: 700; color: #0f172a; }
        .report-actions { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
        .report-actions button, .report-actions input { border-radius: 10px; }
        .report-actions button { background: #142850; color: #fff; border: none; padding: 12px 20px; cursor: pointer; }
        .report-actions input { border: 1px solid #cbd5e0; padding: 12px 16px; width: 100%; max-width: 320px; }
        .table-responsive { width: 100%; overflow-x: auto; max-width: 100%; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; min-width: 0; }
        table { width: 100%; max-width: 100%; border-collapse: collapse; min-width: 0; table-layout: fixed; }
        table th, table td { padding: 14px 16px; border-bottom: 1px solid #e2e8f0; text-align: left; word-break: break-word; white-space: normal; min-width: 0; }
        table th { background: #f8fafc; color: #334155; font-weight: 700; }
        table tbody tr:hover { background: #f8fafc; }
        .report-footer { display: flex; justify-content: flex-end; align-items: center; gap: 16px; margin-top: 16px; }
        .report-footer strong { font-size: 1.15rem; }
        .note-card { margin-top: 16px; padding: 18px 20px; background: #f8fafc; border-left: 4px solid #0f172a; border-radius: 12px; }
        @media (max-width: 900px) {
            .report-summary { grid-template-columns: 1fr 1fr; }
            .report-actions { flex-direction: column; align-items: stretch; }
        }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .sidebar, .nav-links, .header, .report-actions, .note-card { display: none !important; }
            .main-content { margin-left: 0 !important; }
        }
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
            <div class="header-title">Laporan Pemasukan</div>
            <div class="user-profile">
                <div>
                    <span style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($admin); ?></span><br>
                    <span style="font-size: 12px; color: #888;">Admin</span>
                </div>
                <div class="avatar"><i class='bx bxs-user'></i></div>
            </div>
        </header>
        <div class="container">
            <div class="report-header">
                <div>
                    <div class="report-title">Laporan Keuangan</div>
                    <div class="report-subtitle">Ringkasan transaksi penjualan dan total pembayaran untuk laporan keuangan.</div>
                </div>
                <div class="no-print">
                    <button type="button" onclick="window.print()">Cetak PDF</button>
                </div>
            </div>
            <div class="report-summary">
                <div class="report-card">
                    <h3>Total Transaksi</h3>
                    <p id="totalOrders">0</p>
                </div>
                <div class="report-card">
                    <h3>Total Pembayaran</h3>
                    <p id="grandTotal">Rp 0</p>
                </div>
                <div class="report-card">
                    <h3>Diskon Total</h3>
                    <p id="grandDiscount">Rp 0</p>
                </div>
                <div class="report-card">
                    <h3>Ongkir Total</h3>
                    <p id="grandShipping">Rp 0</p>
                </div>
            </div>
            <div class="report-actions no-print">
                <input type="text" id="searchInput" placeholder="Cari nomor pesanan atau pelanggan..." oninput="renderOrders()">
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No. Pesanan</th>
                            <th>Tanggal</th>
                            <th>Pelanggan</th>
                            <th>Subtotal</th>
                            <th>Diskon</th>
                            <th>Ongkir</th>
                            <th>Total Pembayaran</th>
                        </tr>
                    </thead>
                    <tbody id="orderTableBody"></tbody>
                </table>
            </div>
            <div class="report-footer no-print">
                <strong>Total Semua Pembayaran:</strong>
                <span id="totalPaymentFooter">Rp 0</span>
            </div>
            <div class="note-card">
                <strong>Catatan:</strong> Data pesanan diambil dari database server. Laporan akan menampilkan semua transaksi yang tersimpan di basis data.
            </div>
        </div>
    </div>
    <script src="../js/dashboard.js"></script>
    <script>
        const orders = <?php echo json_encode($ordersData); ?>;

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

        function renderOrders() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const tbody = document.getElementById('orderTableBody');
            let totalPayment = 0;
            let totalDiscount = 0;
            let totalShipping = 0;
            const filtered = orders.map((order, index) => {
                const total = Number(order.total || 0);
                const discount = Number(order.discount || 0);
                const shipping = Number(order.shipping || 0);
                const subtotal = total + discount - shipping;
                return {
                    orderNumber: buildOrderNumber(order, index),
                    date: formatDate(order.timestamp),
                    customer: getCustomerLabel(order),
                    subtotal,
                    discount,
                    shipping,
                    total
                };
            }).filter(order => {
                return !search || order.orderNumber.toLowerCase().includes(search) || order.customer.toLowerCase().includes(search);
            });

            if (filtered.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 24px; color:#64748b;">Tidak ada transaksi ditemukan.</td></tr>';
            } else {
                tbody.innerHTML = filtered.map(order => {
                    totalPayment += order.total;
                    totalDiscount += order.discount;
                    totalShipping += order.shipping;
                    return `
                        <tr>
                            <td>${order.orderNumber}</td>
                            <td>${order.date}</td>
                            <td>${order.customer}</td>
                            <td>${formatRupiah(order.subtotal)}</td>
                            <td>${formatRupiah(order.discount)}</td>
                            <td>${formatRupiah(order.shipping)}</td>
                            <td>${formatRupiah(order.total)}</td>
                        </tr>
                    `;
                }).join('');
            }

            document.getElementById('totalOrders').textContent = orders.length;
            document.getElementById('grandTotal').textContent = formatRupiah(totalPayment);
            document.getElementById('grandDiscount').textContent = formatRupiah(totalDiscount);
            document.getElementById('grandShipping').textContent = formatRupiah(totalShipping);
            document.getElementById('totalPaymentFooter').textContent = formatRupiah(totalPayment);
        }

        document.addEventListener('DOMContentLoaded', renderOrders);
    </script>
</body>
</html>
