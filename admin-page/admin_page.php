<?php
session_start();
require_once __DIR__ . '/../database/config.php';

if(!isset($_SESSION['admin_name'])){
   header('location:index.php');
   exit();
}

$admin = $_SESSION['admin_name'];

$totalUsers = 0;
$totalProducts = 0;
$totalCategories = 0;
$totalOrders = 0;
$totalRevenue = 0;
$ordersTableExists = false;

if ($conn) {
    $result = $conn->query('SELECT COUNT(*) AS count FROM users');
    if ($result) {
        $row = $result->fetch_assoc();
        $totalUsers = intval($row['count']);
    }

    $result = $conn->query('SELECT COUNT(*) AS count FROM products');
    if ($result) {
        $row = $result->fetch_assoc();
        $totalProducts = intval($row['count']);
    }

    $result = $conn->query('SELECT COUNT(*) AS count FROM categories');
    if ($result) {
        $row = $result->fetch_assoc();
        $totalCategories = intval($row['count']);
    }

    $result = $conn->query("SHOW TABLES LIKE 'orders'");
    if ($result && $result->num_rows > 0) {
        $ordersTableExists = true;
        $orderResult = $conn->query('SELECT COUNT(*) AS count, COALESCE(SUM(total),0) AS revenue FROM orders');
        if ($orderResult) {
            $row = $orderResult->fetch_assoc();
            $totalOrders = intval($row['count']);
            $totalRevenue = intval($row['revenue']);
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard Furnitur</title>
    <!-- Icons (Boxicons) -->
    <link href='https://cdn.jsdelivr.net/npm/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <!-- Charts (Chart.js) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">   
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .dashboard-controls {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .dashboard-controls select {
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 10px 14px;
            background: #fff;
            color: #334155;
            font-size: 0.95rem;
        }
        .card-full {
            grid-column: 1 / -1;
        }
        .card-summary .summary-content {
            display: grid;
            gap: 16px;
            margin-top: 16px;
        }
        .summary-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px 20px;
        }
        .summary-title {
            color: #64748b;
            font-size: 0.85rem;
            margin-bottom: 8px;
        }
        .summary-value {
            color: #0f172a;
            font-size: 1.25rem;
            font-weight: 700;
        }
        .hero-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            width: 100%;
        }
        .side-panel {
            display: grid;
            gap: 25px;
        }
        .panel-list {
            display: grid;
            gap: 12px;
            margin-top: 12px;
        }
        .payment-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            font-size: 14px;
            color: #334155;
        }
        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .user-table th,
        .user-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            font-size: 14px;
            color: #334155;
        }
        .user-table th {
            background: #f8fafc;
            font-weight: 600;
        }
        .user-table tbody tr:last-child td {
            border-bottom: none;
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

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <header>
            <div class="header-title">Dashboard Overview</div>
            <div class="user-profile">
                <div>
                    <span style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($admin); ?></span>
                    <br>
                    <span style="font-size: 12px; color: #888;">Super Admin</span>
                </div>
                <div class="avatar">
                    <i class='bx bxs-user'></i>
                </div>
            </div>
        </header>

        <!-- Overview metrics -->
        <div class="stats-grid hero-stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Pengguna</h3>
                    <p id="metricUsers"><?php echo number_format($totalUsers); ?></p>
                </div>
                <div class="stat-icon">
                    <i class='bx bxs-user-account'></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Pesanan</h3>
                    <p id="metricOrders"><?php echo number_format($totalOrders); ?></p>
                </div>
                <div class="stat-icon">
                    <i class='bx bxs-shopping-bags'></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Pendapatan</h3>
                    <p id="metricRevenue">Rp <?php echo number_format($totalRevenue); ?></p>
                </div>
                <div class="stat-icon">
                    <i class='bx bxs-wallet'></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Pesanan Aktif</h3>
                    <p id="metricActiveOrders">-</p>
                </div>
                <div class="stat-icon">
                    <i class='bx bxs-time-five'></i>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Trafik Penjualan</h3>
                        <p class="card-subtitle">Lihat tren pesanan dan pendapatan per bulan atau per hari.</p>
                    </div>
                    <div class="dashboard-controls">
                        <select id="dashboardViewSelect">
                            <option value="monthly">Per Bulan</option>
                            <option value="daily">Per Hari</option>
                        </select>
                        <select id="dashboardYearSelect">
                            <?php for ($year = date('Y'); $year >= date('Y') - 2; $year--): ?>
                                <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="chart-container" style="min-height: 340px;">
                    <canvas id="salesChart" height="320"></canvas>
                </div>
                <div class="sales-summary-grid">
                    <div class="metric-box">
                        <div class="metric-label">Produk Terlaris</div>
                        <div class="metric-value" id="topProductText">-</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-label">Total Produk</div>
                        <div class="metric-value" id="totalProductsText"><?php echo number_format($totalProducts); ?></div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-label">Pendapatan Tahun Ini</div>
                        <div class="metric-value" id="yearRevenueText">Rp <?php echo number_format($totalRevenue); ?></div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-label">Jumlah Kategori</div>
                        <div class="metric-value" id="totalCategoriesText"><?php echo number_format($totalCategories); ?></div>
                    </div>
                </div>
            </div>

            <div class="side-panel">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Pendapatan & Metode Pembayaran</h3>
                    </div>
                    <div class="chart-container" style="min-height: 260px; margin-bottom: 18px;">
                        <canvas id="revenueChart" height="260"></canvas>
                    </div>
                    <div class="panel-list" id="paymentMethodsList">
                        <div class="payment-row">
                            <span>Tidak ada data metode pembayaran</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Pengguna Terbaru</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="user-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody id="recentUsersBody">
                                <tr>
                                    <td colspan="3">Memuat data pengguna...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        const salesCanvas = document.getElementById('salesChart');
        const revenueCanvas = document.getElementById('revenueChart');
        const viewSelect = document.getElementById('dashboardViewSelect');
        const yearSelect = document.getElementById('dashboardYearSelect');
        const metricUsersText = document.getElementById('metricUsers');
        const metricOrdersText = document.getElementById('metricOrders');
        const metricRevenueText = document.getElementById('metricRevenue');
        const metricActiveOrdersText = document.getElementById('metricActiveOrders');
        const topProductText = document.getElementById('topProductText');
        const totalProductsText = document.getElementById('totalProductsText');
        const yearRevenueText = document.getElementById('yearRevenueText');
        const totalCategoriesText = document.getElementById('totalCategoriesText');
        const paymentMethodsList = document.getElementById('paymentMethodsList');
        const recentUsersBody = document.getElementById('recentUsersBody');
        let salesChart = null;
        let revenueChart = null;

        function formatRupiah(value) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
        }

        function createChart(canvas, labels, data, label, strokeColor) {
            if (!canvas) return null;
            const ctx = canvas.getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, 'rgba(56, 189, 248, 0.35)');
            gradient.addColorStop(1, 'rgba(56, 189, 248, 0)');
            return new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label,
                        data,
                        fill: true,
                        backgroundColor: gradient,
                        borderColor: strokeColor,
                        borderWidth: 3,
                        pointBackgroundColor: strokeColor,
                        pointRadius: 4,
                        tension: 0.35
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true, position: 'top' },
                        tooltip: { mode: 'index', intersect: false }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(15, 23, 42, 0.05)' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        async function loadDashboardData() {
            const year = yearSelect.value;
            try {
                const response = await fetch(`dashboard_data.php?year=${year}`);
                if (!response.ok) {
                    throw new Error('Failed to load dashboard data');
                }
                const data = await response.json();
                const mode = viewSelect.value;
                renderSummary(data);
                renderPaymentMethods(data);
                renderRecentUsers(data);
                renderCharts(data, mode);
            } catch (error) {
                console.error(error);
            }
        }

        function renderSummary(data) {
            const totalRevenue = Number(data.totalRevenue || 0);
            const totalOrders = Number(data.totalOrders || 0);
            const activeOrders = Number(data.activeOrders || 0);
            const averageValue = totalOrders > 0 ? Math.round(totalRevenue / totalOrders) : 0;

            metricUsersText.textContent = new Intl.NumberFormat('id-ID').format(data.totalUsers || 0);
            metricOrdersText.textContent = new Intl.NumberFormat('id-ID').format(totalOrders);
            metricRevenueText.textContent = formatRupiah(totalRevenue);
            metricActiveOrdersText.textContent = new Intl.NumberFormat('id-ID').format(activeOrders);
            totalProductsText.textContent = new Intl.NumberFormat('id-ID').format(data.totalProducts || 0);
            yearRevenueText.textContent = formatRupiah(totalRevenue);
            totalCategoriesText.textContent = new Intl.NumberFormat('id-ID').format(data.totalCategories || 0);

            if (data.topProducts && data.topProducts.length > 0) {
                topProductText.textContent = data.topProducts[0].name;
            } else {
                topProductText.textContent = '-';
            }
        }

        function renderPaymentMethods(data) {
            const methods = Array.isArray(data.paymentMethods) ? data.paymentMethods : [];
            if (methods.length === 0) {
                paymentMethodsList.innerHTML = '<div class="payment-row"><span>Tidak ada data metode pembayaran</span></div>';
                return;
            }
            paymentMethodsList.innerHTML = methods.map(method => {
                const percentage = data.totalOrders > 0 ? Math.round((method.count / data.totalOrders) * 100) : 0;
                return `<div class="payment-row"><span>${method.method}</span><strong>${method.count} (${percentage}%)</strong></div>`;
            }).join('');
        }

        function renderRecentUsers(data) {
            const users = Array.isArray(data.recentUsers) ? data.recentUsers : [];
            if (users.length === 0) {
                recentUsersBody.innerHTML = '<tr><td colspan="3">Belum ada pengguna terbaru.</td></tr>';
                return;
            }
            recentUsersBody.innerHTML = users.map((user, index) => `
                <tr>
                    <td>${index + 1}</td>
                    <td>${user.name || '-'}</td>
                    <td>${user.email || '-'}</td>
                </tr>
            `).join('');
        }

        function renderCharts(data, mode) {
            const labels = mode === 'daily'
                ? data.dailyOrders.map((_, idx) => idx + 1)
                : data.labels;
            const orderData = mode === 'daily' ? data.dailyOrders : data.monthlyOrders;
            const revenueData = mode === 'daily' ? data.dailyRevenue : data.monthlyRevenue;

            if (salesChart) {
                salesChart.destroy();
            }
            if (revenueChart) {
                revenueChart.destroy();
            }

            salesChart = createChart(salesCanvas, labels, orderData, mode === 'daily' ? 'Pesanan per Hari' : 'Pesanan per Bulan', '#38bdf8');
            revenueChart = createChart(revenueCanvas, labels, revenueData, mode === 'daily' ? 'Pendapatan per Hari' : 'Pendapatan per Bulan', '#f97316');
        }

        function toggleSubmenu(element) {
            const submenu = element.nextElementSibling;
            const arrow = element.querySelector('.arrow');
            if (submenu) {
                submenu.classList.toggle('show');
            }
            if (arrow) {
                arrow.classList.toggle('rotate');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (viewSelect && yearSelect && salesCanvas && revenueCanvas) {
                loadDashboardData();
                viewSelect.addEventListener('change', loadDashboardData);
                yearSelect.addEventListener('change', loadDashboardData);
                setInterval(loadDashboardData, 60000);
            }
        });
    </script>
</body>
</html>