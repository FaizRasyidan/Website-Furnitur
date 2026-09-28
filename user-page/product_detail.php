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

$productsNameColumn = dbFirstColumn(['name', 'product_name', 'nama', 'title'], $cols) ?? 'name';
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

$categoryColumn = dbFirstColumn(['category_id', 'cat_id', 'kategori_id', 'category', 'kategori', 'categoryId'], $cols);
$categoryIsString = in_array($categoryColumn, ['category', 'kategori'], true);
$categorySelect = 'NULL as category_name';
$categoryJoin = '';
if ($categoryColumn) {
    if ($categoryIsString) {
        $categorySelect = 'p.' . $categoryColumn . ' as category_name';
    } else {
        $categorySelect = 'c.name as category_name';
        $categoryJoin = ' LEFT JOIN categories c ON p.' . $categoryColumn . ' = c.id ';
    }
}

// Fetch product data from database
$productData = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $productId = intval($_GET['id']);
    $selectParts = [
        'p.id as id',
        'p.' . $productsNameColumn . ' as name',
        $categorySelect,
        $priceColumn ? 'p.' . $priceColumn . ' as price' : 'NULL as price',
        $stockColumn ? 'p.' . $stockColumn . ' as stock' : 'NULL as stock',
        $descColumn ? 'p.' . $descColumn . ' as description' : 'NULL as description',
        $woodTypeColumn ? 'p.' . $woodTypeColumn . ' as wood_type' : 'NULL as wood_type',
        $colorColumn ? 'p.' . $colorColumn . ' as color' : 'NULL as color',
        $imageFrontColumn ? 'p.' . $imageFrontColumn . ' as image_front' : 'NULL as image_front',
        $imageSideColumn ? 'p.' . $imageSideColumn . ' as image_side' : 'NULL as image_side',
        $imageBackColumn ? 'p.' . $imageBackColumn . ' as image_back' : 'NULL as image_back',
        $image45Column ? 'p.' . $image45Column . ' as image_45degree' : 'NULL as image_45degree',
        $imageColumn ? 'p.' . $imageColumn . ' as image' : 'NULL as image'
    ];
    $query = 'SELECT ' . implode(', ', $selectParts) . ' FROM products p ' . $categoryJoin . ' WHERE p.id = ?';
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $productData = $result->fetch_assoc();
        }
        $stmt->close();
    }
}

// If no product data, try to get from query string (fallback for old URLs)
$productName = $_GET['product'] ?? null;
if (!$productData && $productName) {
    $selectParts = [
        'p.id as id',
        'p.' . $productsNameColumn . ' as name',
        $categorySelect,
        $priceColumn ? 'p.' . $priceColumn . ' as price' : 'NULL as price',
        $stockColumn ? 'p.' . $stockColumn . ' as stock' : 'NULL as stock',
        $descColumn ? 'p.' . $descColumn . ' as description' : 'NULL as description',
        $woodTypeColumn ? 'p.' . $woodTypeColumn . ' as wood_type' : 'NULL as wood_type',
        $colorColumn ? 'p.' . $colorColumn . ' as color' : 'NULL as color',
        $imageFrontColumn ? 'p.' . $imageFrontColumn . ' as image_front' : 'NULL as image_front',
        $imageSideColumn ? 'p.' . $imageSideColumn . ' as image_side' : 'NULL as image_side',
        $imageBackColumn ? 'p.' . $imageBackColumn . ' as image_back' : 'NULL as image_back',
        $image45Column ? 'p.' . $image45Column . ' as image_45degree' : 'NULL as image_45degree',
        $imageColumn ? 'p.' . $imageColumn . ' as image' : 'NULL as image'
    ];
    $query = 'SELECT ' . implode(', ', $selectParts) . ' FROM products p ' . $categoryJoin . ' WHERE p.' . $productsNameColumn . ' = ? LIMIT 1';
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param('s', $productName);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $productData = $result->fetch_assoc();
        }
        $stmt->close();
    }
}

