<?php
session_start();
require_once __DIR__ . '/../database/config.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ../index.php');
    exit();
}

$admin = $_SESSION['admin_name'];
$message = '';
$error = '';

// Handle tambah kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        if (!$name) {
            $error = 'Nama kategori harus diisi.';
        } else {
            $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
            $stmt->bind_param('ss', $name, $desc);
            if ($stmt->execute()) {
                $message = 'Kategori berhasil ditambahkan.';
            } else {
                $error = 'Gagal menambahkan kategori: ' . $stmt->error;
            }
            $stmt->close();
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        if ($id <= 0 || !$name) {
            $error = 'Data tidak lengkap.';
        } else {
            $stmt = $conn->prepare("UPDATE categories SET name=?, description=? WHERE id=?");
            $stmt->bind_param('ssi', $name, $desc, $id);
            if ($stmt->execute()) {
                $message = 'Kategori berhasil diperbarui.';
            } else {
                $error = 'Gagal memperbarui kategori: ' . $stmt->error;
            }
            $stmt->close();
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM categories WHERE id=?");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $message = 'Kategori berhasil dihapus.';
            } else {
                $error = 'Gagal menghapus kategori: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Ambil daftar kategori
$categories = [];
$result = $conn->query("SELECT id, name, description FROM categories ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori Produk</title>
    <link href='https://cdn.jsdelivr.net/npm/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .section-header { background: #142850; color: #fff; padding: 15px 20px; border-radius: 8px 8px 0 0; font-weight: 600; }
        .section-content { background: #fff; border: 1px solid #e0e7ff; border-radius: 0 0 8px 8px; padding: 20px; margin-bottom: 30px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #333; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 14px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .btn-group { display: flex; gap: 10px; }
        .btn { padding: 10px 18px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn-primary { background: #142850; color: #fff; }
        .btn-danger { background: #ff6b6b; color: #fff; }
        .btn-secondary { background: #cbd5e0; color: #333; }
        .alert { padding: 15px 20px; margin-bottom: 20px; border-radius: 6px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        table { width: 100%; border-collapse: collapse; }
        table th { background: #f7fafc; border-bottom: 2px solid #e2e8f0; padding: 12px; text-align: left; font-weight: 600; }
        table td { border-bottom: 1px solid #e2e8f0; padding: 12px; }
        table tr:hover { background: #f7fafc; }
        .action-btn { padding: 6px 12px; margin-right: 8px; font-size: 12px; }
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
            <div class="header-title">Kelola Kategori Produk</div>
            <div class="user-profile">
                <div>
                    <span style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($admin); ?></span><br>
                    <span style="font-size: 12px; color: #888;">Admin</span>
                </div>
                <div class="avatar"><i class='bx bxs-user'></i></div>
            </div>
        </header>

        <div class="container">
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="section-header">Tambah Kategori Baru</div>
            <div class="section-content">
                <form method="post">
                    <input type="hidden" name="action" value="add">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama Kategori</label>
                            <input type="text" name="name" required>
                        </div>
                        <div class="form-group">
                            <label>Deskripsi (Opsional)</label>
                            <input type="text" name="description">
                        </div>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Tambah Kategori</button>
                    </div>
                </form>
            </div>

            <div class="section-header">Daftar Kategori</div>
            <div class="section-content">
                <?php if (empty($categories)): ?>
                    <p style="color: #666;">Belum ada kategori.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Kategori</th>
                                <th>Deskripsi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $idx => $cat): ?>
                                <tr>
                                    <td><?php echo $idx + 1; ?></td>
                                    <td><?php echo htmlspecialchars($cat['name']); ?></td>
                                    <td><?php echo htmlspecialchars($cat['description'] ?? '-'); ?></td>
                                    <td>
                                        <button class="btn btn-secondary action-btn" onclick="editCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name']); ?>', '<?php echo htmlspecialchars($cat['description'] ?? ''); ?>')">Edit</button>
                                        <form style="display:inline;" method="post" onsubmit="return confirm('Yakin hapus kategori ini?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                            <button type="submit" class="btn btn-danger action-btn">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../js/dashboard.js"></script>
    <script>
        function editCategory(id, name, desc) {
            const newName = prompt('Edit nama kategori:', name);
            if (newName === null) return;
            const newDesc = prompt('Edit deskripsi:', desc);
            if (newDesc === null) return;

            const form = document.createElement('form');
            form.method = 'post';
            form.innerHTML = `
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="${id}">
                <input type="hidden" name="name" value="${newName}">
                <input type="hidden" name="description" value="${newDesc}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>
 