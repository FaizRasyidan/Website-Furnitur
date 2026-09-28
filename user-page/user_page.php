<?php

@include 'config.php';
require_once __DIR__ . '/../database/config.php';

session_start();

if(!isset($_SESSION['user_name'])){
   header('location:index.php');
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
 $addresses = [];
 $check = $conn->query("SHOW TABLES LIKE 'user_addresses'");
 if ($check && $check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $normalized = [];
            $normalized['id'] = $row['id'] ?? null;
            $normalized['jalan'] = $row['jalan'] ?? $row['alamat'] ?? $row['address'] ?? $row['street'] ?? '';
            $normalized['kota'] = $row['kota'] ?? $row['city'] ?? '';
            $normalized['provinsi'] = $row['provinsi'] ?? $row['province'] ?? $row['prov'] ?? '';
            $normalized['kodepos'] = $row['kodepos'] ?? $row['postal_code'] ?? $row['zip'] ?? '';
            $normalized['PRIMARY_ADDRESS'] = isset($row['PRIMARY_ADDRESS']) ? (int)$row['PRIMARY_ADDRESS'] : (isset($row['primary_address']) ? (int)$row['primary_address'] : (isset($row['is_primary']) ? (int)$row['is_primary'] : 0));
            $addresses[] = $normalized;
        }
        $stmt->close();
    }
 }

$primaryAddress = null;
$selectedId = $_SESSION['selected_address_id'] ?? null;
if ($selectedId) {
    foreach ($addresses as $addr) {
        if ($addr['id'] == $selectedId) {
            $primaryAddress = $addr;
            break;
        }
    }
}

if (!$primaryAddress) {
    foreach ($addresses as $addr) {
        if (!empty($addr['PRIMARY_ADDRESS']) && $addr['PRIMARY_ADDRESS'] == 1) {
            $primaryAddress = $addr;
            break;
        }
    }
}

if (!$primaryAddress && count($addresses) > 0) {
    $primaryAddress = $addresses[0];
}

$products = [];

// Detect columns in `products` table
$cols = [];
$colRes = $conn->query("SHOW COLUMNS FROM products");
if ($colRes) {
	while ($c = $colRes->fetch_assoc()) {
		if (!empty($c['Field'])) $cols[] = $c['Field'];
	}
}

// Determine product name column
$productsNameColumn = 'name';
if (!in_array('name', $cols, true)) {
	if (in_array('product_name', $cols, true)) {
		$productsNameColumn = 'product_name';
	} elseif (in_array('nama', $cols, true)) {
		$productsNameColumn = 'nama';
	} elseif (in_array('title', $cols, true)) {
		$productsNameColumn = 'title';
	} else {
		// fallback to first non-id column if available
		foreach ($cols as $cname) {
			if ($cname !== 'id') { $productsNameColumn = $cname; break; }
		}
	}
}

// Determine category column - check for string field first (kategori), then ID field with join
$categoryStringColumn = null;
$categoryIdColumn = null;

// First check for simple string category columns
if (in_array('kategori', $cols, true)) {
	$categoryStringColumn = 'kategori';
} elseif (in_array('category', $cols, true)) {
	$categoryStringColumn = 'category';
}

// If no string category, check for category ID columns to join
if (!$categoryStringColumn) {
	$categoryCandidates = ['category_id', 'cat_id', 'kategori_id', 'categoryId'];
	foreach ($categoryCandidates as $cand) {
		if (in_array($cand, $cols, true)) { 
			$categoryIdColumn = $cand; 
			break; 
		}
	}
}

// Determine actual price column name
$actualPriceCol = 'price';
if (!in_array('price', $cols, true)) {
	if (in_array('harga', $cols, true)) {
		$actualPriceCol = 'harga';
	} elseif (in_array('cost', $cols, true)) {
		$actualPriceCol = 'cost';
	}
}

// Build SELECT dynamically
$selectParts = [];
$selectParts[] = 'p.id as id';
$selectParts[] = 'p.' . $productsNameColumn . ' as name';