// Fetch available wood types and colors for dropdown options
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
?>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="author" content="Jawafurnitur">
  <link rel="shortcut icon" href="logo.png">
  <meta name="description" content="Detail produk Jawafurnitur" />
  <meta name="keywords" content="furniture, detail produk, kayu, warna" />
  <meta name="current-user-id" content="<?php echo htmlspecialchars($userId); ?>">

  <link href="../css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <link href="../css/style.css" rel="stylesheet">
  <title>Detail Produk | Jawafurnitur</title>
  <style>
    .product-detail {
      padding: 4rem 0;
    }
    .product-detail .product-gallery {
      border: 1px solid #e6e6e6;
      border-radius: 20px;
      padding: 1rem;
      background: #fff;
    }
    .product-detail .product-gallery .main-image {
      border-radius: 20px;
      width: 100%;
      height: 520px;
      max-width: 100%;
      object-fit: contain;
      object-position: center;
      display: block;
      margin: 0 auto;
      background: #f8f8f8;
    }
    .product-detail .gallery-thumbs {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 0.75rem;
      margin-top: 1rem;
    }
    .product-detail .gallery-thumbs .thumb-item {
      border-radius: 14px;
      overflow: hidden;
      border: 2px solid transparent;
      transition: border-color .2s ease, transform .2s ease;
      aspect-ratio: 1;
      background: #f8f8f8;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .product-detail .gallery-thumbs .thumb-item:hover {
      transform: translateY(-2px);
    }
    .product-detail .gallery-thumbs .thumb-item img {
      width: 100%;
      height: 100%;
      display: block;
      cursor: pointer;
      object-fit: contain;
      padding: 0.5rem;
    }
    .product-detail .gallery-thumbs .thumb-item.active {
      border-color: #3b5d50;
    }
    .product-detail .product-info {
      padding-left: 0;
    }
    .product-detail .product-info h1 {
      font-size: 2.4rem;
      margin-bottom: 1rem;
    }
    .product-detail .product-info .price {
      font-size: 2rem;
      color: #3b5d50;
      font-weight: 700;
      margin-bottom: 1rem;
    }
    .product-detail .product-info .btn-group {
      gap: 0.75rem;
    }
    .product-detail .option-group label {
      font-weight: 600;
      font-size: 0.95rem;
    }
    .product-detail .recent-products {
      padding: 4rem 0;
      background: #f7f7f7;
    }
    .recent-products .product-card {
      border: 1px solid #e6e6e6;
      border-radius: 18px;
      overflow: hidden;
      transition: transform .2s ease, box-shadow .2s ease;
    }
    .recent-products .product-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 14px 30px rgba(0,0,0,.08);
    }
    .recent-products .product-card img {
      border-bottom: 1px solid #eaeaea;
    }
    .recent-products .product-title {
      font-size: 1rem;
      font-weight: 600;
    }
    .recent-products .product-price {
      font-weight: 700;
      color: #437d6b;
    }
  </style>
