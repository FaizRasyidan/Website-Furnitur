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

// Fetch user addresses
// Fetch user addresses (only if table exists)
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

// Determine which address to use for shipping: prefer user-selected, then primary, then first
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

// Load product data from database dynamically
function dbFirstColumn(array $candidates, array $available, $default = null) {
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $available, true)) {
            return $candidate;
        }
    }
    return $default;
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
$imageColumn = dbFirstColumn(['image', 'gambar'], $cols);
$stockColumn = dbFirstColumn(['stock', 'stok'], $cols);

$categoryIsString = in_array($categoryColumn, ['category', 'kategori'], true);

$products = [];
$selectParts = ['p.id as id'];
$selectParts[] = $productsNameColumn ? "p.$productsNameColumn as name" : 'NULL as name';
if ($categoryColumn) {
    if ($categoryIsString) {
        $selectParts[] = "p.$categoryColumn as category_name";
        $join = '';
    } else {
        $selectParts[] = "p.$categoryColumn as category_id";
        $selectParts[] = 'c.name as category_name';
        $join = ' LEFT JOIN categories c ON p.' . $categoryColumn . ' = c.id ';
    }
} else {
    $selectParts[] = 'NULL as category_name';
    $join = '';
}
$selectParts[] = $priceColumn ? "p.$priceColumn as price" : '0 as price';
$selectParts[] = $imageColumn ? "p.$imageColumn as image" : 'NULL as image';
$selectParts[] = $stockColumn ? "p.$stockColumn as stock" : '0 as stock';
$orderBy = in_array('created_at', $cols, true) ? 'p.created_at' : 'p.id';
$sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM products p' . $join . ' ORDER BY ' . $orderBy . ' DESC';
$productResult = $conn->query($sql);
if ($productResult) {
    while ($row = $productResult->fetch_assoc()) {
        $products[] = $row;
    }
}

$categories = [];
$categoryIdToName = [];
$catRes = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
if ($catRes) {
    while ($row = $catRes->fetch_assoc()) {
        $categories[] = $row;
        $categoryIdToName[(string)$row['id']] = $row['name'];
    }
}

foreach ($products as &$productRow) {
    if (!empty($productRow['category_name']) && ctype_digit((string)$productRow['category_name']) && isset($categoryIdToName[$productRow['category_name']])) {
        $productRow['category_name'] = $categoryIdToName[$productRow['category_name']];
    }
}
unset($productRow);