// Handle category - prefer simple string field over JOIN
if ($categoryStringColumn) {
	$selectParts[] = 'p.' . $categoryStringColumn . ' as category_name';
	$join = '';
} elseif ($categoryIdColumn) {
	$selectParts[] = 'p.' . $categoryIdColumn . ' as category_id';
	$join = ' LEFT JOIN categories c ON p.' . $categoryIdColumn . ' = c.id ';
	$selectParts[] = 'c.name as category_name';
} else {
	$selectParts[] = "'Furniture' as category_name";
	$join = '';
}

// Add price column using actual name
$selectParts[] = 'p.' . $actualPriceCol . ' as price';

// Add image column
if (in_array('image', $cols, true)) {
	$selectParts[] = 'p.image as image';
} else {
	$selectParts[] = "NULL as image";
}

// Add created_at if exists
if (in_array('created_at', $cols, true)) {
	$selectParts[] = 'p.created_at as created_at';
} else {
	$selectParts[] = "NULL as created_at";
}

$orderBy = in_array('created_at', $cols, true) ? 'p.created_at' : 'p.id';

// Select random products for featured recommendations
// Use ORDER BY RAND() for small tables, but for larger tables (>100) sample IDs in PHP to avoid heavy RAND()
$maxFeatured = 8;
$prodCount = 0;
$countRes = $conn->query("SELECT COUNT(*) AS cnt FROM products");
if ($countRes) {
	$cntRow = $countRes->fetch_assoc();
	$prodCount = isset($cntRow['cnt']) ? (int)$cntRow['cnt'] : 0;
}

if ($prodCount > 100) {
	// Lightweight sampling: fetch only ids then pick random subset in PHP
	$ids = [];
	$idRes = $conn->query("SELECT id FROM products");
	if ($idRes) {
		while ($r = $idRes->fetch_assoc()) {
			if (isset($r['id'])) $ids[] = (int)$r['id'];
		}
	}
	if (!empty($ids)) {
		shuffle($ids);
		$sample = array_slice($ids, 0, min($maxFeatured, count($ids)));
		$in = implode(',', array_map('intval', $sample));
		$sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM products p' . $join . ' WHERE p.id IN (' . $in . ')';
		$prodResult = $conn->query($sql);
	} else {
		$prodResult = false;
	}
} else {
	// small table: use RAND()
	$sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM products p' . $join . ' ORDER BY RAND() LIMIT ' . $maxFeatured;
	$prodResult = $conn->query($sql);
}

// If RAND() or sampling didn't return results, fallback to ordering by created/id
if (!$prodResult || ($prodResult && $prodResult->num_rows === 0)) {
	$sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM products p' . $join . ' ORDER BY ' . $orderBy . ' DESC LIMIT ' . $maxFeatured;
	$prodResult = $conn->query($sql);
}

if ($prodResult) {
	while ($row = $prodResult->fetch_assoc()) {
		$products[] = $row;
	}
}

// If we have products, ensure randomness in their order
if (!empty($products)) {
	shuffle($products);
}

// Fallback jika belum ada produk di database, gunakan contoh produk dari shop
if (empty($products)) {
	$products = [
		['id' => 1, 'name' => 'Kursi Minimalis', 'category_name' => 'Kursi', 'price' => 250000, 'image' => 'images/product-1.png'],
		['id' => 2, 'name' => 'Kursi Santai', 'category_name' => 'Kursi', 'price' => 450000, 'image' => 'images/product-2.png'],
		['id' => 3, 'name' => 'Sofa Tamu', 'category_name' => 'Sofa', 'price' => 2250000, 'image' => 'images/sofa.png'],
		['id' => 4, 'name' => 'Meja Kopi', 'category_name' => 'Meja', 'price' => 490000, 'image' => 'images/product-1.png'],
		['id' => 5, 'name' => 'Lemari Pakaian', 'category_name' => 'Lemari', 'price' => 2350000, 'image' => 'images/product-2.png'],
		['id' => 6, 'name' => 'Lampu Gantung', 'category_name' => 'Lampu', 'price' => 250000, 'image' => 'images/product-1.png'],
	];
}

