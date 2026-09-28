<?php
session_start();
require_once __DIR__ . '/../database/config.php';

if(!isset($_SESSION['admin_name'])){
    header('location:../index.php');
    exit();
}

$admin = $_SESSION['admin_name'];
$message = '';
$error = '';

function dbFirstColumn(array $candidates, array $available, $default = null) {
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $available, true)) {
            return $candidate;
        }
    }
    return $default;
}

function refValues(array &$arr) {
    $refs = [];
    foreach ($arr as &$item) {
        $refs[] = &$item;
    }
    return $refs;
}

function bindParams(mysqli_stmt $stmt, string $types, array &$values) {
    $params = array_merge([$types], $values);
    return call_user_func_array([$stmt, 'bind_param'], refValues($params));
}

$cols = [];
$colRes = $conn->query("SHOW COLUMNS FROM products");
if ($colRes) {
    while ($c = $colRes->fetch_assoc()) {
        if (!empty($c['Field'])) {
            $cols[] = $c['Field'];
        }
    }
}

$productsNameColumn = dbFirstColumn(['name', 'product_name', 'nama', 'title'], $cols);
$categoryColumn = dbFirstColumn(['category_id', 'cat_id', 'kategori_id', 'category', 'kategori', 'categoryId'], $cols);
$priceColumn = dbFirstColumn(['price', 'harga'], $cols);
$stockColumn = dbFirstColumn(['stock', 'stok'], $cols);
$imageColumn = dbFirstColumn(['image', 'gambar'], $cols);
$imageFrontColumn = in_array('image_front', $cols, true) ? 'image_front' : null;
$imageSideColumn = in_array('image_side', $cols, true) ? 'image_side' : null;
$imageBackColumn = in_array('image_back', $cols, true) ? 'image_back' : null;
$image45Column = in_array('image_45degree', $cols, true) ? 'image_45degree' : null;
$descColumn = dbFirstColumn(['description', 'deskripsi', 'desc', 'keterangan'], $cols);
$woodTypeColumn = dbFirstColumn(['wood_type', 'jenis_kayu', 'wood', 'kayu'], $cols);
$colorColumn = dbFirstColumn(['color', 'warna'], $cols);

$categoryIsString = in_array($categoryColumn, ['category', 'kategori'], true);

if (!$productsNameColumn || !$categoryColumn || !$priceColumn || !$stockColumn || !$imageColumn) {
    $error = 'Struktur tabel products tidak sesuai: diperlukan kolom nama, kategori, harga, stok, dan gambar.';
}

function rupiah($angka) {
    if ($angka === null || $angka === '') return 'Rp 0';
    return 'Rp ' . number_format((int)$angka, 0, ',', '.');
}

