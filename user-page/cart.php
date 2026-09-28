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

		<!-- Bootstrap CSS -->
		<link href="../css/bootstrap.min.css" rel="stylesheet">
		<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
		<link href="../css/tiny-slider.css" rel="stylesheet">
		<link href="../css/style.css" rel="stylesheet">
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

						<li class="nav-item active">
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

		<!-- Start Hero Section -->
			<div class="hero">
				<div class="container">
					<div class="row justify-content-between">
						<div class="col-lg-5">
							<div class="intro-excerpt">
								<h1>Cart</h1>
							</div>
						</div>
						<div class="col-lg-7">
							
						</div>
					</div>
				</div>
			</div>
		<!-- End Hero Section -->

		

		<div class="furni-section before-footer-section">
            <div class="container">
              <div class="row mb-5">
                <form class="col-md-12" method="post">
                  <div class="site-blocks-table">
                    <table class="table">
                      <thead>
                        <tr>
                          <th class="product-thumbnail">Image</th>
                          <th class="product-name">Product</th>
                          <th class="product-price">Price</th>
                          <th class="product-quantity">Quantity</th>
                          <th class="product-total">Total</th>
                          <th class="product-remove">Remove</th>
                        </tr>
                      </thead>
                      <tbody id="cartBody">
                      </tbody>
                    </table>
                  </div>
                </form>
              </div>
        
              <div class="row">
                <div class="col-md-6">
                  <div class="row mb-5">
                    <div class="col-md-6 mb-3 mb-md-0">
                      <button class="btn btn-black btn-sm btn-block">Update Cart</button>
                    </div>
                    <div class="col-md-6">
                      <button class="btn btn-outline-black btn-sm btn-block">Continue Shopping</button>
                    </div>
                  </div>
                </div>
                <div class="col-md-6 pl-5">
                  <div class="row justify-content-end">
                    <div class="col-md-7">
                      <div class="row">
                        <div class="col-md-12 text-right border-bottom mb-5">
                          <h3 class="text-black h4 text-uppercase">Cart Totals</h3>
                        </div>
                      </div>
											<div class="row mb-3">
												<div class="col-md-6">
													<span class="text-black">Subtotal</span>
												</div>
												<div class="col-md-6 text-right">
													<strong id="cartSubtotal" class="text-black">Rp0</strong>
												</div>
											</div>
											<div class="row mb-3">
												<div class="col-md-6">
													<span class="text-black">Jumlah Barang</span>
												</div>
												<div class="col-md-6 text-right">
													<strong id="cartItemCount" class="text-black">0</strong>
												</div>
											</div>
											<div class="row mb-3">
												<div class="col-md-6">
													<span class="text-black">Ongkos Kirim</span>
												</div>
												<div class="col-md-6 text-right">
													<strong id="cartShipping" class="text-black">Rp0</strong>
												</div>
											</div>
											<div class="row mb-5">
												<div class="col-md-6">
													<span class="text-black">Total</span>
												</div>
												<div class="col-md-6 text-right">
													<strong id="cartTotal" class="text-black">Rp0</strong>
												</div>
											</div>
        
                      <div class="row">
                        <div class="col-md-12">
                          <button class="btn btn-black btn-lg py-3 btn-block" onclick="window.location='checkout.php'">Proceed To Checkout</button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
		

		<!-- Start Footer Section -->
		<?php include 'footer.php'; ?>
		<!-- End Footer Section -->	


		<script src="../js/bootstrap.bundle.min.js"></script>
		<script src="../js/tiny-slider.js"></script>
		<script src="../js/custom.js"></script>
		<script>
		  const userId = <?php echo $userId; ?>;
		  const userCartKey = 'cart_' + userId;
		  const userNotificationKey = 'notifications_' + userId;
		  const appliedVoucherKey = 'appliedVoucher_' + userId;
		  function formatRupiah(value) {
		    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
		  }

		function renderCart() {
			const cart = JSON.parse(localStorage.getItem(userCartKey) || '[]');
			const cartBody = document.getElementById('cartBody');
			cartBody.innerHTML = '';
			if (!cart.length) {
				cartBody.innerHTML = '<tr><td colspan="6" style="color:#777; padding:14px 10px;">Keranjang kosong.</td></tr>';
				document.querySelector('.text-black.h4').textContent = 'Cart Totals';
				document.querySelector('.col-md-6.text-right strong')?.remove();
				return;
			}

			// normalize and compute subtotal
			let subtotal = 0;
			cart.forEach((item, idx) => {
// Standardize cart quantity field (quantity is the single source of truth)
				if (typeof item.quantity === 'undefined') {
				  if (typeof item.qty !== 'undefined') item.quantity = item.qty;
				}
				item.quantity = Math.max(1, parseInt(item.quantity || 1, 10));
				delete item.qty;

				const total = item.price * item.quantity;
				subtotal += total;

				const row = document.createElement('tr');
				row.innerHTML = `
					<td class="product-thumbnail"><img src="${item.image}" alt="${item.name}" class="img-fluid" style="max-width:90px;"></td>
					<td class="product-name"><h2 class="h5 text-black">${item.name}<br><small>Kayu: ${item.wood} • Warna: ${item.color}</small></h2></td>
					<td>${formatRupiah(item.price)}</td>
					<td>
						<div class="input-group mb-3 d-flex align-items-center quantity-container" style="max-width: 140px;">
							<button class="btn btn-outline-black decrease" data-idx="${idx}" type="button">−</button>
							<input type="text" class="form-control text-center quantity-amount" value="${item.quantity}" data-idx="${idx}" style="width:60px;">
							<button class="btn btn-outline-black increase" data-idx="${idx}" type="button">+</button>
						</div>
					</td>
					<td class="product-total">${formatRupiah(total)}</td>
					<td><button class="btn btn-black btn-sm remove-item" data-idx="${idx}">X</button></td>
				`;
				cartBody.appendChild(row);
			});

			// persist normalized cart
			localStorage.setItem(userCartKey, JSON.stringify(cart));
			if (window.updateCommonBadges) window.updateCommonBadges();

			// vouchers and shipping
			const vouchers = {
				'DISKON10': { discount: 0.10 },
				'DISKON20': { discount: 0.20 },
				'GRATIS': { discount: 1.0 }
			};
			const shippingCost = 50000;
			let appliedVoucher = localStorage.getItem(appliedVoucherKey) || null;
			let discount = 0;
			let freeShipping = false;
			if (appliedVoucher && vouchers[appliedVoucher]) {
				const info = vouchers[appliedVoucher];
				if (info.discount === 1.0) {
					freeShipping = true;
					discount = 0;
				} else {
					discount = Math.floor(subtotal * info.discount);
				}
			}
			const finalShipping = freeShipping ? 0 : shippingCost;
			const totalPayment = subtotal + finalShipping - discount;

			// update UI
			const subtotalEl = document.getElementById('cartSubtotal');
			const totalEl = document.getElementById('cartTotal');
			const discountEl = document.getElementById('cartDiscount');
			const shippingEl = document.getElementById('cartShipping');
			const itemCountEl = document.getElementById('cartItemCount');
const itemCount = cart.reduce((s, it) => s + (it.quantity || 0), 0);
			if (subtotalEl) subtotalEl.textContent = formatRupiah(subtotal);
			if (discountEl) discountEl.textContent = discount > 0 ? `-${formatRupiah(discount)}` : formatRupiah(0);
			if (shippingEl) shippingEl.textContent = formatRupiah(finalShipping);
			if (totalEl) totalEl.textContent = formatRupiah(totalPayment);
			if (itemCountEl) itemCountEl.textContent = itemCount;

			// Attach handlers
			document.querySelectorAll('.decrease').forEach(btn => {
				btn.addEventListener('click', function () {
					const i = parseInt(this.dataset.idx, 10);
					const cart = JSON.parse(localStorage.getItem(userCartKey) || '[]');
					if (cart[i].quantity > 1) cart[i].quantity--;
					cart[i].qty = cart[i].quantity;
					localStorage.setItem(userCartKey, JSON.stringify(cart));
					renderCart();
					if (window.updateCommonBadges) window.updateCommonBadges();
				});
			});

			document.querySelectorAll('.increase').forEach(btn => {
				btn.addEventListener('click', function () {
					const i = parseInt(this.dataset.idx, 10);
					const cart = JSON.parse(localStorage.getItem(userCartKey) || '[]');
					cart[i].quantity = (cart[i].quantity || cart[i].qty || 0) + 1;
					cart[i].qty = cart[i].quantity;
					localStorage.setItem(userCartKey, JSON.stringify(cart));
					renderCart();
					if (window.updateCommonBadges) window.updateCommonBadges();
				});
			});

			document.querySelectorAll('.quantity-amount').forEach(input => {
				input.addEventListener('change', function () {
					const i = parseInt(this.dataset.idx, 10);
					const cart = JSON.parse(localStorage.getItem(userCartKey) || '[]');
					let v = parseInt(this.value, 10) || 1;
					if (v < 1) v = 1;
					cart[i].quantity = v;
					cart[i].qty = v;
					localStorage.setItem(userCartKey, JSON.stringify(cart));
					renderCart();
					if (window.updateCommonBadges) window.updateCommonBadges();
				});
			});

			document.querySelectorAll('.remove-item').forEach(btn => {
				btn.addEventListener('click', function () {
					const i = parseInt(this.dataset.idx, 10);
					const cart = JSON.parse(localStorage.getItem(userCartKey) || '[]');
					cart.splice(i, 1);
					localStorage.setItem(userCartKey, JSON.stringify(cart));
					renderCart();
					if (window.updateCommonBadges) window.updateCommonBadges();
				});
			});
		}

		  document.addEventListener('DOMContentLoaded', function () {
		    renderCart();
		  });

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