// Fetch delivered/completed order notifications for the logged-in user
$orderStatusNotifications = [];
$orderStatusStmt = $conn->prepare("SELECT order_id, status, updated_at FROM orders WHERE user_id = ? AND status IN ('delivered', 'completed', 'selesai', 'finished') ORDER BY updated_at DESC LIMIT 20");
if ($orderStatusStmt) {
    $orderStatusStmt->bind_param('i', $userId);
    $orderStatusStmt->execute();
    $statusResult = $orderStatusStmt->get_result();
    while ($statusRow = $statusResult->fetch_assoc()) {
        $orderStatusNotifications[] = $statusRow;
    }
    $orderStatusStmt->close();
}

// Fetch categories from database
$categories = [];
$catResult = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
if ($catResult) {
    while ($row = $catResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Fetch store locations from database
$storeLocations = [];
// Check if table exists first
$tableCheck = $conn->query("SHOW TABLES LIKE 'store_locations'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $locResult = $conn->query("SELECT id, name, latitude, longitude, address FROM store_locations WHERE latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY id ASC");
    if ($locResult && $locResult->num_rows > 0) {
        while ($row = $locResult->fetch_assoc()) {
            $storeLocations[] = [
                'id' => (int)$row['id'],
                'name' => (string)($row['name'] ?? 'Toko'),
                'latitude' => (float)($row['latitude'] ?? 0),
                'longitude' => (float)($row['longitude'] ?? 0),
                'address' => (string)($row['address'] ?? '')
            ];
        }
    }
}

// Normalize products array to ensure JSON encoding works properly
$normalizedProducts = [];
foreach ($products as $p) {
	$img = '';
	if (!empty($p['image'])) {
		$imgCandidate = (string)$p['image'];
		$found = false;
		$tryPaths = [];
		$tryPaths[] = realpath(__DIR__ . '/' . $imgCandidate);
		$tryPaths[] = realpath(__DIR__ . '/../' . ltrim($imgCandidate, '/'));
		$tryPaths[] = realpath(__DIR__ . '/../images/' . basename($imgCandidate));
		foreach ($tryPaths as $tp) {
			if ($tp && file_exists($tp)) { $found = true; break; }
		}
		if ($found) {
			$img = $imgCandidate;
		} else {
			$img = 'images/product-1.png';
		}
	} else {
		$img = 'images/product-1.png';
	}

	$normalizedProducts[] = [
		'id' => isset($p['id']) ? (int)$p['id'] : 0,
		'name' => isset($p['name']) ? (string)$p['name'] : '',
		'category_name' => isset($p['category_name']) ? (string)$p['category_name'] : 'Furniture',
		'price' => isset($p['price']) ? (int)$p['price'] : 0,
		'image' => $img,
		'created_at' => isset($p['created_at']) ? (string)$p['created_at'] : ''
	];
}

$productsJson = json_encode($normalizedProducts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$orderStatusNotificationsJson = json_encode($orderStatusNotifications, JSON_UNESCAPED_UNICODE);

$storeLocationsForJson = [];
foreach ($storeLocations as $loc) {
	$storeLocationsForJson[] = [
		'id' => (int)($loc['id'] ?? 0),
		'name' => (string)($loc['name'] ?? 'Toko'),
		'latitude' => (float)($loc['latitude'] ?? 0),
		'longitude' => (float)($loc['longitude'] ?? 0),
		'address' => (string)($loc['address'] ?? '')
	];
}
$storeLocationsJson = json_encode($storeLocationsForJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (!$storeLocationsJson || $storeLocationsJson === 'null' || $storeLocationsJson === '[]') {
	$storeLocationsJson = '[]';
	// Log issue
	error_log('Warning: storeLocationsJson encoding issue. Count: ' . count($storeLocations));
}

if (!$productsJson || $productsJson === 'null' || $productsJson === 'false' || strlen(trim($productsJson)) < 5) {
	$productsJson = json_encode([
		['id' => 1, 'name' => 'Kursi Minimalis', 'category_name' => 'Kursi', 'price' => 250000, 'image' => 'images/product-1.png', 'created_at' => ''],
		['id' => 2, 'name' => 'Kursi Santai', 'category_name' => 'Kursi', 'price' => 450000, 'image' => 'images/product-2.png', 'created_at' => ''],
		['id' => 3, 'name' => 'Sofa Tamu', 'category_name' => 'Sofa', 'price' => 2250000, 'image' => 'images/sofa.png', 'created_at' => ''],
		['id' => 4, 'name' => 'Meja Kopi', 'category_name' => 'Meja', 'price' => 490000, 'image' => 'images/product-1.png', 'created_at' => ''],
		['id' => 5, 'name' => 'Lemari Pakaian', 'category_name' => 'Lemari', 'price' => 2350000, 'image' => 'images/product-2.png', 'created_at' => ''],
		['id' => 6, 'name' => 'Lampu Gantung', 'category_name' => 'Lampu', 'price' => 250000, 'image' => 'images/product-1.png', 'created_at' => '']
	]);
}
?>

<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta name="author" content="Furni">
		<link rel="shortcut icon" href="../logo.png">

		<meta name="description" content="" />
		<meta name="keywords" content="bootstrap, bootstrap4" />
		<!-- Bootstrap CSS -->
		<link href="../css/bootstrap.min.css" rel="stylesheet">
		<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
		<link href="../css/tiny-slider.css" rel="stylesheet">
		<link href="../css/style.css" rel="stylesheet">

		<style>
		/* Slider layout tweaks for Featured Products */
		.my-slider-container { padding: 20px 0; overflow: hidden; }
		#featuredSlider { display: flex; align-items: stretch; gap: 16px; }
		#featuredSlider .slide { flex: 0 0 auto; width: 260px; }
		#featuredSlider .product-card { width: 100%; }
		#featuredSlider img { display: block; width: 100%; height: 220px; object-fit: contain; }
		@media (max-width: 991.98px) { #featuredSlider .slide { width: 220px; } }
		@media (max-width: 767.98px) { #featuredSlider .slide { width: 90%; } }
		</style>
		<meta name="current-user-id" content="<?php echo htmlspecialchars($userId); ?>">
		<title>Offo Living - Furnitur Berkualitas Asli Jawa </title>
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

					<li class="nav-item active">
						<a class="nav-link" href="user_page.php">Home</a>
					</li>

					<li class="nav-item">
						<a class="nav-link" href="shop.php">Shop</a>
					</li>

					<li class="nav-item">
						<a class="nav-link" href="about.php">About Us</a>
					</li>

					<li class="nav-item">
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

		<!-- Start Hero Section -->
			<div class="hero">
				<div class="container">
					<div class="row justify-content-between">
						<div class="col-lg-5">
							<div class="intro-excerpt">
								<h1>Modern Interior <span clsas="d-block">Design Studio</span></h1>
								<p class="mb-4">Terpercaya untuk segala Furnitur yang berkualitas dan terjamin ke asliannya hingga seluruh Indonesia</p>
								<a href="shop.php" class="btn btn-white-outline">Explore</a>
							</div>
						</div>
						<div class="col-lg-7">
							<div class="hero-img-wrap">
								<img src="../images/couch.png" class="img-fluid">
							</div>
						</div>
					</div>
				</div>
			</div>
		<!-- End Hero Section -->

		<!-- Start Product Section -->
		<div class="product-section">
			<div class="container">
				<div class="row mb-5">
					<div class="col-md-12">
						<div class="d-flex align-items-center justify-content-between">
							<div>
								<h2 class="mb-1 fw-bold">PRODUK PILIHAN</h2>
								<p class="text-muted mb-0">Koleksi furnitur terbaik pilihan kami untuk Anda.</p>
								<!-- Debug: featured products info (remove in production) -->
								<div id="debugFeaturedInfo" style="font-size:12px;color:#666;margin-top:6px;"></div>
							</div>
							<a href="shop.php" class="btn btn-primary">Lihat Semua →</a>
						</div>
					</div>
				</div>

				<div class="my-slider-container">
					<div class="my-slider" id="featuredSlider"></div>
				</div>
		</div>
	</div>
	<!-- End Product Section -->
		<!-- Start Why Choose Us Section -->
		<div class="why-choose-section">
			<div class="container">
				<div class="row justify-content-between">
					<div class="col-lg-6">
						<h2 class="section-title">Kenapa Offo Living?</h2>
						<p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Voluptatem minus aut pariatur consequatur aperiam, fuga ut corporis cumque id dignissimos dolorum corrupti debitis blanditiis molestiae facere ratione esse numquam necessitatibus?</p>

						<div class="row my-5">
							<div class="col-6 col-md-6">
								<div class="feature">
									<div class="icon">
										<img src="../images/truck.svg" alt="Image" class="imf-fluid">
									</div>
									<h3>Fast &amp; Free Shipping</h3>
									<p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Numquam rerum magni animi, a voluptate, assumenda tenetur inventore incidunt obcaecati dignissimos et asperiores nostrum perferendis sunt nesciunt quos! Nemo, ipsam eaque.</p>
								</div>
							</div>

							<div class="col-6 col-md-6">
								<div class="feature">
									<div class="icon">
										<img src="../images/bag.svg" alt="Image" class="imf-fluid">
									</div>
									<h3>Easy to Shop</h3>
									<p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Numquam rerum magni animi, a voluptate, assumenda tenetur inventore incidunt obcaecati dignissimos et asperiores nostrum perferendis sunt nesciunt quos! Nemo, ipsam eaque.</p>
								</div>
							</div>

							<div class="col-6 col-md-6">
								<div class="feature">
									<div class="icon">
										<img src="../images/support.svg" alt="Image" class="imf-fluid">
									</div>
									<h3>24/7 Support</h3>
									<p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Numquam rerum magni animi, a voluptate, assumenda tenetur inventore incidunt obcaecati dignissimos et asperiores nostrum perferendis sunt nesciunt quos! Nemo, ipsam eaque.</p>
								</div>
							</div>

							<div class="col-6 col-md-6">
								<div class="feature">
									<div class="icon">
										<img src="../images/return.svg" alt="Image" class="imf-fluid">
									</div>
									<h3>Hassle Free Returns</h3>
									<p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Numquam rerum magni animi, a voluptate, assumenda tenetur inventore incidunt obcaecati dignissimos et asperiores nostrum perferendis sunt nesciunt quos! Nemo, ipsam eaque.</p>
								</div>
							</div>

						</div>
					</div>

					<div class="col-lg-5">
						<div class="img-wrap">
							<img src="../images/why-choose-us-img.jpg" alt="Image" class="img-fluid">
						</div>
					</div>

				</div>
			</div>
		</div>
		<!-- End Why Choose Us Section -->

		<!-- Start We Help Section -->
		<div class="we-help-section">
			<div class="container">
				<div class="row justify-content-between">
					<div class="col-lg-7 mb-5 mb-lg-0">
						<div class="imgs-grid">
							<div class="grid grid-2"><img src="../images/img-grid-2.jpg" alt="Furni"></div>
							<div class="grid grid-3"><img src="../images/img-grid-3.jpg" alt="Furni"></div>
						</div>
					</div>
					<div class="col-lg-5 ps-lg-5">
						<h2 class="section-title mb-4">We Help You Make Modern Interior Design</h2>
						<p>Lorem ipsum, dolor sit amet consectetur adipisicing elit. Asperiores tenetur veritatis nihil ab quam sit iure magni, nisi adipisci ad nesciunt illo non quia doloremque ea, temporibus esse architecto corporis.</p>

						<ul class="list-unstyled custom-list my-4">
							<li>Lorem ipsum dolor sit, amet consectetur adipisicing elit. Aspernatur excepturi cumque omnis velit animi porro autem obcaecati corrupti ut, quas laborum repellat deleniti ea dolorem quis odio? Sunt, aliquam! Ut?</li>
							<li>Lorem ipsum dolor sit, amet consectetur adipisicing elit. Aspernatur excepturi cumque omnis velit animi porro autem obcaecati corrupti ut, quas laborum repellat deleniti ea dolorem quis odio? Sunt, aliquam! Ut?</li>
							<li>Lorem ipsum dolor sit, amet consectetur adipisicing elit. Aspernatur excepturi cumque omnis velit animi porro autem obcaecati corrupti ut, quas laborum repellat deleniti ea dolorem quis odio? Sunt, aliquam! Ut?</li>
							<li>Lorem ipsum dolor sit, amet consectetur adipisicing elit. Aspernatur excepturi cumque omnis velit animi porro autem obcaecati corrupti ut, quas laborum repellat deleniti ea dolorem quis odio? Sunt, aliquam! Ut?</li>
						</ul>
						<p><a href="about.php" class="btn">Explore</a></p>
					</div>
				</div>
			</div>
		</div>
		<!-- End We Help Section -->

		<!-- Start Footer Section -->
		<footer class="footer-section">
			<div class="container relative">

				<div class="row g-5 mb-5">
					<div class="col-lg-4">
						<div class="mb-4 footer-logo-wrap"><a href="#" class="footer-logo">Offo Living<span>.</span></a></div>
						<p class="mb-4">Terpercaya untuk segal Furnitur yang berkualitas dan terjamin ke asliannya hingga seluruh Indonesia</p>

						<ul class="list-unstyled custom-social">
							<li><a href="#"><span class="fa fa-brands fa-facebook-f"></span></a></li>
							<li><a href="#"><span class="fa fa-brands fa-twitter"></span></a></li>
							<li><a href="#"><span class="fa fa-brands fa-instagram"></span></a></li>
							<li><a href="#"><span class="fa fa-brands fa-linkedin"></span></a></li>
						</ul>
					</div>

					<div class="col-lg-8">
						<div class="row links-wrap">
							<div class="col-6 col-sm-6 col-md-3">
								<h4 style="font-size: 14px; font-weight: 700; margin-bottom: 15px;">Kategori Produk</h4>
								<ul class="list-unstyled">
									<?php foreach ($categories as $cat): ?>
										<li><a href="shop.php?category=<?php echo urlencode($cat['name']); ?>"><?php echo htmlspecialchars($cat['name']); ?></a></li>
									<?php endforeach; ?>
									<?php if (empty($categories)): ?>
										<li><a href="shop.php">Lihat Semua Produk</a></li>
									<?php endif; ?>
								</ul>
							</div>

							<div class="col-6 col-sm-6 col-md-3">
								<h4 style="font-size: 14px; font-weight: 700; margin-bottom: 15px;">Cari & Lokasi</h4>
								<ul class="list-unstyled">
									<li><a href="shop.php">Cari Produk</a></li>
									<li><a href="#storeMapModal" data-bs-toggle="modal">Peta Lokasi Toko</a></li>
									<li><a href="about.php">Tentang Kami</a></li>
								</ul>
							</div>

							<div class="col-6 col-sm-6 col-md-3">
								<h4 style="font-size: 14px; font-weight: 700; margin-bottom: 15px;">Bantuan</h4>
								<ul class="list-unstyled">
									<li><a href="pesanan.php">Pesanan Saya</a></li>
									<li><a href="chat.php">Chat Bantuan</a></li>
									<li><a href="contact.php">Kebijakan Privasi</a></li>
								</ul>
							</div>
						</div>
					</div>
				</div>

				<!-- Informasi Lokasi Toko -->
				<?php if (!empty($storeLocations)): ?>
				<div class="row mt-5 pt-5" style="border-top: 1px solid #e0e7ff;">
					<div class="col-12">
						<h4 style="font-size: 16px; font-weight: 700; margin-bottom: 20px;">Lokasi Toko Kami</h4>
					</div>
					<div class="col-12">
						<div style="width: 100%; height: 300px; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" id="footerStoreMap"></div>
					</div>
					<div class="col-12 mt-3">
						<div class="row">
							<?php foreach ($storeLocations as $idx => $store): ?>
								<?php if ($idx < 4): ?>
									<div class="col-6 col-md-3 mb-3">
										<div style="padding: 12px; background: #f7fafc; border-radius: 6px;">
											<h6 style="font-size: 13px; font-weight: 700; margin-bottom: 5px;"><?php echo htmlspecialchars($store['name']); ?></h6>
											<p style="font-size: 12px; color: #666; margin: 0;">
												<?php echo htmlspecialchars(substr($store['address'] ?? 'Alamat', 0, 40)); ?>...
											</p>
										</div>
									</div>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				<?php endif; ?>
			</div>
		</footer>
		<!-- End Footer Section -->

		<!-- Store Locations Modal -->
		<?php if (!empty($storeLocations)): ?>
		<div class="modal fade" id="storeMapModal" tabindex="-1" aria-labelledby="storeMapLabel" aria-hidden="true">
			<div class="modal-dialog modal-lg">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="storeMapLabel">Peta Lokasi Toko Kami</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body" style="padding: 0;">
						<div style="width: 100%; height: 500px;" id="fullStoreMap"></div>
					</div>
				</div>
			</div>
		</div>
		<?php endif; ?>
		<script src="../js/bootstrap.bundle.min.js"></script>
		<script src="../js/tiny-slider.js"></script>
		<script src="../js/custom.js"></script>
		<script src="../js/dashboard.js"></script>
		<!-- Leaflet Maps Library (OpenStreetMap - No API Key Required) -->
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
		<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
		<script>
			// Store locations dari PHP dengan debugging
			const storeLocationsRaw = <?php echo $storeLocationsJson; ?>;
			const storeLocations = Array.isArray(storeLocationsRaw) ? storeLocationsRaw : [];
			
			console.log('🗺️ Store Locations Data:', {
				rawLength: Array.isArray(storeLocationsRaw) ? storeLocationsRaw.length : 0,
				processedLength: storeLocations.length,
				data: storeLocations
			});

			let footerMap = null;
			let fullStoreMap = null;
			let fullStoreMapInitialized = false;

			function initFooterMap() {
				const mapElement = document.getElementById('footerStoreMap');
				console.log('📍 initFooterMap - element:', mapElement ? 'FOUND' : 'NOT FOUND', 'locations:', storeLocations.length);
				
				if (!mapElement) {
					console.warn('⚠️ footerStoreMap element not found');
					return;
				}
				
				if (!storeLocations || storeLocations.length === 0) {
					console.warn('⚠️ No store locations to display');
					mapElement.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #999;">Lokasi toko belum ditambahkan</div>';
					return;
				}

				try {
					// Calculate center point
					let centerLat = 0, centerLng = 0;
					storeLocations.forEach(loc => {
						const lat = parseFloat(loc.latitude);
						const lng = parseFloat(loc.longitude);
						if (!isNaN(lat) && !isNaN(lng)) {
							centerLat += lat;
							centerLng += lng;
						}
					});
					centerLat /= storeLocations.length;
					centerLng /= storeLocations.length;

					console.log('📌 Map center:', { lat: centerLat, lng: centerLng });

					// Initialize map
					footerMap = L.map(mapElement, { 
						attributionControl: true,
						zoomControl: true 
					}).setView([centerLat, centerLng], 9);

					// Add tile layer
					L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
						attribution: '© OpenStreetMap contributors',
						maxZoom: 19,
						minZoom: 3
					}).addTo(footerMap);

					// Add markers
					let markerCount = 0;
					storeLocations.forEach((loc, idx) => {
						const lat = parseFloat(loc.latitude);
						const lng = parseFloat(loc.longitude);
						
						if (!isNaN(lat) && !isNaN(lng)) {
							const marker = L.circleMarker([lat, lng], {
								radius: 8,
								fillColor: '#3b5d50',
								color: '#fff',
								weight: 2,
								opacity: 1,
								fillOpacity: 0.8
							}).addTo(footerMap);

							marker.bindPopup(`
								<div style="font-size: 13px; min-width: 200px;">
									<strong style="color: #3b5d50;">${loc.name}</strong>
									<br/><small>${loc.address || 'Alamat tidak tersedia'}</small>
								</div>
							`);

							markerCount++;
						}
					});

					console.log('✅ Footer map initialized with', markerCount, 'markers');
				} catch (error) {
					console.error('❌ Error initializing footer map:', error);
				}
			}

			function initFullMap() {
				const mapElement = document.getElementById('fullStoreMap');
				console.log('🗺️ initFullMap - element:', mapElement ? 'FOUND' : 'NOT FOUND');
				
				if (!mapElement) {
					console.warn('⚠️ fullStoreMap element not found');
					return;
				}
				
				if (!storeLocations || storeLocations.length === 0) {
					console.warn('⚠️ No store locations to display in full map');
					return;
				}

				try {
					// Calculate center point
					let centerLat = 0, centerLng = 0;
					storeLocations.forEach(loc => {
						const lat = parseFloat(loc.latitude);
						const lng = parseFloat(loc.longitude);
						if (!isNaN(lat) && !isNaN(lng)) {
							centerLat += lat;
							centerLng += lng;
						}
					});
					centerLat /= storeLocations.length;
					centerLng /= storeLocations.length;

					// Initialize map
					fullStoreMap = L.map(mapElement, { 
						attributionControl: true,
						zoomControl: true 
					}).setView([centerLat, centerLng], 10);

					// Add tile layer
					L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
						attribution: '© OpenStreetMap contributors',
						maxZoom: 19,
						minZoom: 3
					}).addTo(fullStoreMap);

					// Add markers
					let markerCount = 0;
					storeLocations.forEach((loc, idx) => {
						const lat = parseFloat(loc.latitude);
						const lng = parseFloat(loc.longitude);
						
						if (!isNaN(lat) && !isNaN(lng)) {
							const marker = L.circleMarker([lat, lng], {
								radius: 10,
								fillColor: '#3b5d50',
								color: '#fff',
								weight: 2,
								opacity: 1,
								fillOpacity: 0.8
							}).addTo(fullStoreMap);

							marker.bindPopup(`
								<div style="font-size: 14px; min-width: 250px;">
									<strong style="color: #3b5d50; display: block; margin-bottom: 8px;">${loc.name}</strong>
									<small style="color: #666;">${loc.address || 'Alamat tidak tersedia'}</small>
								</div>
							`);

							markerCount++;
						}
					});

					fullStoreMapInitialized = true;
					console.log('✅ Full map initialized with', markerCount, 'markers');
				} catch (error) {
					console.error('❌ Error initializing full map:', error);
				}
			}

			// Initialize footer map on page load
			document.addEventListener('DOMContentLoaded', () => {
				console.log('✓ DOMContentLoaded - initializing footer map');
				setTimeout(initFooterMap, 100);
			});

			// Handle modal opening for full map
			const storeMapModal = document.getElementById('storeMapModal');
			if (storeMapModal) {
				storeMapModal.addEventListener('shown.bs.modal', () => {
					console.log('📱 Modal shown - fullStoreMapInitialized:', fullStoreMapInitialized);
					if (!fullStoreMapInitialized) {
						setTimeout(() => {
							initFullMap();
							if (fullStoreMap) {
								fullStoreMap.invalidateSize();
							}
						}, 100);
					} else if (fullStoreMap) {
						setTimeout(() => {
							fullStoreMap.invalidateSize();
						}, 50);
					}
				});
			}
		</script>
	</body>
</html>