// Helper function to upload a single image
function uploadImage($fileInputName) {
    $uploadDir = __DIR__ . '/../images/products/';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
    
    if (!empty($_FILES[$fileInputName]['name'])) {
        $ext = strtolower(pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION));
        $allowed = ['png','jpg','jpeg','webp','gif'];
        
        if (!in_array($ext, $allowed)) {
            return ['error' => 'Format gambar tidak didukung.'];
        }
        
        $filename = 'produk_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $uploadDir . $filename)) {
            return ['path' => 'images/products/' . $filename];
        } else {
            return ['error' => 'Gagal upload gambar.'];
        }
    }
    return null; // No file uploaded
}

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        if ($categoryIsString) {
            $category_id = trim($_POST['category_id'] ?? '');
        } else {
            $category_id = intval($_POST['category_id'] ?? 0);
        }
        $price = intval($_POST['price'] ?? 0);
        $stock = intval($_POST['stock'] ?? 0);
        $wood_type = trim($_POST['wood_type'] ?? '');
        $color = trim($_POST['color'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        // Upload 4 images
        $imageFront = '';
        $imageSide = '';
        $imageBack = '';
        $image45 = '';
        
        // Upload Tampak Depan (required)
        $frontResult = uploadImage('image_front');
        if ($frontResult && isset($frontResult['error'])) {
            $error = 'Tampak Depan: ' . $frontResult['error'];
        } elseif ($frontResult && isset($frontResult['path'])) {
            $imageFront = $frontResult['path'];
        } else {
            $error = 'Tampak Depan wajib diunggah.';
        }
        
        // Upload Tampak Samping (optional)
        if (!$error) {
            $sideResult = uploadImage('image_side');
            if ($sideResult && isset($sideResult['error'])) {
                $error = 'Tampak Samping: ' . $sideResult['error'];
            } elseif ($sideResult && isset($sideResult['path'])) {
                $imageSide = $sideResult['path'];
            }
        }
        
        // Upload Tampak Belakang (optional)
        if (!$error) {
            $backResult = uploadImage('image_back');
            if ($backResult && isset($backResult['error'])) {
                $error = 'Tampak Belakang: ' . $backResult['error'];
            } elseif ($backResult && isset($backResult['path'])) {
                $imageBack = $backResult['path'];
            }
        }
        
        // Upload Tampak 45 Derajat (optional)
        if (!$error) {
            $angle45Result = uploadImage('image_45degree');
            if ($angle45Result && isset($angle45Result['error'])) {
                $error = 'Tampak 45 Derajat: ' . $angle45Result['error'];
            } elseif ($angle45Result && isset($angle45Result['path'])) {
                $image45 = $angle45Result['path'];
            }
        }

        if (!$error) {
            if ($categoryIsString && $category_id === '') {
                $error = 'Kategori wajib dipilih.';
            }
        }

        if (!$error) {
            $insertCols = [$productsNameColumn, $categoryColumn, $priceColumn, $stockColumn];
            // Determine types: name (s), category may be string (s) or int (i), price (i), stock (i)
            $insertTypes = $categoryIsString ? 'ssii' : 'siii';
            $insertValues = [$name, $category_id, $price, $stock];

            if ($woodTypeColumn) {
                $insertCols[] = $woodTypeColumn;
                $insertTypes .= 's';
                $insertValues[] = $wood_type;
            }
            if ($colorColumn) {
                $insertCols[] = $colorColumn;
                $insertTypes .= 's';
                $insertValues[] = $color;
            }
            if ($descColumn) {
                $insertCols[] = $descColumn;
                $insertTypes .= 's';
                $insertValues[] = $desc;
            }
            
            // Add optional image columns only if they exist
            if ($imageFrontColumn) {
                $insertCols[] = $imageFrontColumn;
                $insertTypes .= 's';
                $insertValues[] = $imageFront;
            }
            if ($imageSideColumn) {
                $insertCols[] = $imageSideColumn;
                $insertTypes .= 's';
                $insertValues[] = $imageSide;
            }
            if ($imageBackColumn) {
                $insertCols[] = $imageBackColumn;
                $insertTypes .= 's';
                $insertValues[] = $imageBack;
            }
            if ($image45Column) {
                $insertCols[] = $image45Column;
                $insertTypes .= 's';
                $insertValues[] = $image45;
            }
            if ($imageColumn) {
                $insertCols[] = $imageColumn; // Keep the old image column for backward compatibility
                $insertTypes .= 's';
                $insertValues[] = $imageFront; // Use front image as main image
            }

            $placeholders = implode(', ', array_fill(0, count($insertCols), '?'));
            $stmt = $conn->prepare('INSERT INTO products (' . implode(', ', $insertCols) . ') VALUES (' . $placeholders . ')');
            if ($stmt) {
                bindParams($stmt, $insertTypes, $insertValues);
                if ($stmt->execute()) {
                    $message = 'Produk berhasil ditambahkan.';
                } else {
                    $error = 'Gagal menambahkan produk: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = 'Gagal menyiapkan query produk: ' . $conn->error;
            }
        }
    } elseif ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($categoryIsString) {
            $category_id = trim($_POST['category_id'] ?? '');
        } else {
            $category_id = intval($_POST['category_id'] ?? 0);
        }
        $price = intval($_POST['price'] ?? 0);
        $stock = intval($_POST['stock'] ?? 0);
        $wood_type = trim($_POST['wood_type'] ?? '');
        $color = trim($_POST['color'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        // Fetch existing images
        $imageFront = '';
        $imageSide = '';
        $imageBack = '';
        $image45 = '';
        
        if ($id > 0) {
            $imageSelects = [];
            if ($imageFrontColumn) {
                $imageSelects[] = $imageFrontColumn;
            }
            if ($imageSideColumn) {
                $imageSelects[] = $imageSideColumn;
            }
            if ($imageBackColumn) {
                $imageSelects[] = $imageBackColumn;
            }
            if ($image45Column) {
                $imageSelects[] = $image45Column;
            }
            if ($imageColumn) {
                $imageSelects[] = $imageColumn;
            }

            if (!empty($imageSelects)) {
                $q = $conn->query('SELECT ' . implode(', ', $imageSelects) . ' FROM products WHERE id=' . $id);
                if ($q && $q->num_rows > 0) {
                    $row = $q->fetch_assoc();
                    $imageFront = $imageFrontColumn ? ($row[$imageFrontColumn] ?? '') : ($row[$imageColumn] ?? '');
                    $imageSide = $imageSideColumn ? ($row[$imageSideColumn] ?? '') : '';
                    $imageBack = $imageBackColumn ? ($row[$imageBackColumn] ?? '') : '';
                    $image45 = $image45Column ? ($row[$image45Column] ?? '') : '';
                }
            }
        }

        // Try to update images if new ones are uploaded
        $frontResult = uploadImage('image_front');
        if ($frontResult && isset($frontResult['error'])) {
            $error = 'Tampak Depan: ' . $frontResult['error'];
        } elseif ($frontResult && isset($frontResult['path'])) {
            $imageFront = $frontResult['path'];
        }
        
        if (!$error) {
            $sideResult = uploadImage('image_side');
            if ($sideResult && isset($sideResult['error'])) {
                $error = 'Tampak Samping: ' . $sideResult['error'];
            } elseif ($sideResult && isset($sideResult['path'])) {
                $imageSide = $sideResult['path'];
            }
        }
        
        if (!$error) {
            $backResult = uploadImage('image_back');
            if ($backResult && isset($backResult['error'])) {
                $error = 'Tampak Belakang: ' . $backResult['error'];
            } elseif ($backResult && isset($backResult['path'])) {
                $imageBack = $backResult['path'];
            }
        }
        
        if (!$error) {
            $angle45Result = uploadImage('image_45degree');
            if ($angle45Result && isset($angle45Result['error'])) {
                $error = 'Tampak 45 Derajat: ' . $angle45Result['error'];
            } elseif ($angle45Result && isset($angle45Result['path'])) {
                $image45 = $angle45Result['path'];
            }
        }

        if (!$error) {
            if ($categoryIsString && $category_id === '') {
                $error = 'Kategori wajib dipilih.';
            }
        }

        if (!$error) {
            $updateParts = [
                "$productsNameColumn=?",
                "$categoryColumn=?",
                "$priceColumn=?",
                "$stockColumn=?"
            ];
            // name (s), category may be string (s) or int (i), price (i), stock (i)
            $updateTypes = $categoryIsString ? 'ssii' : 'siii';
            $updateValues = [$name, $category_id, $price, $stock];

            if ($woodTypeColumn) {
                $updateParts[] = "$woodTypeColumn=?";
                $updateTypes .= 's';
                $updateValues[] = $wood_type;
            }
            if ($colorColumn) {
                $updateParts[] = "$colorColumn=?";
                $updateTypes .= 's';
                $updateValues[] = $color;
            }
            if ($descColumn) {
                $updateParts[] = "$descColumn=?";
                $updateTypes .= 's';
                $updateValues[] = $desc;
            }
            
            // Update image columns only if they exist
            if ($imageFrontColumn) {
                $updateParts[] = "$imageFrontColumn=?";
                $updateTypes .= 's';
                $updateValues[] = $imageFront;
            }
            if ($imageSideColumn) {
                $updateParts[] = "$imageSideColumn=?";
                $updateTypes .= 's';
                $updateValues[] = $imageSide;
            }
            if ($imageBackColumn) {
                $updateParts[] = "$imageBackColumn=?";
                $updateTypes .= 's';
                $updateValues[] = $imageBack;
            }
            if ($image45Column) {
                $updateParts[] = "$image45Column=?";
                $updateTypes .= 's';
                $updateValues[] = $image45;
            }
            if ($imageColumn) {
                $updateParts[] = "$imageColumn=?"; // Update main image too
                $updateTypes .= 's';
                $updateValues[] = $imageFront; // Use front image as main image
            }

            $updateValues[] = $id;
            $updateTypes .= 'i';

            $stmt = $conn->prepare('UPDATE products SET ' . implode(', ', $updateParts) . ' WHERE id=?');
            if ($stmt) {
                bindParams($stmt, $updateTypes, $updateValues);
                if ($stmt->execute()) {
                    $message = 'Produk berhasil diperbarui.';
                } else {
                    $error = 'Gagal memperbarui produk: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = 'Gagal menyiapkan query produk: ' . $conn->error;
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM products WHERE id=?");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $message = 'Produk berhasil dihapus.';
            } else {
                $error = 'Gagal menghapus produk: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Ambil daftar kategori
$categories = [];
$categoryValueField = $categoryIsString ? 'name' : 'id';
$catRes = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
if ($catRes) {
    while ($row = $catRes->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Ambil daftar produk (kolom nama produk bisa berbeda di DB)
$products = [];
$productsNameColumn = 'name';

// Cek kolom yang tersedia pada tabel products
$cols = [];
$colRes = $conn->query("SHOW COLUMNS FROM products");
if ($colRes) {
    while ($c = $colRes->fetch_assoc()) {
        if (!empty($c['Field'])) {
            $cols[] = $c['Field'];
        }
    }
}

if (!in_array('name', $cols, true)) {
    // fallback kolom nama produk yang mungkin ada
    if (in_array('product_name', $cols, true)) {
        $productsNameColumn = 'product_name';
    } elseif (in_array('nama', $cols, true)) {
        $productsNameColumn = 'nama';
    } else {
        $error = 'Struktur tabel products tidak sesuai: kolom nama produk tidak ditemukan (butuh: name / product_name / nama).';
    }
}

if (empty($error)) {
    // Deteksi nama kolom untuk relasi kategori (beberapa DB mungkin pakai nama berbeda)
    $categoryCandidates = ['category_id', 'cat_id', 'kategori_id', 'category', 'kategori', 'categoryId'];
    $categoryIdColumn = null;
    foreach ($categoryCandidates as $cand) {
        if (in_array($cand, $cols, true)) {
            $categoryIdColumn = $cand;
            break;
        }
    }

    $categoryIsString = in_array($categoryIdColumn, ['category', 'kategori'], true);

    // Kolom lain yang biasanya ada di tabel products
    $selectCols = [
        'price' => $priceColumn,
        'stock' => $stockColumn,
        'wood_type' => $woodTypeColumn,
        'color' => $colorColumn,
        'description' => $descColumn,
        'image' => $imageColumn,
        'image_front' => $imageFrontColumn,
        'image_side' => $imageSideColumn,
        'image_back' => $imageBackColumn,
        'image_45degree' => $image45Column,
    ];

    // Bangun daftar kolom SELECT secara dinamis sehingga query tidak memanggil kolom yang tidak ada
    $selectParts = [];
    $selectParts[] = 'p.id as id';
    $selectParts[] = 'p.' . $productsNameColumn . ' as name';

    if ($categoryIdColumn) {
        if ($categoryIsString) {
            $selectParts[] = 'p.' . $categoryIdColumn . ' as category_name';
            $join = '';
        } else {
            $selectParts[] = 'p.' . $categoryIdColumn . ' as category_id';
            $join = ' LEFT JOIN categories c ON p.' . $categoryIdColumn . ' = c.id ';
            $selectParts[] = 'c.name as category_name';
        }
    } else {
        $join = '';
        $selectParts[] = "NULL as category_name";
    }

    foreach ($selectCols as $alias => $col) {
        if ($col) {
            $selectParts[] = 'p.' . $col . ' as ' . $alias;
        } else {
            $selectParts[] = 'NULL as ' . $alias;
        }
    }

    $sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM products p' . $join . ' ORDER BY p.id DESC';
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $products[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Produk</title>
    <link href='https://cdn.jsdelivr.net/npm/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .container { max-width: 1400px; margin: 20px auto; padding: 0 20px; }
        .section-header { background: #142850; color: #fff; padding: 15px 20px; border-radius: 8px 8px 0 0; font-weight: 600; }
        .section-content { background: #fff; border: 1px solid #e0e7ff; border-radius: 0 0 8px 8px; padding: 20px; margin-bottom: 30px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #333; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 14px; font-family: inherit; box-sizing: border-box; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
        .form-row-2 { display: grid; grid-template-columns: 1fr; gap: 20px; }
        .btn-group { display: flex; gap: 10px; }
        .btn { padding: 10px 18px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn-primary { background: #142850; color: #fff; }
        .btn-danger { background: #ff6b6b; color: #fff; }
        .alert { padding: 15px 20px; margin-bottom: 20px; border-radius: 6px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        table { width: 100%; border-collapse: collapse; }
        table th { background: #f7fafc; border-bottom: 2px solid #e2e8f0; padding: 12px; text-align: left; font-weight: 600; }
        table td { border-bottom: 1px solid #e2e8f0; padding: 12px; }
        table tr:hover { background: #f7fafc; }
        .thumb { width: 50px; height: 50px; border-radius: 4px; object-fit: cover; }
        .action-btn { padding: 6px 12px; margin-right: 8px; font-size: 12px; }
        @media (max-width: 900px) {
            .form-row { grid-template-columns: 1fr; }
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
            <div class="header-title">Data Produk</div>
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

            <div class="section-header">Tambah Produk Baru</div>
            <div class="section-content">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama Produk *</label>
                            <input type="text" name="name" required>
                        </div>
                        <div class="form-group">
                            <label>Kategori *</label>
                            <select name="category_id" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($categoryValueField === 'name' ? $cat['name'] : $cat['id']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Harga (Rp) *</label>
                            <input type="number" name="price" min="0" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Stock *</label>
                            <input type="number" name="stock" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Jenis Kayu</label>
                            <input type="text" name="wood_type" placeholder="cth: Jati, Mahoni">
                        </div>
                        <div class="form-group">
                            <label>Warna</label>
                            <input type="text" name="color" placeholder="cth: Cokelat, Hitam">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="description" rows="3" placeholder="Deskripsi produk..."></textarea>
                    </div>
                    <div class="form-group">
                        <label style="font-weight: 700; color: #142850;">Upload Foto Produk (Minimal 1 foto wajib diunggah)</label>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tampak Depan (Front) *</label>
                            <input type="file" name="image_front" accept="image/*" required>
                            <small style="color: #888;">Ini akan menjadi foto thumbnail produk</small>
                        </div>
                        <div class="form-group">
                            <label>Tampak Samping (Side)</label>
                            <input type="file" name="image_side" accept="image/*">
                        </div>
                        <div class="form-group">
                            <label>Tampak Belakang (Back)</label>
                            <input type="file" name="image_back" accept="image/*">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tampak 45 Derajat (45°)</label>
                            <input type="file" name="image_45degree" accept="image/*">
                        </div>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Tambah Produk</button>
                    </div>
                </form>
            </div>

            <div class="section-header">Edit Produk</div>
            <div class="section-content">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update">
                    <div class="form-row-2">
                        <div class="form-group">
                            <label>Pilih Produk *</label>
                            <select name="id" id="productSelect" required onchange="populateProductData(this.value)">
                                <option value="">-- Pilih Produk --</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" data-product='<?php echo json_encode($p); ?>'>
                                        #<?php echo $p['id']; ?> - <?php echo htmlspecialchars($p['name']); ?> (<?php echo htmlspecialchars($p['category_name'] ?? '-'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama Produk</label>
                            <input type="text" name="name" id="editName">
                        </div>
                        <div class="form-group">
                            <label>Kategori</label>
                            <select name="category_id" id="editCategory">
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($categoryValueField === 'name' ? $cat['name'] : $cat['id']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Harga (Rp)</label>
                            <input type="number" name="price" id="editPrice" min="0">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Stock</label>
                            <input type="number" name="stock" id="editStock" min="0">
                        </div>
                        <div class="form-group">
                            <label>Jenis Kayu</label>
                            <input type="text" name="wood_type" id="editWoodType">
                        </div>
                        <div class="form-group">
                            <label>Warna</label>
                            <input type="text" name="color" id="editColor">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="description" id="editDescription" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label style="font-weight: 700; color: #142850;">Update Foto Produk (opsional)</label>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tampak Depan (Front)</label>
                            <input type="file" name="image_front" accept="image/*">
                            <small style="color: #888;">Kosongkan jika tidak ingin mengubah</small>
                        </div>
                        <div class="form-group">
                            <label>Tampak Samping (Side)</label>
                            <input type="file" name="image_side" accept="image/*">
                            <small style="color: #888;">Kosongkan jika tidak ingin mengubah</small>
                        </div>
                        <div class="form-group">
                            <label>Tampak Belakang (Back)</label>
                            <input type="file" name="image_back" accept="image/*">
                            <small style="color: #888;">Kosongkan jika tidak ingin mengubah</small>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tampak 45 Derajat (45°)</label>
                            <input type="file" name="image_45degree" accept="image/*">
                            <small style="color: #888;">Kosongkan jika tidak ingin mengubah</small>
                        </div>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Update Produk</button>
                    </div>
                </form>
            </div>

            <div class="section-header">Daftar Produk (<?php echo count($products); ?> produk)</div>
            <div class="section-content">
                <?php if (empty($products)): ?>
                    <p style="color: #666;">Belum ada produk.</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Gambar</th>
                                    <th>Nama</th>
                                    <th>Kategori</th>
                                    <th>Harga</th>
                                    <th>Stock</th>
                                    <th>Kayu</th>
                                    <th>Warna</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $p): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($p['image']) && file_exists(__DIR__ . '/../' . $p['image'])): ?>
                                                <img class="thumb" src="../<?php echo htmlspecialchars($p['image']); ?>" alt="">
                                            <?php else: ?>
                                                <div class="thumb" style="display:flex;align-items:center;justify-content:center;background:#f0f0f0;color:#999;">N/A</div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($p['name']); ?></td>
                                        <td><?php echo htmlspecialchars($p['category_name'] ?? '-'); ?></td>
                                        <td><?php echo rupiah($p['price']); ?></td>
                                        <td><?php echo (int)$p['stock']; ?></td>
                                        <td><?php echo htmlspecialchars($p['wood_type'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($p['color'] ?? '-'); ?></td>
                                        <td>
                                            <form style="display:inline;" method="post" onsubmit="return confirm('Hapus produk ini?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                                <button type="submit" class="btn btn-danger action-btn">Hapus</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../js/dashboard.js"></script>
    <script>
        function populateProductData(productId) {
            if (!productId) {
                document.getElementById('editName').value = '';
                document.getElementById('editCategory').value = '';
                document.getElementById('editPrice').value = '';
                document.getElementById('editStock').value = '';
                document.getElementById('editWoodType').value = '';
                document.getElementById('editColor').value = '';
                document.getElementById('editDescription').value = '';
                return;
            }

            const option = document.querySelector(`#productSelect option[value="${productId}"]`);
            if (option && option.dataset.product) {
                const product = JSON.parse(option.dataset.product);
                document.getElementById('editName').value = product.name || '';
                document.getElementById('editCategory').value = product.category_id || product.category_name || '';
                document.getElementById('editPrice').value = product.price || '';
                document.getElementById('editStock').value = product.stock || '';
                document.getElementById('editWoodType').value = product.wood_type || '';
                document.getElementById('editColor').value = product.color || '';
                document.getElementById('editDescription').value = product.description || '';
            }
        }
                                            
    </script>
</body>
</html>
                                        