<?php
session_start();
require_once __DIR__ . '/../database/config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';

// Fetch all orders for the logged-in user
$orders = [];
$orderTableCheck = $conn->query("SHOW TABLES LIKE 'orders'");
if ($orderTableCheck && $orderTableCheck->num_rows > 0) {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY timestamp DESC");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Decode tracking data if available
            if (!empty($row['tracking_data']) && is_string($row['tracking_data'])) {
                $decodedTracking = json_decode($row['tracking_data'], true);
                $row['tracking_data'] = is_array($decodedTracking) ? $decodedTracking : null;
            } else {
                $row['tracking_data'] = null;
            }
            
            // Fetch delivery personnel if assigned
            if (!empty($row['delivery_personnel_id'])) {
                $dpStmt = $conn->prepare("SELECT * FROM delivery_personnel WHERE id = ? LIMIT 1");
                $dpStmt->bind_param('i', $row['delivery_personnel_id']);
                $dpStmt->execute();
                $dpResult = $dpStmt->get_result();
                $row['delivery_personnel'] = $dpResult->fetch_assoc();
                $dpStmt->close();
            }
            $orders[] = $row;
        }
        $stmt->close();
    }
}

// Define status categories
$statusCategories = [
    'all' => ['label' => 'Semua', 'icon' => 'bx-list-check'],
    'pending' => ['label' => 'Belum Bayar', 'icon' => 'bx-time', 'payment' => ['notdone', 'pending', 'cod']],
    'processing' => ['label' => 'Sedang Dikemas', 'icon' => 'bx-package', 'payment' => ['success', 'paid'], 'order' => ['processing', 'pending']],
    'shipped' => ['label' => 'Dikirim', 'icon' => 'bx-truck', 'order' => ['ready', 'dikirim', 'siap_dikirim', 'on_delivery']],
    'completed' => ['label' => 'Selesai', 'icon' => 'bx-check-circle', 'order' => ['completed', 'delivered', 'selesai', 'finished']]
];

$currentFilter = $_GET['filter'] ?? 'all';
$filteredOrders = $orders;

if ($currentFilter !== 'all') {
    $filteredOrders = array_filter($orders, function($order) use ($currentFilter) {
        if ($currentFilter === 'pending') {
            return in_array($order['payment_status'], ['notdone', 'pending', 'cod'], true);
        } elseif ($currentFilter === 'processing') {
            return in_array($order['payment_status'], ['success', 'paid'], true) && in_array($order['status'], ['processing', 'pending'], true);
        } elseif ($currentFilter === 'shipped' || $currentFilter === 'ready') {
            return in_array($order['status'], ['ready', 'dikirim', 'siap_dikirim', 'on_delivery'], true);
        } elseif ($currentFilter === 'completed') {
            return in_array($order['status'], ['completed', 'delivered', 'selesai', 'finished'], true);
        }
        return true;
    });
}

function formatRupiah($value) {
    return 'Rp ' . number_format((int)$value, 0, ',', '.');
}

function formatDate($date) {
    if (!$date) return '-';
    return date('d M Y H:i', strtotime($date));
}

