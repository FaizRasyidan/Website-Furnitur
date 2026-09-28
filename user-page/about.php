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
		<style>
			.why-choose-section .feature {
				background: white;
				padding: 32px;
				border-radius: 22px;
				border: 1px solid rgba(0,0,0,0.04);
				box-shadow: 0 18px 40px rgba(0,0,0,0.08);
				transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
				height: 100%;
			}
			.why-choose-section .feature:hover {
				transform: translateY(-10px);
				box-shadow: 0 24px 50px rgba(0,0,0,0.14);
				border-color: rgba(242,204,143,0.9);
			}
			.why-choose-section .feature .icon {
				width: 90px;
				height: 90px;
				margin: 0 auto 20px;
				display: flex;
				align-items: center;
				justify-content: center;
				border-radius: 50%;
				background: linear-gradient(135deg, #f2cc8f 0%, #e4b85a 100%);
				color: #3b5d50;
				box-shadow: 0 12px 30px rgba(242,204,143,0.35);
			}
			.why-choose-section .feature h3 {
				font-size: 22px;
				margin-bottom: 15px;
				color: #2c3e50;
			}
			.why-choose-section .feature p {
				color: #666;
				line-height: 1.75;
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
				Offo<span style="color: #f2cc8f;">Living</span>
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

					<li class="nav-item active">
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
		<!-- Start Hero Section -->
			<div class="hero" style="background: linear-gradient(135deg, #3b5d50 0%, #3b5d50 100%);">
				<div class="container">
					<div class="row justify-content-between align-items-center" style="min-height: 500px;">
						<div class="col-lg-5">
							<div class="intro-excerpt">
								<span style="color: #f2cc8f; font-size: 18px; font-weight: 600; text-transform: uppercase; letter-spacing: 2px;">Tentang Kami</span>
								<h1 style="font-size: 56px; font-weight: 700; margin-top: 15px; color: white; line-height: 1.2;">Rumah Impian Dimulai dari Furnitur Berkualitas</h1>
								<p class="mb-4" style="font-size: 16px; color: #ecf0f1; margin-top: 20px; line-height: 1.8;">Offo Living adalah destinasi terpercaya untuk furnitur premium berkualitas tinggi. Kami menghadirkan koleksi furnitur modern yang menggabungkan desain elegan, kenyamanan maksimal, dan harga kompetitif untuk mewujudkan rumah impian Anda.</p>
								<p><a href="shop.php" class="btn btn-secondary me-2" style="background-color: #f2cc8f; border-color: #f2cc8f; color: #3b5d50; font-weight: 600;">Belanja Sekarang</a><a href="#why-choose" class="btn btn-white-outline" style="border-color: white; color: white;">Pelajari Lebih Lanjut</a></p>
							</div>
						</div>
						<div class="col-lg-7">
							<div class="hero-img-wrap" style="text-align: center;">
								<img src="../images/couch.png" class="img-fluid" style="max-width: 100%; height: auto; filter: drop-shadow(0 10px 30px rgba(0,0,0,0.3));">
							</div>
						</div>
					</div>
				</div>
			</div>
		<!-- End Hero Section -->

		

		<!-- Start Why Choose Us Section -->
		<div class="why-choose-section" id="why-choose" style="background-color: #f9f8f6; padding: 80px 0;">
			<div class="container">
				<div class="row justify-content-center mb-5">
					<div class="col-lg-8 text-center">
						<span style="color: #3b5d50; font-size: 16px; font-weight: 600; text-transform: uppercase; letter-spacing: 2px;">Mengapa Memilih Kami</span>
						<h2 class="section-title" style="font-size: 48px; font-weight: 700; margin-top: 15px;">Mengapa Offo Living adalah Pilihan Terbaik?</h2>
						<p style="font-size: 16px; color: #666; margin-top: 20px; line-height: 1.8;">Kami berkomitmen memberikan pengalaman berbelanja furnitur terbaik dengan kualitas premium dan layanan pelanggan yang luar biasa.</p>
					</div>
				</div>

				<div class="row">
					<div class="col-md-6 col-lg-4 mb-4">
						<div class="feature" style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); transition: all 0.3s ease; text-align: center; height: 100%;">
							<div class="icon" style="margin-bottom: 20px; font-size: 48px;">
								<i class="fas fa-star" style="color: #f2cc8f;"></i>
							</div>
							<h3 style="font-size: 20px; font-weight: 600; margin-bottom: 15px;">Kualitas Premium</h3>
							<p style="color: #666; line-height: 1.6;">Semua produk kami dipilih dengan cermat dari produsen terpercaya dan melalui quality control ketat untuk memastikan durabilitas dan estetika terbaik.</p>
						</div>
					</div>

					<div class="col-md-6 col-lg-4 mb-4">
						<div class="feature" style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); transition: all 0.3s ease; text-align: center; height: 100%;">
							<div class="icon" style="margin-bottom: 20px; font-size: 48px;">
								<i class="fas fa-truck" style="color: #f2cc8f;"></i>
							</div>
							<h3 style="font-size: 20px; font-weight: 600; margin-bottom: 15px;">Pengiriman Cepat & Aman</h3>
							<p style="color: #666; line-height: 1.6;">Gratis ongkir untuk pembelian minimum dan pengiriman ke seluruh nusantara dengan asuransi penuh. Paket Anda dijamin sampai dengan aman.</p>
						</div>
					</div>

					<div class="col-md-6 col-lg-4 mb-4">
						<div class="feature" style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); transition: all 0.3s ease; text-align: center; height: 100%;">
							<div class="icon" style="margin-bottom: 20px; font-size: 48px;">
								<i class="fas fa-tag" style="color: #f2cc8f;"></i>
							</div>
							<h3 style="font-size: 20px; font-weight: 600; margin-bottom: 15px;">Harga Kompetitif</h3>
							<p style="color: #666; line-height: 1.6;">Nikmati berbagai promosi, diskon menarik, dan program cashback regular. Kami menawarkan value terbaik untuk setiap rupiah yang Anda investasikan.</p>
						</div>
					</div>

					<div class="col-md-6 col-lg-4 mb-4">
						<div class="feature" style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); transition: all 0.3s ease; text-align: center; height: 100%;">
							<div class="icon" style="margin-bottom: 20px; font-size: 48px;">
								<i class="fas fa-headset" style="color: #f2cc8f;"></i>
							</div>
							<h3 style="font-size: 20px; font-weight: 600; margin-bottom: 15px;">Layanan Pelanggan 24/7</h3>
							<p style="color: #666; line-height: 1.6;">Tim support kami siap membantu menjawab pertanyaan dan menyelesaikan masalah kapan saja. Chat, email, atau telepon - kami selalu ada untuk Anda.</p>
						</div>
					</div>

					<div class="col-md-6 col-lg-4 mb-4">
						<div class="feature" style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); transition: all 0.3s ease; text-align: center; height: 100%;">
							<div class="icon" style="margin-bottom: 20px; font-size: 48px;">
								<i class="fas fa-undo" style="color: #f2cc8f;"></i>
							</div>
							<h3 style="font-size: 20px; font-weight: 600; margin-bottom: 15px;">Kebijakan Pengembalian Mudah</h3>
							<p style="color: #666; line-height: 1.6;">Tidak puas? Kembalikan dalam 30 hari tanpa pertanyaan. Kami ingin Anda 100% puas dengan pembelian furnitur Anda.</p>
						</div>
					</div>

					<div class="col-md-6 col-lg-4 mb-4">
						<div class="feature" style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); transition: all 0.3s ease; text-align: center; height: 100%;">
							<div class="icon" style="margin-bottom: 20px; font-size: 48px;">
								<i class="fas fa-check-circle" style="color: #f2cc8f;"></i>
							</div>
							<h3 style="font-size: 20px; font-weight: 600; margin-bottom: 15px;">Terpercaya & Bersertifikat</h3>
							<p style="color: #666; line-height: 1.6;">Offo Living telah melayani ribuan pelanggan puas. Kami berkomitmen pada transparansi dan praktik bisnis yang etis dan berkelanjutan.</p>
						</div>
					</div>
				</div>
			</div>
		</div>
		<!-- End Why Choose Us Section -->

		<!-- Start Statistics Section -->
		<div class="statistics-section" style="background: linear-gradient(135deg, #3b5d50 0%, #3b5d50 100%); padding: 80px 0; color: white;">
			<div class="container">
				<div class="row text-center">
					<div class="col-md-3 col-6 mb-4">
						<div>
							<h3 style="font-size: 48px; font-weight: 700; color: #f2cc8f;">5000+</h3>
							<p style="font-size: 18px; margin-top: 10px;">Pelanggan Puas</p>
						</div>
					</div>
					<div class="col-md-3 col-6 mb-4">
						<div>
							<h3 style="font-size: 48px; font-weight: 700; color: #f2cc8f;">2000+</h3>
							<p style="font-size: 18px; margin-top: 10px;">Produk Pilihan</p>
						</div>
					</div>
					<div class="col-md-3 col-6 mb-4">
						<div>
							<h3 style="font-size: 48px; font-weight: 700; color: #f2cc8f;">50+</h3>
							<p style="font-size: 18px; margin-top: 10px;">Kota Jangkauan</p>
						</div>
					</div>
					<div class="col-md-3 col-6 mb-4">
						<div>
							<h3 style="font-size: 48px; font-weight: 700; color: #f2cc8f;">10+</h3>
							<p style="font-size: 18px; margin-top: 10px;">Tahun Pengalaman</p>
						</div>
					</div>
				</div>
			</div>
		</div>
		<!-- End Statistics Section -->

		<!-- Start Mission & Values Section -->
		<div class="mission-section" style="background-color: #ffffff; padding: 80px 0;">
			<div class="container">
				<div class="row mb-5">
					<div class="col-lg-8 mx-auto text-center">
						<span style="color: #f2cc8f; font-size: 16px; font-weight: 600; text-transform: uppercase; letter-spacing: 2px;">Visi Kami</span>
						<h2 class="section-title" style="font-size: 48px; font-weight: 700; margin-top: 15px;">Menjadi Pilihan Utama untuk Furnitur Berkualitas di Indonesia</h2>
					</div>
				</div>

				<div class="row">
					<div class="col-md-6 mb-4">
						<div style="background: #f9f8f6; padding: 40px; border-radius: 12px; border-left: 5px solid #f2cc8f;">
							<h3 style="font-size: 24px; font-weight: 700; margin-bottom: 20px; color: #3b5d50;">
								<i class="fas fa-bullseye" style="color: #f2cc8f; margin-right: 15px;"></i>Misi Kami
							</h3>
							<p style="color: #666; font-size: 16px; line-height: 1.8;">Menyediakan furnitur berkualitas tinggi dengan desain modern yang terjangkau bagi seluruh lapisan masyarakat, serta memberikan pengalaman berbelanja yang menyenangkan dan memuaskan.</p>
						</div>
					</div>

					<div class="col-md-6 mb-4">
						<div style="background: #f9f8f6; padding: 40px; border-radius: 12px; border-left: 5px solid #f2cc8f;">
							<h3 style="font-size: 24px; font-weight: 700; margin-bottom: 20px; color: #3b5d50;">
								<i class="fas fa-heart" style="color: #f2cc8f; margin-right: 15px;"></i>Nilai Inti Kami
							</h3>
							<ul style="color: #666; font-size: 16px; line-height: 2; list-style: none; padding: 0;">
								<li><i class="fas fa-check" style="color: #f2cc8f; margin-right: 10px;"></i>Kualitas tanpa kompromi</li>
								<li><i class="fas fa-check" style="color: #f2cc8f; margin-right: 10px;"></i>Kejujuran dan transparansi</li>
								<li><i class="fas fa-check" style="color: #f2cc8f; margin-right: 10px;"></i>Kepuasan pelanggan adalah prioritas</li>
								<li><i class="fas fa-check" style="color: #f2cc8f; margin-right: 10px;"></i>Inovasi berkelanjutan</li>
							</ul>
						</div>
					</div>
				</div>
			</div>
		</div>
		<!-- End Mission & Values Section -->

		<!-- Start Testimonial Slider -->
		<div class="testimonial-section before-footer-section" style="padding: 80px 0; background-color: #ffffff;">
			<div class="container">
				<div class="row">
					<div class="col-lg-8 mx-auto text-center">
						<span style="color: #f2cc8f; font-size: 16px; font-weight: 600; text-transform: uppercase; letter-spacing: 2px;">Testimoni Pelanggan</span>
						<h2 class="section-title" style="font-size: 48px; font-weight: 700; margin-top: 15px;">Kepuasan Pelanggan adalah Kesuksesan Kami</h2>
						<p style="font-size: 16px; color: #666; margin-top: 20px; line-height: 1.8;">Ribuan pelanggan telah merasakan kualitas dan layanan terbaik dari Offo Living. Inilah cerita mereka.</p>
					</div>
				</div>

				<div class="row justify-content-center" style="margin-top: 50px;">
					<div class="col-lg-12">
						<div class="testimonial-slider-wrap text-center">

							<div id="testimonial-nav">
								<span class="prev" data-controls="prev"><span class="fa fa-chevron-left"></span></span>
								<span class="next" data-controls="next"><span class="fa fa-chevron-right"></span></span>
							</div>

							<div class="testimonial-slider">
								
								<div class="item">
									<div class="row justify-content-center">
										<div class="col-lg-8 mx-auto">

											<div class="testimonial-block text-center">
												<div style="margin-bottom: 15px;">
													<i class="fas fa-star" style="color: #f2cc8f; font-size: 20px; margin: 0 3px;"></i>
													<i class="fas fa-star" style="color: #f2cc8f; font-size: 20px; margin: 0 3px;"></i>
													<i class="fas fa-star" style="color: #f2cc8f; font-size: 20px; margin: 0 3px;"></i>
													<i class="fas fa-star" style="color: #f2cc8f; font-size: 20px; margin: 0 3px;"></i>
													<i class="fas fa-star" style="color: #f2cc8f; font-size: 20px; margin: 0 3px;"></i>
												</div>
												<blockquote class="mb-5" style="font-size: 18px; font-style: italic; line-height: 1.8; color: #666;">
													<p>&ldquo;Saya sangat terkesan dengan kualitas kursi yang saya beli dari Offo Living. Desainnya modern, nyaman, dan daya tahan luar biasa. Pengiriman juga sangat cepat dan aman. Sudah merekomendasikan ke keluarga dan teman-teman saya!&rdquo;</p>
												</blockquote>

												<div class="author-info">
													<div class="author-pic" style="margin: 0 auto 15px; width: 60px; height: 60px; border-radius: 50%; overflow: hidden;">
														<img src="images/person-1.png" alt="Ibu Siti" class="img-fluid" style="width: 100%; height: 100%; object-fit: cover;">
													</div>
													<h3 class="font-weight-bold" style="color: #3b5d50; font-weight: 700;">Ibu Siti Nurhaliza</h3>
													<span class="position d-block mb-3" style="color: #f2cc8f; font-weight: 600;">Jakarta, Indonesia</span>
												</div>
											</div>

										</div>
									</div>
								</div> 
								<!-- END item -->

								<div class="item">
									<div class="row justify-content-center">
										<div class="col-lg-8 mx-auto">

											<div class="testimonial-block text-center">
												<div style="margin-bottom: 15px;">
													<i class="fas fa-star" style="color: #f2cc8f; font-size: 20px; margin: 0 3px;"></i>
													<i class="fas fa-star" style="color: #f2cc8f; font-size: 20px; margin: 0 3px;"></i>
													<i class="fas fa-star" style="color: #f2cc8f; font-size: 20px; margin: 0 3px;"></i>
													<i class="fas fa-star" style="color: #f2cc8f; font-size: 20px; margin: 0 3px;"></i>
													<i class="fas fa-star" style="color: #f2cc8f; font-size: 20px; margin: 0 3px;"></i>
												</div>
												<blockquote class="mb-5" style="font-size: 18px; font-style: italic; line-height: 1.8; color: #666;">
													<p>&ldquo;Offo Living menawarkan harga yang sangat kompetitif dibanding kompetitor lainnya. Belum lagi pelayanan customer service mereka yang responsif dan membantu. Tim mereka selalu siap menjawab pertanyaan saya tentang spesifikasi produk. Highly recommended!&rdquo;</p>
												</blockquote>

												<div class="author-info">
													<div class="author-pic" style="margin: 0 auto 15px; width: 60px; height: 60px; border-radius: 50%; overflow: hidden;">
														<img src="images/person-1.png" alt="Pak Hendra" class="img-fluid" style="width: 100%; height: 100%; object-fit: cover;">
													</div>
													<h3 class="font-weight-bold" style="color: #3b5d50; font-weight: 700;">Pak Hendra Wijaya</h3>
													<span class="position d-block mb-3" style="color: #f2cc8f; font-weight: 600;">Surabaya, Indonesia</span>
												</div>
											</div>

										</div>
									</div>
								</div> 
								<!-- END item -->

								<div class="item">
									<div class="row justify-content-center">
										<div class="col-lg-8 mx-auto">

											<div class="testimonial-block text-center">
												<div style="margin-bottom: 15px;">
													<i class="fas fa-star" style="color: #f2cc8f; font-size: 20px; margin: 0 3px;"></i>
													<i class="fas fa-star" style="color: #f2cc8f; font-size: 20px; margin: 0 3px;"></i>
													<i class="fas fa-star" style="color: #f2cc8f; font-size: 20px; margin: 0 3px;"></i>
													<i class="fas fa-star" style="color: #f2cc8f; font-size: 20px; margin: 0 3px;"></i>
													<i class="fas fa-star" style="color: #f2cc8f; font-size: 20px; margin: 0 3px;"></i>
												</div>
												<blockquote class="mb-5" style="font-size: 18px; font-style: italic; line-height: 1.8; color: #666;">
													<p>&ldquo;Renovasi rumah saya menjadi sempurna berkat koleksi furnitur dari Offo Living. Desain mereka sangat trendy dan cocok dengan konsep modern minimalis yang saya inginkan. Proses pembelian hingga instalasi berjalan dengan lancar. Terima kasih Offo Living!&rdquo;</p>
												</blockquote>

												<div class="author-info">
													<div class="author-pic" style="margin: 0 auto 15px; width: 60px; height: 60px; border-radius: 50%; overflow: hidden;">
														<img src="images/person-1.png" alt="Bu Eka" class="img-fluid" style="width: 100%; height: 100%; object-fit: cover;">
													</div>
													<h3 class="font-weight-bold" style="color: #3b5d50; font-weight: 700;">Bu Eka Maharani</h3>
													<span class="position d-block mb-3" style="color: #f2cc8f; font-weight: 600;">Bandung, Indonesia</span>
												</div>
											</div>

										</div>
									</div>
								</div> 
								<!-- END item -->

							</div>

						</div>
					</div>
				</div>
			</div>
		</div>
		<!-- End Testimonial Slider -->

		<!-- Start CTA Section -->
		<div style="background: linear-gradient(135deg, #3b5d50 0%, #3b5d50 100%); padding: 80px 0; color: white; text-align: center;">
			<div class="container">
				<div class="row">
					<div class="col-lg-8 mx-auto">
						<h2 style="font-size: 48px; font-weight: 700; margin-bottom: 20px;">Siap Memulai Transformasi Rumah Anda?</h2>
						<p style="font-size: 18px; line-height: 1.8; margin-bottom: 30px; opacity: 0.95;">Bergabunglah dengan ribuan pelanggan yang telah merasakan keindahan dan kenyamanan furnitur berkualitas dari Offo Living. Dapatkan penawaran spesial hari ini!</p>
						<a href="shop.php" class="btn" style="background-color: #f2cc8f; border-color: #f2cc8f; color: #3b5d50; font-weight: 600; padding: 15px 40px; font-size: 16px; border-radius: 6px;">Jelajahi Koleksi Kami Sekarang</a>
					</div>
				</div>
			</div>
		</div>
		<!-- End CTA Section -->

		<!-- Start Footer Section -->
		<?php include 'footer.php'; ?>
		<!-- End Footer Section -->	


		<script src="../js/bootstrap.bundle.min.js"></script>
		<script src="../js/tiny-slider.js"></script>
		<script src="../js/custom.js"></script>
		<script>
		const userNotificationKey = 'notifications_' + <?php echo $userId; ?>;
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