$categoryOptions = [];
foreach ($categories as $cat) {
    if (!empty($cat['name'])) {
        $categoryOptions[$cat['name']] = true;
    }
}
foreach ($products as $p) {
    if (!empty($p['category_name'])) {
        $categoryOptions[$p['category_name']] = true;
    }
}
$categoryOptions = array_keys($categoryOptions);

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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="author" content="Furni">
  <link rel="shortcut icon" href="logo.png">

  <meta name="description" content="" />
  <meta name="keywords" content="bootstrap, bootstrap4" />
  <meta name="current-user-id" content="<?php echo htmlspecialchars($userId); ?>">

		<!-- 	Bootstrap CSS -->
		<link href="../css/bootstrap.min.css" rel="stylesheet">
		<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
		<link href="../css/tiny-slider.css" rel="stylesheet">
		<link href="../css/style.css" rel="stylesheet">
		<style>
			.product-grid .product-card {
				min-height: 100%;
				transition: transform .2s ease, box-shadow .2s ease;
			}
			.product-grid .product-card:hover {
				transform: translateY(-5px);
				box-shadow: 0 10px 30px rgba(0,0,0,.08);
			}
			.product-card .card-img-top {
				width: 100%;
				height: 260px;
				object-fit: contain;
				object-position: center;
				background-color: #f8f9fa;
			}
			.filter-panel {
				position: sticky;
				top: 120px;
			}
			.filter-panel .list-group-item {
				border: none;
				padding-left: 0;
				padding-right: 0;
			}
			.filter-panel .form-check-input {
				margin-top: .25rem;
			}
			.filter-panel h5 {
				font-size: 0.95rem;
				letter-spacing: .03em;
			}
			@media (max-width: 991.98px) {
				.filter-panel {
					position: static;
					top: auto;
				}
			}
		</style>
		<title>Offo Living - Furnitur Berkualitas Asli Jawa</title>
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

					<li class="nav-item active">
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
								<h1>Shop</h1>
							</div>
						</div>
						<div class="col-lg-7">
							
						</div>
					</div>
				</div>
			</div>
		<!-- End Hero Section -->

		

		<div class="furni-section product-section before-footer-section">
		    <div class="container">
		      	<div class="row">
		      		<!-- Products Grid - Left Side -->
		      		<div id="productCol" class="col-lg-8 mb-4">
		      			<div class="d-flex align-items-center justify-content-between mb-3">
		      				<div>
		      					<h2 class="mb-1 fw-bold">JELAJAHI PRODUK KAMI</h2>
		      					<p class="text-muted mb-0">Temukan furnitur terbaik untuk rumah Anda.</p>
		      				</div>
		      				<button class="btn btn-outline-secondary btn-sm" id="toggleFilter">
		      					<i class="fa fa-filter"></i> SEMBUNYIKAN FILTER
		      				</button>
		      			</div>
		      			<div class="row product-grid g-4" id="productGrid"></div>
		      			<div class="row mt-4">
		      				<div class="col-12 text-center">
		      					<button class="btn btn-outline-primary" id="loadMoreProducts" style="display: none;">
		      						Tampilkan Lebih Banyak
		      					</button>
		      				</div>
		      			</div>
		      		</div>

		      		<!-- Filter Sidebar - Right Side -->
		      		<div id="filterCol" class="col-lg-4">
		      			<div class="filter-panel p-3 bg-light rounded">
		      				<div id="filterContent" class="filter-content">
		      					<div class="filter-section mb-4 pb-3 border-bottom">
		      						<h5 class="fw-bold mb-3">KATEGORI</h5>
		      						<div class="list-group list-group-flush">
		      							<label class="list-group-item ps-0">
		      								<input type="radio" name="category" value="all" class="form-check-input category-filter" checked>
		      								<span class="ms-2">Semua Kategori</span>
		      							</label>
										<?php foreach ($categoryOptions as $categoryOption): ?>
											<label class="list-group-item ps-0">
												<input type="radio" name="category" value="<?php echo htmlspecialchars(strtolower(trim($categoryOption))); ?>" class="form-check-input category-filter">
												<span class="ms-2"><?php echo htmlspecialchars($categoryOption); ?></span>
											</label>
										<?php endforeach; ?>
		      						</div>
		      					</div>

		      					<div class="filter-section mb-4 pb-3 border-bottom">
		      						<h5 class="fw-bold mb-3">PRICE</h5>
		      						<div class="mb-2">
		      							<label class="form-label small">Min</label>
		      							<input type="number" id="minPrice" class="form-control form-control-sm" placeholder="0" value="0">
		      						</div>
		      						<div class="mb-3">
		      							<label class="form-label small">Max</label>
		      							<input type="number" id="maxPrice" class="form-control form-control-sm" placeholder="999999999" value="999999999">
		      						</div>
		      					</div>

		      					<div class="filter-section mb-4">
		      						<h5 class="fw-bold mb-3">SORT BY</h5>
		      						<select id="sortOrder" class="form-select form-select-sm">
		      							<option value="default">RELEVAN</option>
		      							<option value="desc">Harga Terbesar - Terkecil</option>
		      							<option value="asc">Harga Terkecil - Terbesar</option>
		      						</select>
		      					</div>

		      					<button class="btn btn-danger w-100" id="resetFilter">
		      						RESET FILTER
		      					</button>
		      				</div>
		      			</div>
		      		</div>
		      	</div>
		    </div>
		</div>


		<?php include 'footer.php'; ?>

		<script src="../js/bootstrap.bundle.min.js"></script>
		<script src="../js/tiny-slider.js"></script>
		<script src="../js/custom.js"></script>
		<script>
				const products = <?php echo json_encode(array_map(function ($p) {
    return [
        'id' => $p['id'],
        'name' => $p['name'] ?? 'Produk',
        'category' => $p['category_name'] ?? 'Furniture',
        'price' => isset($p['price']) ? (int)$p['price'] : 0,
        'image' => !empty($p['image']) ? '../' . $p['image'] : '../images/product-1.png',
        'stock' => isset($p['stock']) ? (int)$p['stock'] : 0,
    ];
}, $products), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
			const formatRupiah = (value) => {
				return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
			};

			const productGrid = document.getElementById('productGrid');
			const categoryFilters = document.querySelectorAll('.category-filter');
			const minPriceInput = document.getElementById('minPrice');
			const maxPriceInput = document.getElementById('maxPrice');
			const sortOrderSelect = document.getElementById('sortOrder');
			const toggleFilterBtn = document.getElementById('toggleFilter');
			const filterContent = document.getElementById('filterContent');
			const filterCol = document.getElementById('filterCol');
			const productCol = document.getElementById('productCol');
			const resetFilterBtn = document.getElementById('resetFilter');
			const loadMoreBtn = document.getElementById('loadMoreProducts');

			let currentFilteredProducts = [];
			let itemsPerPage = 8;
			let currentDisplayCount = itemsPerPage;

			function buildProductCard(product) {
			const productUrl = `product_detail.php?id=${encodeURIComponent(product.id)}`;
				return `
					<div class="col-6 col-md-4 col-xl-3">
						<a href="${productUrl}" class="text-decoration-none text-body">
							<div class="card product-card h-100 border-0 shadow-sm">
								<img src="${product.image}" class="card-img-top img-fluid" alt="${product.name}">
								<div class="card-body p-3">
									<div class="d-flex justify-content-between align-items-start mb-2">
										<span class="badge bg-secondary text-uppercase small">${product.category}</span>
										<strong class="text-primary">${formatRupiah(product.price)}</strong>
									</div>
									<h3 class="h6 product-title mb-2">${product.name}</h3>
									<p class="small text-muted mb-0">Furniture berkualitas untuk ruangan Anda.</p>
								</div>
							</div>
						</a>
					</div>
				`;
			}

			function renderProducts(items) {
				const itemsToShow = items.slice(0, currentDisplayCount);
				productGrid.innerHTML = itemsToShow.map(buildProductCard).join('');
				
				if (currentDisplayCount < items.length) {
					loadMoreBtn.style.display = 'block';
				} else {
					loadMoreBtn.style.display = 'none';
				}
			}

			function applyFilter() {
				const category = document.querySelector('input[name="category"]:checked').value;
				const minPrice = parseFloat(minPriceInput.value) || 0;
				const maxPrice = parseFloat(maxPriceInput.value) || 999999999;
				const sortOrder = sortOrderSelect.value;

                const normalizedFilter = category.trim().toLowerCase();
                let filtered = products.filter(product => {
                    const productCategory = (product.category || '').trim().toLowerCase();
                    const matchCategory = normalizedFilter === 'all' || productCategory === normalizedFilter;
                    const matchPrice = product.price >= minPrice && product.price <= maxPrice;
                    return matchCategory && matchPrice;
                });

				if (sortOrder === 'asc') {
					filtered.sort((a, b) => a.price - b.price);
				} else if (sortOrder === 'desc') {
					filtered.sort((a, b) => b.price - a.price);
				}

				currentFilteredProducts = filtered;
				currentDisplayCount = itemsPerPage;
				renderProducts(filtered);
			}

			function resetFilter() {
				document.querySelector('input[name="category"][value="all"]').checked = true;
				minPriceInput.value = '0';
				maxPriceInput.value = '999999999';
				sortOrderSelect.value = 'default';
				currentDisplayCount = itemsPerPage;
				applyFilter();
			}

			function toggleFilterSidebar() {
				const hidden = filterCol.classList.toggle('d-none');
				if (hidden) {
					productCol.classList.remove('col-lg-8');
					productCol.classList.add('col-lg-12');
					toggleFilterBtn.innerHTML = '<i class="fa fa-filter"></i> TAMPILKAN FILTER';
				} else {
					productCol.classList.remove('col-lg-12');
					productCol.classList.add('col-lg-8');
					toggleFilterBtn.innerHTML = '<i class="fa fa-filter"></i> SEMBUNYIKAN FILTER';
				}
			}

			loadMoreBtn.addEventListener('click', () => {
				currentDisplayCount += itemsPerPage;
				renderProducts(currentFilteredProducts);
			});

			categoryFilters.forEach(filter => {
				filter.addEventListener('change', applyFilter);
			});

			minPriceInput.addEventListener('change', applyFilter);
			maxPriceInput.addEventListener('change', applyFilter);
			sortOrderSelect.addEventListener('change', applyFilter);
			resetFilterBtn.addEventListener('click', resetFilter);
			toggleFilterBtn.addEventListener('click', toggleFilterSidebar);

			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', applyFilter);
			} else {
				applyFilter();
			}

			 // Notification system
	const userNotificationKey = 'notifications_' + <?php echo $userId; ?>;
	function addNotification(notif) {
		if (window.addCommonNotification) return window.addCommonNotification(notif);
		const notifications = JSON.parse(localStorage.getItem(userNotificationKey) || '[]');
		notifications.unshift(notif);
		if (notifications.length > 10) notifications.pop();
		localStorage.setItem(userNotificationKey, JSON.stringify(notifications));
		if (window.updateNotificationBadgeCommon) window.updateNotificationBadgeCommon();
	}

	function updateNotificationBadge() {
		if (window.updateNotificationBadgeCommon) return window.updateNotificationBadgeCommon();
		const notifications = JSON.parse(localStorage.getItem(userNotificationKey) || '[]');
		const badge = document.getElementById('notifBadge');
		if (badge) badge.textContent = notifications.length;
		const dropdown = document.getElementById('notificationItems');
		if (!dropdown) return;
        if (notifications.length === 0) {
            dropdown.innerHTML = '<li class="dropdown-item text-center text-muted">Tidak ada notifikasi</li>';
        } else {
            let html = '';
            notifications.forEach(notif => {
                const iconClass = notif.type === 'success' ? 'fa-check-circle text-success' : 'fa-info-circle text-info';
                html += `
                    <li class="dropdown-item" style="border-bottom: 1px solid #eee; padding: 10px;">
                        <div style="display: flex; gap: 10px;">
                            <i class="fa ${iconClass}" style="font-size: 1.2em;"></i>
                            <div style="flex: 1;">
                                <small>${notif.message}</small><br>
                                <small class="text-muted">${notif.time}</small>
                            </div>
                        </div>
                    </li>
                `;
            });
            dropdown.innerHTML = html;
        }
    }
		</script>
	</body>

</html>
