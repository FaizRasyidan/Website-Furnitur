<?php
session_start();
require_once __DIR__ . '/../database/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_name'])) {
    header('Location: ../index.php');
    exit();
}

// Ensure optional columns exist in users table
$cols = [];
$res = $conn->query("SHOW COLUMNS FROM users");
while ($row = $res->fetch_assoc()) {
    $cols[] = $row['Field'];
}

$need = [];
if (!in_array('phone', $cols)) $need[] = "ADD COLUMN phone VARCHAR(30) NULL";
if (!in_array('profile_photo', $cols)) $need[] = "ADD COLUMN profile_photo VARCHAR(255) NULL";
if (!in_array('username', $cols)) $need[] = "ADD COLUMN username VARCHAR(100) NULL";
if (!in_array('created_at', $cols)) $need[] = "ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
if ($need) {
    $sql = "ALTER TABLE users " . implode(', ', $need);
    $conn->query($sql);
}

// Pagination settings
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : 5;
$perPage = min($perPage, 100); // Max 100 per page

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchWhere = '';
$params = [];
$types = '';

if (!empty($search)) {
    $searchWhere = "WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? OR username LIKE ?";
    $searchTerm = '%' . $search . '%';
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    $types = 'ssss';
}

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM users " . $searchWhere;
if (!empty($params)) {
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $totalResult = $stmt->get_result();
    $stmt->close();
} else {
    $totalResult = $conn->query($countQuery);
}

$totalRow = $totalResult->fetch_assoc();
$totalUsers = $totalRow['total'];
$totalPages = ceil($totalUsers / $perPage);
$page = min($page, max(1, $totalPages));
$offset = ($page - 1) * $perPage;

// Fetch users with pagination
$query = "SELECT id, name, email, username, phone, profile_photo, created_at FROM users " . $searchWhere . " ORDER BY created_at DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $perPage;
$types .= 'ii';

$stmt = $conn->prepare($query);
if (!empty($searchWhere)) {
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param('ii', $offset, $perPage);
}
$stmt->execute();
$result = $stmt->get_result();
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

