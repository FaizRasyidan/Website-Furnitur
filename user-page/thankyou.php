
<?php session_start(); $userId = $_SESSION['user_id'] ?? null; ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="author" content="Furni">
  <meta name="current-user-id" content="<?php echo htmlspecialchars($userId); ?>">
  <link rel="shortcut icon" href="logo.png">

  <meta name="description" content="" />
  <meta name="keywords" content="bootstrap, bootstrap4" />

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
								<h1>Cart</h1>
							</div>
						</div>
						<div class="col-lg-7">
							
						</div>
					</div>
				</div>
			</div>
		<!-- End Hero Section -->

		

		<div class="furni-section">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-md-10 text-center pt-5">
          <span class="display-3 thankyou-icon text-primary">
            <svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-cart-check mb-5" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd" d="M11.354 5.646a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L8 8.293l2.646-2.647a.5.5 0 0 1 .708 0z"/>
              <path fill-rule="evenodd" d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5zM3.102 4l1.313 7h8.17l1.313-7H3.102zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm-7 1a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm7 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
            </svg>
          </span>
          <h2 class="display-3 text-black">Terima Kasih!</h2>
          <p class="lead mb-4">Pesanan Anda berhasil dibuat. Berikut rincian ringkasnya.</p>
          <div id="thankyouDetails" class="text-start mx-auto" style="max-width:720px;"></div>
          <div class="mt-4 d-flex justify-content-center gap-2 flex-wrap">
            <a href="pesanan.php" class="btn btn-primary">Lihat Pesanan Saya</a>
            <a href="shop.php" class="btn btn-outline-black">Lanjut Belanja</a>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script>
    function formatRupiah(value) {
      return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
    }
    function getQueryParam(key) {
      return new URLSearchParams(window.location.search).get(key);
    }
    function getStatusText(order) {
      if (!order) return 'Unknown';
      if (order.paymentStatus === 'cod') return 'Bayar di Tempat (COD)';
      if (order.paymentStatus === 'paid' || order.paymentStatus === 'success') return 'Sudah Dibayar';
      if (order.paymentStatus === 'pending' || order.paymentStatus === 'notdone') return 'Menunggu Pembayaran';
      return order.paymentStatus;
    }
    function getPaymentMethodText(order) {
      if (!order) return '-';
      if (order.paymentType === 'cod') return 'COD';
      return order.paymentMethod || '-';
    }
    function renderOrderDetails(order) {
      if (!order) {
        return '<div class="alert alert-secondary">Detail pesanan tidak ditemukan. Silakan buka <a href="pesanan.php">Pesanan Saya</a> untuk melihat semua pesanan.</div>';
      }
      const items = Array.isArray(order.items) ? order.items : [];
      const itemHtml = items.length > 0 ? items.map(item => {
        const qty = item.quantity || item.qty || 1;
        const amount = Number(item.price || 0) * qty;
        return `\n          <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #e9ecef;">\n            <div><strong>${item.name || 'Produk'}</strong><br><small>${qty} x ${formatRupiah(Number(item.price || 0))}</small></div>\n            <div>${formatRupiah(amount)}</div>\n          </div>`;
      }).join('') : '<div class="text-muted">Tidak ada item yang tersimpan.</div>';
      const address = order.address || {};
      const addressText = [address.jalan || address.address || '', address.kota || address.city || '', address.provinsi || address.province || '', address.kodepos || address.postal_code || '']
        .filter(Boolean).join(', ');
      const subtotal = Number(order.total || 0) - Number(order.shipping || 0) + Number(order.discount || 0);
      return `\n        <div style="background:#ffffff; border:1px solid #e9ecef; border-radius:18px; padding:24px; box-shadow:0 12px 30px rgba(0,0,0,0.05);">\n          <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:20px;">\n            <div><strong>Nomor Pesanan:</strong> ${order.orderId}</div>\n            <div><strong>Status:</strong> ${getStatusText(order)}</div>\n          </div>\n          <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:18px; margin-bottom:20px;">\n            <div><strong>Metode Pembayaran</strong><br>${getPaymentMethodText(order)}</div>\n            <div><strong>Subtotal</strong><br>${formatRupiah(subtotal)}</div>\n            <div><strong>Biaya Pengiriman</strong><br>${formatRupiah(order.shipping || 0)}</div>\n            <div><strong>Diskon</strong><br>${formatRupiah(order.discount || 0)}</div>\n          </div>\n          <div style="margin-bottom:20px;"><strong>Total Bayar</strong><div style="font-size:1.5rem; font-weight:700; color:#0d6efd;">${formatRupiah(order.total || 0)}</div></div>\n          <div style="margin-bottom:20px;"><strong>Alamat Pengiriman</strong><div>${addressText || '-'}</div></div>\n          <div style="margin-bottom:16px;"><strong>Detail Item</strong>${itemHtml}</div>\n          <div><strong>Catatan Pembeli</strong><div>${order.message ? order.message : '-'}</div></div>\n        </div>`;
    }
    document.addEventListener('DOMContentLoaded', function() {
      const orderId = getQueryParam('order_id');
      const userId = <?php echo json_encode($userId ?: 'guest'); ?>;
      const orderStorageKey = userId && userId !== 'guest' ? 'orders_' + userId : 'orders';
      const orders = JSON.parse(localStorage.getItem(orderStorageKey) || '[]');
      const order = orderId ? orders.find(o => o.orderId === orderId) : orders[0];
      document.getElementById('thankyouDetails').innerHTML = renderOrderDetails(order);
    });
  </script>

		<!-- Start Footer Section -->
		<footer class="footer-section">
			<div class="container relative">

				<div class="sofa-img">
					<img src="images/sofa.png" alt="Image" class="img-fluid">
				</div>

				<div class="row">
					<div class="col-lg-8">
						<div class="subscription-form">
							<h3 class="d-flex align-items-center"><span class="me-1"><img src="images/envelope-outline.svg" alt="Image" class="img-fluid"></span><span>Subscribe to Newsletter</span></h3>

							<form action="#" class="row g-3">
								<div class="col-auto">
									<input type="text" class="form-control" placeholder="Enter your name">
								</div>
								<div class="col-auto">
									<input type="email" class="form-control" placeholder="Enter your email">
								</div>
								<div class="col-auto">
									<button class="btn btn-primary">
										<span class="fa fa-paper-plane"></span>
									</button>
								</div>
							</form>

						</div>
					</div>
				</div>

				<div class="row g-5 mb-5">
					<div class="col-lg-4">
						<div class="mb-4 footer-logo-wrap"><a href="#" class="footer-logo">Furni<span>.</span></a></div>
						<p class="mb-4">Donec facilisis quam ut purus rutrum lobortis. Donec vitae odio quis nisl dapibus malesuada. Nullam ac aliquet velit. Aliquam vulputate velit imperdiet dolor tempor tristique. Pellentesque habitant</p>

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
								<ul class="list-unstyled">
									<li><a href="#">About us</a></li>
									<li><a href="#">Services</a></li>
									<li><a href="#">Blog</a></li>
									<li><a href="#">Contact us</a></li>
								</ul>
							</div>

							<div class="col-6 col-sm-6 col-md-3">
								<ul class="list-unstyled">
									<li><a href="#">Support</a></li>
									<li><a href="#">Knowledge base</a></li>
									<li><a href="#">Live chat</a></li>
								</ul>
							</div>

							<div class="col-6 col-sm-6 col-md-3">
								<ul class="list-unstyled">
									<li><a href="#">Jobs</a></li>
									<li><a href="#">Our team</a></li>
									<li><a href="#">Leadership</a></li>
									<li><a href="#">Privacy Policy</a></li>
								</ul>
							</div>

							<div class="col-6 col-sm-6 col-md-3">
								<ul class="list-unstyled">
									<li><a href="#">Nordic Chair</a></li>
									<li><a href="#">Kruzo Aero</a></li>
									<li><a href="#">Ergonomic Chair</a></li>
								</ul>
							</div>
						</div>
					</div>

				</div>

				<div class="border-top copyright">
					<div class="row pt-4">
						<div class="col-lg-6">
							<p class="mb-2 text-center text-lg-start">Copyright &copy;<script>document.write(new Date().getFullYear());</script>. All Rights Reserved. &mdash; Designed with love by <a href="https://Furni">Furni</a> Distributed By <a href="https://themewagon.com">ThemeWagon</a> <!-- License information: https://Furni/license/ -->
            </p>
						</div>

						<div class="col-lg-6 text-center text-lg-end">
							<ul class="list-unstyled d-inline-flex ms-auto">
								<li class="me-4"><a href="#">Terms &amp; Conditions</a></li>
								<li><a href="#">Privacy Policy</a></li>
							</ul>
						</div>

					</div>
				</div>

			</div>
		</footer>
		<!-- End Footer Section -->	


		<script src="../js/bootstrap.bundle.min.js"></script>
		<script src="../js/tiny-slider.js"></script>
		<script src="../js/custom.js"></script>
	</body>

</html>
