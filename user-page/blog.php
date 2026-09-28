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
                    <a class="nav-link" href="services.php">Services</a>
                </li>

            </ul>
			<!-- Search Bar -->
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
						<a class="nav-link nav-icon-btn position-relative" href="#">

							<span class="fa fa-comment"></span>

							<span class="notif-badge">2</span>

						</a>
					</li>

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
								<img src="images/user.svg" alt="User">
							</div>
							<div class ="dropdown">
							<a href="#">My Account</a>
							<a href="../database/logout.php">Log Out</a>
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
								<h1>Blog</h1>
								<p class="mb-4">Donec vitae odio quis nisl dapibus malesuada. Nullam ac aliquet velit. Aliquam vulputate velit imperdiet dolor tempor tristique.</p>
								<p><a href="" class="btn btn-secondary me-2">Shop Now</a><a href="#" class="btn btn-white-outline">Explore</a></p>
							</div>
						</div>
						<div class="col-lg-7">
							<div class="hero-img-wrap">
								<img src="images/couch.png" class="img-fluid">
							</div>
						</div>
					</div>
				</div>
			</div>
		<!-- End Hero Section -->

		

		<!-- Start Blog Section -->
		<div class="blog-section">
			<div class="container">
				
				<div class="row">

					<div class="col-12 col-sm-6 col-md-4 mb-5">
						<div class="post-entry">
							<a href="#" class="post-thumbnail"><img src="images/post-1.jpg" alt="Image" class="img-fluid"></a>
							<div class="post-content-entry">
								<h3><a href="#">First Time Home Owner Ideas</a></h3>
								<div class="meta">
									<span>by <a href="#">Kristin Watson</a></span> <span>on <a href="#">Dec 19, 2021</a></span>
								</div>
							</div>
						</div>
					</div>

					<div class="col-12 col-sm-6 col-md-4 mb-5">
						<div class="post-entry">
							<a href="#" class="post-thumbnail"><img src="images/post-2.jpg" alt="Image" class="img-fluid"></a>
							<div class="post-content-entry">
								<h3><a href="#">How To Keep Your Furniture Clean</a></h3>
								<div class="meta">
									<span>by <a href="#">Robert Fox</a></span> <span>on <a href="#">Dec 15, 2021</a></span>
								</div>
							</div>
						</div>
					</div>

					<div class="col-12 col-sm-6 col-md-4 mb-5">
						<div class="post-entry">
							<a href="#" class="post-thumbnail"><img src="images/post-3.jpg" alt="Image" class="img-fluid"></a>
							<div class="post-content-entry">
								<h3><a href="#">Small Space Furniture Apartment Ideas</a></h3>
								<div class="meta">
									<span>by <a href="#">Kristin Watson</a></span> <span>on <a href="#">Dec 12, 2021</a></span>
								</div>
							</div>
						</div>
					</div>

					<div class="col-12 col-sm-6 col-md-4 mb-5">
						<div class="post-entry">
							<a href="#" class="post-thumbnail"><img src="images/post-1.jpg" alt="Image" class="img-fluid"></a>
							<div class="post-content-entry">
								<h3><a href="#">First Time Home Owner Ideas</a></h3>
								<div class="meta">
									<span>by <a href="#">Kristin Watson</a></span> <span>on <a href="#">Dec 19, 2021</a></span>
								</div>
							</div>
						</div>
					</div>

					<div class="col-12 col-sm-6 col-md-4 mb-5">
						<div class="post-entry">
							<a href="#" class="post-thumbnail"><img src="images/post-2.jpg" alt="Image" class="img-fluid"></a>
							<div class="post-content-entry">
								<h3><a href="#">How To Keep Your Furniture Clean</a></h3>
								<div class="meta">
									<span>by <a href="#">Robert Fox</a></span> <span>on <a href="#">Dec 15, 2021</a></span>
								</div>
							</div>
						</div>
					</div>

					<div class="col-12 col-sm-6 col-md-4 mb-5">
						<div class="post-entry">
							<a href="#" class="post-thumbnail"><img src="images/post-3.jpg" alt="Image" class="img-fluid"></a>
							<div class="post-content-entry">
								<h3><a href="#">Small Space Furniture Apartment Ideas</a></h3>
								<div class="meta">
									<span>by <a href="#">Kristin Watson</a></span> <span>on <a href="#">Dec 12, 2021</a></span>
								</div>
							</div>
						</div>
					</div>

					<div class="col-12 col-sm-6 col-md-4 mb-5">
						<div class="post-entry">
							<a href="#" class="post-thumbnail"><img src="images/post-1.jpg" alt="Image" class="img-fluid"></a>
							<div class="post-content-entry">
								<h3><a href="#">First Time Home Owner Ideas</a></h3>
								<div class="meta">
									<span>by <a href="#">Kristin Watson</a></span> <span>on <a href="#">Dec 19, 2021</a></span>
								</div>
							</div>
						</div>
					</div>

					<div class="col-12 col-sm-6 col-md-4 mb-5">
						<div class="post-entry">
							<a href="#" class="post-thumbnail"><img src="images/post-2.jpg" alt="Image" class="img-fluid"></a>
							<div class="post-content-entry">
								<h3><a href="#">How To Keep Your Furniture Clean</a></h3>
								<div class="meta">
									<span>by <a href="#">Robert Fox</a></span> <span>on <a href="#">Dec 15, 2021</a></span>
								</div>
							</div>
						</div>
					</div>

					<div class="col-12 col-sm-6 col-md-4 mb-5">
						<div class="post-entry">
							<a href="#" class="post-thumbnail"><img src="images/post-3.jpg" alt="Image" class="img-fluid"></a>
							<div class="post-content-entry">
								<h3><a href="#">Small Space Furniture Apartment Ideas</a></h3>
								<div class="meta">
									<span>by <a href="#">Kristin Watson</a></span> <span>on <a href="#">Dec 12, 2021</a></span>
								</div>
							</div>
						</div>
					</div>

				</div>
			</div>
		</div>
		<!-- End Blog Section -->	

		

		<!-- Start Testimonial Slider -->
		<div class="testimonial-section before-footer-section">
			<div class="container">
				<div class="row">
					<div class="col-lg-7 mx-auto text-center">
						<h2 class="section-title">Testimonials</h2>
					</div>
				</div>

				<div class="row justify-content-center">
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
												<blockquote class="mb-5">
													<p>&ldquo;Donec facilisis quam ut purus rutrum lobortis. Donec vitae odio quis nisl dapibus malesuada. Nullam ac aliquet velit. Aliquam vulputate velit imperdiet dolor tempor tristique. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Integer convallis volutpat dui quis scelerisque.&rdquo;</p>
												</blockquote>

												<div class="author-info">
													<div class="author-pic">
														<img src="images/person-1.png" alt="Maria Jones" class="img-fluid">
													</div>
													<h3 class="font-weight-bold">Maria Jones</h3>
													<span class="position d-block mb-3">CEO, Co-Founder, XYZ Inc.</span>
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
												<blockquote class="mb-5">
													<p>&ldquo;Donec facilisis quam ut purus rutrum lobortis. Donec vitae odio quis nisl dapibus malesuada. Nullam ac aliquet velit. Aliquam vulputate velit imperdiet dolor tempor tristique. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Integer convallis volutpat dui quis scelerisque.&rdquo;</p>
												</blockquote>

												<div class="author-info">
													<div class="author-pic">
														<img src="images/person-1.png" alt="Maria Jones" class="img-fluid">
													</div>
													<h3 class="font-weight-bold">Maria Jones</h3>
													<span class="position d-block mb-3">CEO, Co-Founder, XYZ Inc.</span>
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
												<blockquote class="mb-5">
													<p>&ldquo;Donec facilisis quam ut purus rutrum lobortis. Donec vitae odio quis nisl dapibus malesuada. Nullam ac aliquet velit. Aliquam vulputate velit imperdiet dolor tempor tristique. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Integer convallis volutpat dui quis scelerisque.&rdquo;</p>
												</blockquote>

												<div class="author-info">
													<div class="author-pic">
														<img src="images/person-1.png" alt="Maria Jones" class="img-fluid">
													</div>
													<h3 class="font-weight-bold">Maria Jones</h3>
													<span class="position d-block mb-3">CEO, Co-Founder, XYZ Inc.</span>
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