// Set default profile photo path (use existing shared default)
$defaultPhoto = '../images/default-avatar.png';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pelanggan - Admin</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="../css/admin.css" rel="stylesheet">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; margin-bottom: 24px; padding: 26px 30px; border-radius: 22px; background: #ffffff; border: 1px solid #e5e7eb; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08); }
        .page-title { font-size: 32px; font-weight: 800; color: #0f172a; margin-bottom: 8px; }
        .page-subtitle { color: #6b7280; font-size: 15px; line-height: 1.7; max-width: 640px; }
        .table-card { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 22px; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08); overflow: hidden; }
        .table-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 22px 24px; border-bottom: 1px solid #e5e7eb; flex-wrap: wrap; }
        .table-toolbar-left { display: flex; flex-direction: column; gap: 8px; }
        .table-title { font-size: 18px; font-weight: 700; color: #0f172a; }
        .table-desc { color: #6b7280; font-size: 14px; }
        .table-wrapper { overflow-x: auto; }
        .table-wrapper table { min-width: 760px; margin-bottom: 0; }
        .table-wrapper th, .table-wrapper td { vertical-align: middle; }
        .table-wrapper th { font-weight: 700; color: #1f2937; border-bottom: 2px solid #e5e7eb; }
        .table-wrapper tbody tr:hover { background: #f8fafc; }
        .user-photo { width: 48px; height: 48px; border-radius: 12px; object-fit: cover; border: 1px solid #e5e7eb; }
        .pagination-controls { display: flex; align-items: center; gap: 12px; justify-content: flex-end; margin-top: 16px; padding: 16px 24px 0; border-top: 1px solid #e5e7eb; }
        .pagination-info { color: #6b7280; font-size: 14px; }
        .per-page-select { padding: 4px 8px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; }
        .search-box { padding: 16px 24px; border-bottom: 1px solid #e5e7eb; }
        .search-input { width: 100%; padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 14px; }
        .pagination-buttons { display: flex; gap: 6px; }
        .pagination-btn { padding: 6px 10px; border: 1px solid #e5e7eb; border-radius: 8px; background: white; cursor: pointer; color: #0f172a; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .pagination-btn:hover { background: #f3f4f6; }
        .pagination-btn.active { background: #007bff; color: white; border-color: #007bff; }
        .pagination-btn:disabled { opacity: 0.5; cursor: not-allowed; }
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
    <div class="page-header">
        <div>
            <div class="page-title">Manajemen Pelanggan</div>
            <div class="page-subtitle">Lihat daftar lengkap pelanggan terdaftar dengan informasi kontak dan profil mereka.</div>
        </div>
    </div>

    <div class="table-card">
        <!-- Search Bar -->
        <div class="search-box">
            <form method="GET" action="pelanggan.php" style="display: flex; gap: 10px;">
                <input type="text" name="search" class="search-input" placeholder="Cari pelanggan berdasarkan nama, email, atau nomor HP..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>

        <!-- Table -->
        <div class="table-wrapper">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Username</th>
                        <th>No. Telepon</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fas fa-users" style="font-size: 1.5rem;"></i>
                                <div>Belum ada pelanggan terdaftar.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <img src="<?php 
                                        if (!empty($user['profile_photo']) && file_exists(__DIR__ . '/../' . $user['profile_photo'])) {
                                            echo '../' . htmlspecialchars($user['profile_photo']);
                                        } else {
                                            echo 'https://ui-avatars.com/api/?name=' . urlencode($user['name']) . '&background=random&size=48';
                                        }
                                    ?>" alt="<?php echo htmlspecialchars($user['name']); ?>" class="user-photo">
                                </td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['username'] ?? $user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination-controls">
            <div class="pagination-info">
                Showing <?php echo $totalUsers > 0 ? ($offset + 1) : 0; ?> to <?php echo min($offset + $perPage, $totalUsers); ?> of <?php echo $totalUsers; ?> results
            </div>
            <div>
                <label for="per_page_select" style="margin-right: 8px; font-size: 14px; color: #6b7280;">Per page:</label>
                <select id="per_page_select" class="per-page-select" onchange="changePerPage(this.value)">
                    <option value="5" <?php echo $perPage == 5 ? 'selected' : ''; ?>>5</option>
                    <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10</option>
                    <option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25</option>
                    <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50</option>
                </select>
            </div>
            <div class="pagination-buttons">
                <?php if ($page > 1): ?>
                    <a href="pelanggan.php?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>&per_page=<?php echo $perPage; ?>" class="pagination-btn">«</a>
                    <a href="pelanggan.php?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>&per_page=<?php echo $perPage; ?>" class="pagination-btn">‹</a>
                <?php else: ?>
                    <button class="pagination-btn" disabled>«</button>
                    <button class="pagination-btn" disabled>‹</button>
                <?php endif; ?>

                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                    <a href="pelanggan.php?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>&per_page=<?php echo $perPage; ?>" 
                       class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="pelanggan.php?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>&per_page=<?php echo $perPage; ?>" class="pagination-btn">›</a>
                    <a href="pelanggan.php?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>&per_page=<?php echo $perPage; ?>" class="pagination-btn">»</a>
                <?php else: ?>
                    <button class="pagination-btn" disabled>›</button>
                    <button class="pagination-btn" disabled>»</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="../js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSubmenu(element) {
        if (element.nextElementSibling && element.nextElementSibling.classList.contains('submenu')) {
            element.nextElementSibling.classList.toggle('show');
            element.classList.toggle('rotate');
        }
    }

    function changePerPage(value) {
        const search = new URLSearchParams(window.location.search).get('search') || '';
        const url = new URL(window.location);
        url.searchParams.set('per_page', value);
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    }
</script>
</body>
</html>