function getStatusBadge($paymentStatus, $orderStatus) {
    if (in_array($paymentStatus, ['notdone', 'pending', 'cod'], true)) {
        return ['class' => 'badge-warning', 'text' => 'Belum Bayar'];
    }
    if (in_array($orderStatus, ['pending'], true)) {
        return ['class' => 'badge-info', 'text' => 'Diproses'];
    }
    if (in_array($orderStatus, ['processing'], true)) {
        return ['class' => 'badge-info', 'text' => 'Sedang Dikemas'];
    }
    if (in_array($orderStatus, ['ready', 'dikirim', 'siap_dikirim', 'on_delivery'], true)) {
        return ['class' => 'badge-success', 'text' => 'Dikirim'];
    }
    if (in_array($orderStatus, ['delivered', 'completed', 'selesai', 'finished'], true)) {
        return ['class' => 'badge-success', 'text' => 'Selesai'];
    }
    if (in_array($orderStatus, ['rejected', 'ditolak'], true)) {
        return ['class' => 'badge-warning', 'text' => 'Ditolak'];
    }
    return ['class' => 'badge-secondary', 'text' => ucfirst(str_replace('_', ' ', $orderStatus))];
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="author" content="Offo Living">
    <link rel="shortcut icon" href="../logo.png">
    <meta name="description" content="Pesanan Saya - Offo Living">
    <meta name="keywords" content="pesanan, tracking, furniture">
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link href="../css/tiny-slider.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <title>Pesanan Saya - Offo Living</title>
    <style>
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; }
        .pesanan-container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .pesanan-header { background: white; padding: 30px 40px; border-radius: 16px; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .pesanan-title { font-size: 32px; font-weight: 800; color: #142850; margin-bottom: 8px; }
        .pesanan-subtitle { color: #64748b; font-size: 16px; }
        .status-filter { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 30px; }
        .status-filter-btn { 
            display: flex; align-items: center; gap: 8px; padding: 12px 20px; border: 2px solid #e2e8f0; 
            background: white; border-radius: 12px; cursor: pointer; font-weight: 600; 
            color: #475569; transition: all 0.3s ease; text-decoration: none;
        }
        .status-filter-btn:hover { border-color: #142850; color: #142850; background: #f8fafc; }
        .status-filter-btn.active { 
            background: #142850; color: white; border-color: #142850; 
            box-shadow: 0 10px 25px rgba(20, 40, 80, 0.2);
        }
        .status-filter-btn i { font-size: 18px; }
        .orders-grid { display: grid; gap: 20px; }
        .order-card { 
            background: white; border-radius: 16px; overflow: hidden; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.08); transition: all 0.3s ease; 
            border-left: 5px solid #00ff33;
        }
        .order-card:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(0,0,0,0.12); }
        .order-header { 
            display: flex; justify-content: space-between; align-items: center; 
            padding: 20px 24px; border-bottom: 1px solid #e2e8f0; background: #f8fafc;
        }
        .order-info { display: flex; flex-direction: column; gap: 4px; }
        .order-number { font-size: 14px; color: #64748b; }
        .order-number strong { color: #142850; font-weight: 700; }
        .order-date { font-size: 13px; color: #94a3b8; }
        .order-status-badge { display: inline-block; }
        .badge-warning { background: #fef3c7; color: #92400e; padding: 8px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-info { background: #dbeafe; color: #1e40af; padding: 8px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #d1fae5; color: #065f46; padding: 8px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-secondary { background: #e2e8f0; color: #475569; padding: 8px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .order-body { padding: 20px 24px; }
        .order-items { margin-bottom: 16px; }
        .order-item { display: flex; gap: 12px; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; }
        .order-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .order-item-img { width: 60px; height: 60px; background: #f1f5f9; border-radius: 8px; overflow: hidden; }
        .order-item-img img { width: 100%; height: 100%; object-fit: cover; }
        .order-item-info { flex: 1; }
        .order-item-name { font-size: 14px; font-weight: 600; color: #142850; margin-bottom: 4px; }
        .order-item-meta { font-size: 12px; color: #64748b; }
        .order-summary { background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 16px; }
        .order-summary-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 14px; }
        .order-summary-row:last-child { margin-bottom: 0; border-top: 1px solid #e2e8f0; padding-top: 8px; }
        .order-summary-row.total { font-size: 18px; font-weight: 700; color: #142850; }
        .order-summary-label { color: #64748b; }
        .order-summary-value { font-weight: 600; color: #142850; }
        .order-footer { display: flex; justify-content: space-between; align-items: center; }
        .order-status-progress { flex: 1; }
        .progress-stage { display: flex; gap: 8px; align-items: center; font-size: 12px; color: #64748b; }
        .progress-dot { width: 8px; height: 8px; border-radius: 50%; background: #cbd5e0; }
        .progress-dot.active { background: #00ff33; }
        .progress-line { width: 20px; height: 2px; background: #cbd5e0; }
        .progress-line.active { background: #00ff33; }
        .order-actions { display: flex; gap: 8px; }
        .order-action-btn { padding: 8px 14px; border: 1px solid #cbd5e0; background: white; color: #475569; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; transition: all 0.2s; }
        .order-action-btn:hover { background: #f1f5f9; border-color: #142850; color: #142850; }
        .empty-state { text-align: center; padding: 60px 40px; }
        .empty-state-icon { font-size: 64px; color: #cbd5e0; margin-bottom: 16px; }
        .empty-state-title { font-size: 24px; font-weight: 700; color: #142850; margin-bottom: 8px; }
        .empty-state-text { color: #64748b; margin-bottom: 24px; }
        .empty-state-btn { display: inline-block; padding: 12px 28px; background: #142850; color: white; border-radius: 8px; text-decoration: none; font-weight: 600; }
        @media (max-width: 768px) {
            .pesanan-container { margin: 20px auto; }
            .order-header { flex-direction: column; align-items: flex-start; gap: 12px; }
            .status-filter { gap: 8px; }
            .status-filter-btn { padding: 10px 16px; font-size: 12px; }
            .order-footer { flex-direction: column; gap: 12px; }
        }
    </style>
</head>
<body>
   <!-- Start Header/Navigation -->
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

					<li class="nav-item">
						<a class="nav-link" href="user_page.php">Home</a>
					</li>

					<li class="nav-item">
						<a class="nav-link" href="shop.php">Shop</a>
					</li>

					<li class="nav-item">
						<a class="nav-link" href="about.php">About Us</a>
					</li>

					<li class="nav-item active">
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
		<!-- End Header/Navigation -->
    <div class="pesanan-container">
        <!-- Header -->
        <div class="pesanan-header">
            <h1 class="pesanan-title">Pesanan Saya</h1>
            <p class="pesanan-subtitle">Kelola dan pantau status pesanan Anda di sini</p>
        </div>

        <!-- Status Filter -->
        <div class="status-filter">
            <?php foreach ($statusCategories as $key => $cat): ?>
                <a href="?filter=<?php echo $key; ?>" 
                   class="status-filter-btn <?php echo ($currentFilter === $key) ? 'active' : ''; ?>">
                    <i class="bx <?php echo $cat['icon']; ?>"></i>
                    <?php echo $cat['label']; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Orders Grid -->
        <?php if (empty($filteredOrders)): ?>
            <div class="order-card">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fa fa-inbox"></i></div>
                    <h3 class="empty-state-title">Belum Ada Pesanan</h3>
                    <p class="empty-state-text">Anda belum memiliki pesanan di kategori ini. Mulai berbelanja sekarang!</p>
                    <a href="shop.php" class="empty-state-btn">Belanja Sekarang</a>
                </div>
            </div>
        <?php else: ?>
            <div class="orders-grid">
                <?php foreach ($filteredOrders as $order): 
                    $badge = getStatusBadge($order['payment_status'], $order['status']);
                    $items = json_decode($order['items'] ?? '[]', true);
                    $address = json_decode($order['address'] ?? '{}', true);
                ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-info">
                                <div class="order-number">
                                    Nomor Pesanan: <strong><?php echo htmlspecialchars($order['order_id'] ?: ('ID-' . $order['id'])); ?></strong>
                                </div>
                                <div class="order-date">
                                    <?php echo formatDate($order['timestamp']); ?>
                                </div>
                            </div>
                            <div class="order-status-badge">
                                <span class="badge-<?php echo $badge['class']; ?>">
                                    <?php echo $badge['text']; ?>
                                </span>
                            </div>
                        </div>

                        <div class="order-body">
                            <!-- Order Items -->
                            <?php if (!empty($items)): ?>
                                <div class="order-items">
                                    <?php foreach (array_slice($items, 0, 2) as $item): ?>
                                        <div class="order-item">
                                            <div class="order-item-img">
                                                <img src="<?php echo htmlspecialchars($item['image'] ?? '../images/product-1.png'); ?>" 
                                                     alt="<?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>">
                                            </div>
                                            <div class="order-item-info">
                                                <div class="order-item-name"><?php echo htmlspecialchars($item['name'] ?? 'Product'); ?></div>
                                                <div class="order-item-meta">
                                                    <?php echo htmlspecialchars($item['color'] ?? 'Default'); ?> 
                                                    × <?php echo intval($item['quantity'] ?? 1); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($items) > 2): ?>
                                        <div style="text-align: center; padding: 8px; color: #64748b; font-size: 12px;">
                                            +<?php echo count($items) - 2; ?> item lainnya
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Order Summary -->
                            <div class="order-summary">
                                <div class="order-summary-row">
                                    <span class="order-summary-label">Subtotal</span>
                                    <span class="order-summary-value"><?php echo formatRupiah($order['total'] + $order['discount'] - $order['shipping']); ?></span>
                                </div>
                                <?php if ((int)$order['discount'] > 0): ?>
                                    <div class="order-summary-row">
                                        <span class="order-summary-label">Diskon</span>
                                        <span class="order-summary-value" style="color: #10b981;">-<?php echo formatRupiah($order['discount']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="order-summary-row">
                                    <span class="order-summary-label">Ongkir</span>
                                    <span class="order-summary-value"><?php echo formatRupiah($order['shipping']); ?></span>
                                </div>
                                <div class="order-summary-row total">
                                    <span>Total Pesanan</span>
                                    <span><?php echo formatRupiah($order['total']); ?></span>
                                </div>
                            </div>

                            <!-- Delivery Personnel Info -->
                            <?php if (in_array($order['status'], ['delivered', 'on_delivery', 'completed']) && !empty($order['delivery_personnel'])): 
                                $dp = $order['delivery_personnel'];
                            ?>
                            <div style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 14px; border-radius: 8px; margin-bottom: 16px;">
                                <div style="font-size: 12px; color: #b45309; text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">
                                    👤 Informasi Pengirim
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 13px;">
                                    <div>
                                        <div style="color: #64748b; font-size: 11px; margin-bottom: 3px;">Nama Supir</div>
                                        <div style="font-weight: 600; color: #1f2937;"><?php echo htmlspecialchars($dp['driver_name']); ?></div>
                                    </div>
                                    <div>
                                        <div style="color: #64748b; font-size: 11px; margin-bottom: 3px;">Nomor HP</div>
                                        <div style="font-weight: 600;">
                                            <a href="tel:<?php echo htmlspecialchars($dp['phone_number']); ?>" style="color: #0ea5e9; text-decoration: none;">
                                                <?php echo htmlspecialchars($dp['phone_number']); ?>
                                            </a>
                                        </div>
                                    </div>
                                    <div>
                                        <div style="color: #64748b; font-size: 11px; margin-bottom: 3px;">Jenis Kendaraan</div>
                                        <div style="font-weight: 600; color: #1f2937;"><?php echo htmlspecialchars($dp['vehicle_type']); ?></div>
                                    </div>
                                    <div>
                                        <div style="color: #64748b; font-size: 11px; margin-bottom: 3px;">Nomor Kendaraan</div>
                                        <div style="font-weight: 600; color: #1f2937;"><?php echo htmlspecialchars($dp['vehicle_number']); ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Order Actions -->
                            <div class="order-footer">
                                <div class="order-status-progress">
                                    <div class="progress-stage">
                                        <span class="progress-dot <?php echo in_array($order['payment_status'], ['success', 'paid', 'pending', 'cod'], true) ? 'active' : ''; ?>"></span>
                                        Pembayaran
                                        <span class="progress-line <?php echo in_array($order['status'], ['processing', 'ready', 'delivered', 'dikirim', 'siap_dikirim', 'on_delivery', 'completed'], true) ? 'active' : ''; ?>"></span>
                                        <span class="progress-dot <?php echo in_array($order['status'], ['processing', 'ready', 'delivered', 'dikirim', 'siap_dikirim', 'on_delivery', 'completed'], true) ? 'active' : ''; ?>"></span>
                                        Pengiriman
                                        <span class="progress-line <?php echo in_array($order['status'], ['delivered', 'dikirim', 'siap_dikirim', 'on_delivery', 'completed'], true) ? 'active' : ''; ?>"></span>
                                        <span class="progress-dot <?php echo in_array($order['status'], ['delivered', 'dikirim', 'siap_dikirim', 'on_delivery', 'completed'], true) ? 'active' : ''; ?>"></span>
                                        Selesai
                                    </div>
                                </div>
                                <div class="order-actions">
                                    <button class="order-action-btn" onclick="showOrderDetail('<?php echo htmlspecialchars($order['order_id']); ?>', event)" style="background: #dbeafe; color: #1e40af;">
                                        <i class="fa fa-eye"></i> Detail
                                    </button>
                                    <?php if (in_array($order['status'], ['ready', 'dikirim', 'siap_dikirim', 'on_delivery', 'delivered'], true) && !empty($order['tracking_data']) && !empty($order['tracking_data']['store_id'])): ?>
                                    <a href="lacak.php?order_id=<?php echo urlencode($order['order_id']); ?>" class="order-action-btn" style="background: #d1fae5; color: #065f46; text-decoration: none;">
                                        <i class="fa fa-location-dot"></i> Lacak
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>

    <!-- Order Detail Modal -->
    <div id="orderDetailModal" class="order-detail-modal">
        <div class="modal-overlay" onclick="closeOrderDetail()"></div>
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modalOrderId">Nomor Pesanan</h3>
                <button class="modal-close" onclick="closeOrderDetail()"><i class="fa fa-times"></i></button>
            </div>
            <div class="modal-body" id="modalContent">
                <!-- Detail akan dimuat di sini -->
            </div>
            <div class="modal-footer">
                <button onclick="closeOrderDetail()" class="btn btn-secondary">Tutup</button>
                <a id="trackingBtn" href="#" class="btn btn-primary" style="display:none;">
                    <i class="fa fa-location-dot"></i> Lacak Pesanan
                </a>
            </div>
        </div>
    </div>

    <style>
        .order-detail-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1000;
            align-items: flex-start;
            justify-content: center;
            padding: 20px;
            overflow-y: auto;
        }
        .order-detail-modal.active {
            display: flex;
        }
        .modal-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.6);
        }
        .modal-box {
            background: white;
            border-radius: 18px;
            width: 100%;
            max-width: 700px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            margin-top: 40px;
            position: relative;
            z-index: 1001;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .modal-header h3 {
            font-size: 20px;
            font-weight: 700;
            color: #142850;
            margin: 0;
        }
        .modal-close {
            border: none;
            background: transparent;
            font-size: 24px;
            color: #64748b;
            cursor: pointer;
        }
        .modal-body {
            padding: 24px;
            max-height: 500px;
            overflow-y: auto;
        }
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        .btn {
            padding: 10px 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #334155;
        }
        .btn-primary {
            background: #142850;
            color: white;
        }
        .detail-section {
            margin-bottom: 20px;
        }
        .detail-section-title {
            font-size: 14px;
            font-weight: 700;
            color: #142850;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .detail-item {
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
        }
        .detail-item-label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 4px;
        }
        .detail-item-value {
            font-size: 16px;
            font-weight: 700;
            color: #142850;
        }
        .detail-item-img {
            width: 100%;
            height: 100px;
            background: #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        .detail-item-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="../js/custom.js"></script>
    <script>
        const ordersData = <?php echo json_encode(array_map(function($o) { 
            return [
                'order_id' => $o['order_id'] ?: ('ID-' . $o['id']),
                'timestamp' => $o['timestamp'],
                'status' => $o['status'],
                'payment_status' => $o['payment_status'],
                'total' => $o['total'],
                'discount' => $o['discount'],
                'shipping' => $o['shipping'],
                'items' => json_decode($o['items'] ?? '[]', true),
                'address' => json_decode($o['address'] ?? '{}', true),
                'message' => $o['message'] ?? ''
            ];
        }, $filteredOrders)); ?>;

        function formatRupiah(value) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
        }

        function getStatusText(status, paymentStatus) {
            if (['notdone', 'pending', 'cod'].includes(paymentStatus)) return 'Belum Bayar';
            if (['pending'].includes(status)) return 'Diproses';
            if (status === 'processing') return 'Sedang Dikemas';
            if (['ready', 'delivered', 'dikirim', 'siap_dikirim', 'on_delivery'].includes(status)) return 'Dikirim';
            if (['completed', 'selesai', 'finished'].includes(status)) return 'Selesai';
            if (['rejected', 'ditolak'].includes(status)) return 'Ditolak';
            return status || 'Unknown';
        }

        function showOrderDetail(orderId, event) {
            event.preventDefault();
            const order = ordersData.find(o => o.order_id === orderId);
            if (!order) {
                alert('Pesanan tidak ditemukan');
                return;
            }

            const subtotal = order.total - order.shipping + order.discount;
            const itemsHtml = order.items.map(item => {
                const qty = item.quantity || item.qty || 1;
                const amount = Number(item.price || 0) * qty;
                return `
                    <div style="margin-bottom: 12px;">
                        <div class="detail-item-img">
                            <img src="${item.image || '../images/product-1.png'}" alt="${item.name}">
                        </div>
                        <div style="font-size: 13px; font-weight: 600; color: #142850;">${item.name}</div>
                        <div style="font-size: 12px; color: #64748b;">${qty} × ${formatRupiah(Number(item.price || 0))} = ${formatRupiah(amount)}</div>
                    </div>`;
            }).join('');

            const addressText = [order.address.jalan || '', order.address.kota || '', order.address.provinsi || '', order.address.kodepos || '']
                .filter(Boolean).join(', ');

            let html = `
                <div class="detail-section">
                    <div class="detail-section-title">📦 Informasi Pesanan</div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-item-label">Status</div>
                            <div class="detail-item-value">${getStatusText(order.status, order.payment_status)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-item-label">Tanggal</div>
                            <div class="detail-item-value">${new Date(order.timestamp).toLocaleDateString('id-ID')}</div>
                        </div>
                    </div>
                </div>

                <div class="detail-section">
                    <div class="detail-section-title">📍 Pengiriman</div>
                    <div style="background: #f8fafc; padding: 12px; border-radius: 8px; font-size: 14px; line-height: 1.6;">
                        ${addressText || 'Alamat tidak tersedia'}
                    </div>
                </div>

                <div class="detail-section">
                    <div class="detail-section-title">🛍️ Item Pesanan</div>
                    <div style="background: #f8fafc; padding: 12px; border-radius: 8px;">
                        ${itemsHtml}
                    </div>
                </div>

                <div class="detail-section">
                    <div class="detail-section-title">💰 Rincian Pembayaran</div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-item-label">Subtotal</div>
                            <div class="detail-item-value">${formatRupiah(subtotal)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-item-label">Diskon</div>
                            <div class="detail-item-value" style="color: #10b981;">-${formatRupiah(order.discount)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-item-label">Ongkir</div>
                            <div class="detail-item-value">${formatRupiah(order.shipping)}</div>
                        </div>
                        <div class="detail-item" style="background: #dbeafe; grid-column: 1/-1;">
                            <div class="detail-item-label">Total Bayar</div>
                            <div class="detail-item-value" style="color: #1e40af;">${formatRupiah(order.total)}</div>
                        </div>
                    </div>
                </div>`;

            if (order.message) {
                html += `<div class="detail-section">
                    <div class="detail-section-title">📝 Catatan</div>
                    <div style="background: #f8fafc; padding: 12px; border-radius: 8px; font-size: 14px;">
                        ${order.message}
                    </div>
                </div>`;
            }

            document.getElementById('modalOrderId').textContent = 'Pesanan ' + orderId;
            document.getElementById('modalContent').innerHTML = html;
            
            const trackingBtn = document.getElementById('trackingBtn');
            const showStatuses = ['ready', 'on_delivery', 'dikirim', 'delivered'];
            const hasStoreOrigin = order.tracking_data && order.tracking_data.store_id;
            if (showStatuses.includes(order.status) && hasStoreOrigin) {
                trackingBtn.href = 'lacak.php?order_id=' + encodeURIComponent(orderId);
                trackingBtn.style.display = 'inline-flex';
            } else {
                trackingBtn.style.display = 'none';
            }

            document.getElementById('orderDetailModal').classList.add('active');
        }

        function closeOrderDetail() {
            document.getElementById('orderDetailModal').classList.remove('active');
        }

        // Auto-refresh pesanan setiap 15 detik untuk menangkap update status dari admin
        setInterval(function() {
            location.reload();
        }, 15000);
    </script>
</body>
</html>
