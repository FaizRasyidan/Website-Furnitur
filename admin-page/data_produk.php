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
        $category_id = intval($_POST['category_id'] ?? 0);
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
            $insertCols = [$productsNameColumn, $categoryColumn, $priceColumn, $stockColumn];
            $insertTypes = 'siii';
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
        $category_id = intval($_POST['category_id'] ?? 0);
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
            $updateParts = [
                "$productsNameColumn=?",
                "$categoryColumn=?",
                "$priceColumn=?",
                "$stockColumn=?"
            ];
            $updateTypes = 'siii';
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
$catRes = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
if ($catRes) {
    while ($row = $catRes->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Ambil daftar nilai unik untuk jenis kayu dan warna dari tabel products (jika kolom ada)
$woodTypes = [];
$colors = [];
if ($woodTypeColumn) {
    $wq = $conn->query("SELECT DISTINCT " . $woodTypeColumn . " AS val FROM products WHERE " . $woodTypeColumn . " IS NOT NULL AND " . $woodTypeColumn . "<>'' ORDER BY " . $woodTypeColumn . " ASC");
    if ($wq) {
        while ($r = $wq->fetch_assoc()) {
            $woodTypes[] = $r['val'];
        }
    }
}
if ($colorColumn) {
    $cq = $conn->query("SELECT DISTINCT " . $colorColumn . " AS val FROM products WHERE " . $colorColumn . " IS NOT NULL AND " . $colorColumn . "<>'' ORDER BY " . $colorColumn . " ASC");
    if ($cq) {
        while ($r = $cq->fetch_assoc()) {
            $colors[] = $r['val'];
        }
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
        .clickable { cursor: pointer; margin-right: 6px; }
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
                <form id="createForm" method="post" enctype="multipart/form-data">
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
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
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
                            <select id="woodTypeSelect_create">
                                <option value="">-- Pilih Jenis Kayu --</option>
                                <?php foreach ($woodTypes as $wt): ?>
                                    <option value="<?php echo htmlspecialchars($wt); ?>"><?php echo htmlspecialchars($wt); ?></option>
                                <?php endforeach; ?>
                                <option value="other">Lainnya...</option>
                            </select>
                            <input type="text" id="woodTypeOther_create" placeholder="Masukkan jenis kayu lain" style="display:none;margin-top:8px;">
                            <input type="hidden" name="wood_type" id="wood_type_create">
                        </div>
                        <div class="form-group">
                            <label>Warna</label>
                            <select id="colorSelect_create">
                                <option value="">-- Pilih Warna --</option>
                                <?php foreach ($colors as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                                <?php endforeach; ?>
                                <option value="other">Lainnya...</option>
                            </select>
                            <input type="text" id="colorOther_create" placeholder="Masukkan warna lain" style="display:none;margin-top:8px;">
                            <input type="hidden" name="color" id="color_create">
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
                <form id="updateForm" method="post" enctype="multipart/form-data">
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
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
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
                            <select id="woodTypeSelect_update">
                                <option value="">-- Pilih Jenis Kayu --</option>
                                <?php foreach ($woodTypes as $wt): ?>
                                    <option value="<?php echo htmlspecialchars($wt); ?>"><?php echo htmlspecialchars($wt); ?></option>
                                <?php endforeach; ?>
                                <option value="other">Lainnya...</option>
                            </select>
                            <input type="text" id="woodTypeOther_update" placeholder="Masukkan jenis kayu lain" style="display:none;margin-top:8px;">
                            <input type="hidden" name="wood_type" id="wood_type_update">
                        </div>
                        <div class="form-group">
                            <label>Warna</label>
                            <select id="colorSelect_update">
                                <option value="">-- Pilih Warna --</option>
                                <?php foreach ($colors as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                                <?php endforeach; ?>
                                <option value="other">Lainnya...</option>
                            </select>
                            <input type="text" id="colorOther_update" placeholder="Masukkan warna lain" style="display:none;margin-top:8px;">
                            <input type="hidden" name="color" id="color_update">
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
                                            <?php
                                                $imgCols = [
                                                    'image_front' => 'Tampak Depan',
                                                    'image_side' => 'Tampak Samping',
                                                    'image_back' => 'Tampak Belakang',
                                                    'image_45degree' => 'Tampak 45°',
                                                    'image' => 'Gambar Utama'
                                                ];
                                                $hasAny = false;
                                                foreach ($imgCols as $col => $label) {
                                                    if (!empty($p[$col]) && file_exists(__DIR__ . '/../' . $p[$col])) {
                                                        $hasAny = true;
                                                        echo '<img class="thumb clickable" src="../' . htmlspecialchars($p[$col]) . '" data-src="../' . htmlspecialchars($p[$col]) . '" data-type="' . $label . '" onclick="openImageModal(this.dataset.src, this.dataset.type)">';
                                                    }
                                                }
                                                if (!$hasAny) {
                                                    echo '<div class="thumb" style="display:flex;align-items:center;justify-content:center;background:#f0f0f0;color:#999;">N/A</div>';
                                                }
                                            ?>
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
        // Open image modal with given src and caption
        function openImageModal(src, caption) {
            const modal = document.getElementById('imgModal');
            const modalImg = document.getElementById('imgModalImg');
            const modalCaption = document.getElementById('imgModalCaption');
            modalImg.src = src;
            modalCaption.innerText = caption || '';
            modal.style.display = 'flex';
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Close modal handlers
            const modal = document.getElementById('imgModal');
            if (modal) {
                document.getElementById('imgModalClose').addEventListener('click', function(e){ modal.style.display='none'; });
                modal.addEventListener('click', function(e){ if (e.target.id === 'imgModal' || e.target.id === 'imgModalClose') modal.style.display='none'; });
            }

            // Wire up create form selects
            const woodSelCreate = document.getElementById('woodTypeSelect_create');
            const woodOtherCreate = document.getElementById('woodTypeOther_create');
            const woodHiddenCreate = document.getElementById('wood_type_create');
            if (woodSelCreate) {
                woodSelCreate.addEventListener('change', function(){
                    if (this.value === 'other') { woodOtherCreate.style.display='block'; }
                    else { woodOtherCreate.style.display='none'; }
                });
            }
            const colorSelCreate = document.getElementById('colorSelect_create');
            const colorOtherCreate = document.getElementById('colorOther_create');
            const colorHiddenCreate = document.getElementById('color_create');
            if (colorSelCreate) {
                colorSelCreate.addEventListener('change', function(){
                    if (this.value === 'other') { colorOtherCreate.style.display='block'; }
                    else { colorOtherCreate.style.display='none'; }
                });
            }

            // Wire up update form selects
            const woodSelUpd = document.getElementById('woodTypeSelect_update');
            const woodOtherUpd = document.getElementById('woodTypeOther_update');
            const woodHiddenUpd = document.getElementById('wood_type_update');
            if (woodSelUpd) {
                woodSelUpd.addEventListener('change', function(){
                    if (this.value === 'other') { woodOtherUpd.style.display='block'; }
                    else { woodOtherUpd.style.display='none'; }
                });
            }
            const colorSelUpd = document.getElementById('colorSelect_update');
            const colorOtherUpd = document.getElementById('colorOther_update');
            const colorHiddenUpd = document.getElementById('color_update');
            if (colorSelUpd) {
                colorSelUpd.addEventListener('change', function(){
                    if (this.value === 'other') { colorOtherUpd.style.display='block'; }
                    else { colorOtherUpd.style.display='none'; }
                });
            }

            // On submit, copy selected/other into hidden inputs for both forms
            const createForm = document.getElementById('createForm');
            if (createForm) {
                createForm.addEventListener('submit', function(e){
                    if (woodSelCreate) {
                        woodHiddenCreate.value = (woodSelCreate.value === 'other') ? (woodOtherCreate.value || '') : (woodSelCreate.value || '');
                    }
                    if (colorSelCreate) {
                        colorHiddenCreate.value = (colorSelCreate.value === 'other') ? (colorOtherCreate.value || '') : (colorSelCreate.value || '');
                    }
                });
            }
            const updateForm = document.getElementById('updateForm');
            if (updateForm) {
                updateForm.addEventListener('submit', function(e){
                    if (woodSelUpd) {
                        woodHiddenUpd.value = (woodSelUpd.value === 'other') ? (woodOtherUpd.value || '') : (woodSelUpd.value || '');
                    }
                    if (colorSelUpd) {
                        colorHiddenUpd.value = (colorSelUpd.value === 'other') ? (colorOtherUpd.value || '') : (colorSelUpd.value || '');
                    }
                });
            }
        });

        function populateProductData(productId) {
            if (!productId) {
                document.getElementById('editName').value = '';
                document.getElementById('editCategory').value = '';
                document.getElementById('editPrice').value = '';
                document.getElementById('editStock').value = '';
                // reset selects and other inputs
                const wsel = document.getElementById('woodTypeSelect_update');
                const wother = document.getElementById('woodTypeOther_update');
                const whid = document.getElementById('wood_type_update');
                if (wsel) { wsel.value = ''; if (wother) wother.style.display='none'; }
                if (whid) whid.value = '';
                const csel = document.getElementById('colorSelect_update');
                const cother = document.getElementById('colorOther_update');
                const chid = document.getElementById('color_update');
                if (csel) { csel.value = ''; if (cother) cother.style.display='none'; }
                if (chid) chid.value = '';
                document.getElementById('editDescription').value = '';
                return;
            }

            const option = document.querySelector(`#productSelect option[value="${productId}"]`);
            if (option && option.dataset.product) {
                const product = JSON.parse(option.dataset.product);
                document.getElementById('editName').value = product.name || '';
                document.getElementById('editCategory').value = product.category_id || '';
                document.getElementById('editPrice').value = product.price || '';
                document.getElementById('editStock').value = product.stock || '';

                // wood type: if present in options set select, otherwise set to other and fill text
                const wsel = document.getElementById('woodTypeSelect_update');
                const wother = document.getElementById('woodTypeOther_update');
                const whid = document.getElementById('wood_type_update');
                const wval = product.wood_type || '';
                if (wsel) {
                    let found = false;
                    for (let i=0;i<wsel.options.length;i++){
                        if (wsel.options[i].value === wval){ found = true; break; }
                    }
                    if (found) { wsel.value = wval; if (wother) wother.style.display='none'; if (whid) whid.value = wval; }
                    else if (wval) { wsel.value = 'other'; if (wother) { wother.style.display='block'; wother.value = wval; } if (whid) whid.value = wval; }
                    else { wsel.value = ''; if (wother) wother.style.display='none'; if (whid) whid.value = ''; }
                }

                // color
                const csel = document.getElementById('colorSelect_update');
                const cother = document.getElementById('colorOther_update');
                const chid = document.getElementById('color_update');
                const cval = product.color || '';
                if (csel) {
                    let found = false;
                    for (let i=0;i<csel.options.length;i++){
                        if (csel.options[i].value === cval){ found = true; break; }
                    }
                    if (found) { csel.value = cval; if (cother) cother.style.display='none'; if (chid) chid.value = cval; }
                    else if (cval) { csel.value = 'other'; if (cother) { cother.style.display='block'; cother.value = cval; } if (chid) chid.value = cval; }
                    else { csel.value = ''; if (cother) cother.style.display='none'; if (chid) chid.value = ''; }
                }

                document.getElementById('editDescription').value = product.description || '';
            }
        }
                                            
    </script>

    <!-- Image modal -->
    <div id="imgModal" style="display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.8);align-items:center;justify-content:center;">
        <span id="imgModalClose" style="position:absolute;right:20px;top:20px;color:#fff;font-size:28px;cursor:pointer;">&times;</span>
        <img id="imgModalImg" src="" style="max-width:90%;max-height:90%;border-radius:8px;">
        <div id="imgModalCaption" style="color:#fff;margin-top:10px;text-align:center;"></div>
    </div>
</body>
</html>
                                        