</head>
<body>
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

  <div class="hero">
    <div class="container">
      <div class="row justify-content-between">
        <div class="col-lg-6">
          <div class="intro-excerpt">
            <h1>Detail Produk</h1>
            <p class="mb-0">Pilih kayu dan warna yang sesuai untuk pesanan Anda.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="furni-section product-detail">
    <div class="container">
      <div class="row g-5">
        <div class="col-lg-6">
          <div class="product-gallery">
            <img id="mainProductImage" src="../images/product-1.png" alt="Product Image" class="main-image">
            <div class="gallery-thumbs" id="galleryThumbs"></div>
          </div>
        </div>
        <div class="col-lg-6 product-info">
          <h1 id="productName">Nama Produk</h1>
          <div class="price" id="productPrice">Rp0</div>
          <p class="text-muted" id="productDescription">Deskripsi produk akan tampil di sini ketika pelanggan mengklik gambar produk.</p>

          <div class="option-group mb-4">
            <div class="mb-3">
              <label for="woodType" class="form-label">Jenis Kayu</label>
              <select id="woodType" class="form-control">
                <option value="">-- Pilih Jenis Kayu --</option>
                <?php foreach ($woodTypes as $wt): ?>
                  <option value="<?php echo htmlspecialchars($wt); ?>"><?php echo htmlspecialchars($wt); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label for="colorOption" class="form-label">Warna</label>
              <select id="colorOption" class="form-control">
                <option value="">-- Pilih Warna --</option>
                <?php foreach ($colors as $c): ?>
                  <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-4">
              <label for="quantity" class="form-label">Jumlah</label>
              <input id="quantity" type="number" min="1" value="1" class="form-control" style="max-width:140px;">
            </div>
            <div class="d-flex flex-wrap gap-2 mb-4">
              <button id="addToCart" class="btn btn-primary">Tambah ke Keranjang</button>
              <a href="cart.php" class="btn btn-outline-secondary">Lihat Keranjang</a>
            </div>
          </div>

          <div class="product-meta">
            <p><strong>Kategori:</strong> <span id="productCategory">-</span></p>
            <p><strong>Stok:</strong> <span id="productStock">Tersedia</span></p>
            <p><strong>Material:</strong> <span id="productMaterial">Kayu solid berkualitas</span></p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="recent-products">
    <div class="container">
      <div class="row mb-4">
        <div class="col-lg-8">
          <h2 class="section-title">Rekomendasi Produk</h2>
          <p class="text-muted">Lihat produk lain yang direkomendasikan dan cocok untuk pilihan Anda.</p>
        </div>
      </div>
      <div class="row g-4" id="recentProducts"></div>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <script src="../js/bootstrap.bundle.min.js"></script>
  <script src="../js/custom.js"></script>
  <script>
    // Get product data from PHP or use hardcoded fallback
    const phpProductData = <?php echo $productData ? json_encode($productData) : 'null'; ?>;
    
    let product = null;

    const normalizeImagePath = (path) => {
      if (!path) return null;
      const trimmed = path.trim();
      if (!trimmed) return null;
      if (trimmed.startsWith('http://') || trimmed.startsWith('https://') || trimmed.startsWith('../') || trimmed.startsWith('/')) {
        return trimmed;
      }
      return '../' + trimmed.replace(/^\/*/, '');
    };

    const parseOptionsFromString = (value) => {
      if (!value) return [];
      return value
        .toString()
        .split(/[,|]/)
        .map(v => v.trim())
        .filter(Boolean);
    };

    const parseImagesFromValue = (value) => {
      if (!value) return [];
      if (Array.isArray(value)) return value.flatMap(parseImagesFromValue);
      return value
        .toString()
        .split(/[,;|]+/)
        .map(v => v.trim())
        .filter(Boolean);
    };

    const buildGallery = () => {
      const gallery = [];
      const addImage = (img) => {
        const normalized = normalizeImagePath(img);
        if (normalized && !gallery.includes(normalized)) {
          gallery.push(normalized);
        }
      };

      if (phpProductData) {
        ['image_front', 'image_side', 'image_back', 'image_45degree', 'image'].forEach(field => {
          parseImagesFromValue(phpProductData[field]).forEach(addImage);
        });
      }

      if (gallery.length === 0) {
        gallery.push('../images/product-1.png');
      }
      return gallery;
    };

    if (phpProductData) {
      const gallery = buildGallery();
      product = {
        id: phpProductData.id,
        name: phpProductData.name || 'Produk',
        category: phpProductData.category_name || phpProductData.category || 'Produk',
        price: typeof phpProductData.price === 'number' ? phpProductData.price : parseFloat(phpProductData.price || 0),
        image: gallery[0],
        description: phpProductData.description || 'Deskripsi produk tidak tersedia',
        gallery,
        wood_type: phpProductData.wood_type || '',
        color: phpProductData.color || '',
        stock: typeof phpProductData.stock === 'number' ? phpProductData.stock : parseInt(phpProductData.stock || 0, 10)
      };
    } else {
      // Fallback to hardcoded products for backward compatibility
      const products = [
        { id: 1, name: 'Kursi Minimalis', category: 'kursi', price: 250000, image: '../images/product-1.png', description: 'Desain minimalis yang elegan dan nyaman untuk ruang tamu atau ruang kerja.', gallery: ['../images/product-1.png', '../images/product-2.png', '../images/product-3.png'] },
        { id: 2, name: 'Kursi Santai', category: 'kursi', price: 450000, image: '../images/product-2.png', description: 'Kursi santai dengan bantalan lembut dan rangka kayu kuat untuk kenyamanan maksimal.', gallery: ['../images/product-2.png', '../images/product-3.png', '../images/product-1.png'] },
        { id: 3, name: 'Kursi Makan', category: 'kursi', price: 550000, image: '../images/product-3.png', description: 'Kursi makan dengan finishing kayu natural yang hangat, cocok untuk meja makan modern.', gallery: ['../images/product-3.png', '../images/couch.png', '../images/product-2.png'] },
        { id: 4, name: 'Kursi Kantor', category: 'kursi', price: 650000, image: '../images/couch.png', description: 'Kursi kantor ergonomis dengan tampilan profesional dan penyangga punggung optimal.', gallery: ['../images/couch.png', '../images/product-1.png', '../images/product-2.png'] },
        { id: 5, name: 'Sofa Tamu', category: 'sofa', price: 2250000, image: '../images/sofa.png', description: 'Sofa tamu mewah dengan bahan premium dan pilihan kayu berkualitas.', gallery: ['../images/sofa.png', '../images/product-1.png', '../images/product-2.png'] },
      ];
      
      const urlParams = new URLSearchParams(window.location.search);
      const productName = urlParams.get('product');
      product = products.find(item => item.name === productName) || products[0];
    }

    const mainImage = document.getElementById('mainProductImage');
    const galleryThumbs = document.getElementById('galleryThumbs');
    const productNameEl = document.getElementById('productName');
    const productPriceEl = document.getElementById('productPrice');
    const productDescriptionEl = document.getElementById('productDescription');
    const productCategoryEl = document.getElementById('productCategory');
    const productStockEl = document.getElementById('productStock');
    const productMaterialEl = document.getElementById('productMaterial');
    const recentProductsEl = document.getElementById('recentProducts');
    const addToCartBtn = document.getElementById('addToCart');
    const woodTypeSelect = document.getElementById('woodType');
    const colorSelect = document.getElementById('colorOption');

    function formatRupiah(value) {
      return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
    }

    function populateSelectOptions(selectElement, optionString, defaultText) {
      if (!selectElement) return;
      const values = parseOptionsFromString(optionString);
      if (values.length === 0) return;
      selectElement.innerHTML = `<option value="">${defaultText}</option>` +
        values.map(item => `<option value="${item}">${item}</option>`).join('');
    }

    function renderGallery(images) {
      galleryThumbs.innerHTML = images.map((src, index) => `
        <div class="thumb-item ${index === 0 ? 'active' : ''}">
          <img src="${src}" alt="Foto produk ${index + 1}" data-src="${src}">
        </div>
      `).join('');
      galleryThumbs.querySelectorAll('img').forEach(img => {
        img.addEventListener('click', function () {
          mainImage.src = this.dataset.src;
          galleryThumbs.querySelectorAll('.thumb-item').forEach(item => item.classList.remove('active'));
          this.closest('.thumb-item').classList.add('active');
        });
      });
    }

    function renderRecentProducts(currentProduct) {
      // Fetch recent products from database for display
      fetch('../database/api_get_products.php?limit=8')
        .then(r => r.json())
        .then(data => {
          const recent = (data.products || []).filter(item => item.id !== currentProduct.id).slice(0, 4);
          if (recent.length === 0) {
            recentProductsEl.innerHTML = '<div class="col-12"><p class="text-muted">Tidak ada rekomendasi lain saat ini.</p></div>';
            return;
          }
          recentProductsEl.innerHTML = recent.map(item => `
            <div class="col-sm-6 col-lg-3">
              <a href="product_detail.php?id=${item.id}" class="text-decoration-none text-body">
                <div class="product-card h-100 p-2 bg-white">
                  <img src="../${item.image}" alt="${item.name}" class="img-fluid">
                  <div class="p-3">
                    <p class="product-title mb-2">${item.name}</p>
                    <p class="product-price mb-0">${formatRupiah(item.price)}</p>
                  </div>
                </div>
              </a>
            </div>
          `).join('');
        })
        .catch(err => console.log('Error loading recent products:', err));
    }

    const userId = <?php echo $userId; ?>;
    const userCartKey = 'cart_' + userId;
    const userNotificationKey = 'notifications_' + userId;

    function updateProductMaterialLabel() {
      const selectedWood = woodTypeSelect ? woodTypeSelect.value : '';
      const selectedColor = colorSelect ? colorSelect.value : '';
      if (selectedWood || selectedColor) {
        productMaterialEl.textContent = (selectedWood ? 'Jenis Kayu: ' + selectedWood : '') + (selectedColor ? ' | Warna: ' + selectedColor : '');
      } else {
        const woodLabel = product.wood_type ? 'Pilihan Kayu: ' + product.wood_type : '';
        const colorLabel = product.color ? 'Pilihan Warna: ' + product.color : '';
        productMaterialEl.textContent = [woodLabel, colorLabel].filter(Boolean).join(' | ');
      }
    }

    function renderDetail() {
      productNameEl.textContent = product.name;
      productPriceEl.textContent = formatRupiah(product.price);
      productDescriptionEl.textContent = product.description;
      productCategoryEl.textContent = product.category;
      productStockEl.textContent = (product.stock && product.stock > 0) ? 'Tersedia' : 'Stok Habis';
      populateSelectOptions(woodTypeSelect, product.wood_type, '-- Pilih Jenis Kayu --');
      populateSelectOptions(colorSelect, product.color, '-- Pilih Warna --');
      updateProductMaterialLabel();
      mainImage.src = product.image;
      renderGallery(product.gallery);
      renderRecentProducts(product);
    }

    if (woodTypeSelect) {
      woodTypeSelect.addEventListener('change', updateProductMaterialLabel);
    }
    if (colorSelect) {
      colorSelect.addEventListener('change', updateProductMaterialLabel);
    }

    addToCartBtn.addEventListener('click', function () {
      const quantity = parseInt(document.getElementById('quantity').value, 10) || 1;
      const selectedWood = document.getElementById('woodType').value;
      const selectedColor = document.getElementById('colorOption').value;

      const cartItem = {
        id: product.id,
        name: product.name,
        price: product.price,
        wood: selectedWood || '',
        color: selectedColor || '',
        quantity: quantity,
        image: mainImage.src
      };

      const existing = JSON.parse(localStorage.getItem(userCartKey) || '[]');
      // If same product + options exist, increase qty instead of duplicate
      const foundIndex = existing.findIndex(item => item.name === cartItem.name && item.wood === cartItem.wood && item.color === cartItem.color && item.price === cartItem.price);
      if (foundIndex >= 0) {
        existing[foundIndex].quantity = (existing[foundIndex].quantity || existing[foundIndex].qty || 0) + (cartItem.quantity || cartItem.qty || 1);
        // keep legacy `qty` for older code that may read it
        existing[foundIndex].qty = existing[foundIndex].quantity;
      } else {
        // ensure `qty` exists for compatibility
        cartItem.qty = cartItem.quantity;
        existing.push(cartItem);
      }
      localStorage.setItem(userCartKey, JSON.stringify(existing));
      if (window.updateCommonBadges) window.updateCommonBadges();

      if (confirm(`Produk ${product.name} ditambahkan ke keranjang.\nBuka keranjang sekarang?`)) {
        window.location = 'cart.php';
      }
    });

    document.addEventListener('DOMContentLoaded', renderDetail);
    
			 // Notification system
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
