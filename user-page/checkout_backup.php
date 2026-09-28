
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
    <title>Checkout - Offo Living</title>
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
            </ul>

            <!-- Right Section -->
            <div class="navbar-right d-flex align-items-center justify-content-end">
                <!-- Icons -->
                <ul class="navbar-nav navbar-icons">
                    <!-- Chat -->
                    <li class="nav-item">
                        <a class="nav-link nav-icon-btn position-relative" href="chat.php">
                            <span class="fa fa-comment"></span>
                            <span class="notif-badge" id="chatBadge">0</span>
                        </a>
                    </li>
                    <!-- Notifications -->
                    <li class="nav-item dropdown">
                        <a class="nav-link nav-icon-btn position-relative" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="fa fa-bell"></span>
                            <span class="notif-badge" id="notifBadge">0</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" id="notificationItems">
                            <li class="dropdown-item text-center text-muted">Tidak ada notifikasi</li>
                        </ul>
                    </li>
                    <!-- Cart -->
                    <li class="nav-item">
                        <a class="nav-link nav-icon-btn position-relative" href="cart.php">
                            <span class="fa fa-shopping-bag"></span>
                            <span class="notif-badge" id="cartBadge">0</span>
                        </a>
                    </li>
                    <!-- User -->
                    <li class="nav-item dropdown">
                        <a class="nav-link nav-icon-btn" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="fa fa-user"></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="pesanan.php">Pesanan</a></li>
                            <li><a class="dropdown-item" href="addresses.php">Alamat</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../database/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
<!-- End Header/Navigation -->

<!-- Start Checkout Section -->
<div class="site-section">
  <div class="container">
    <div class="row mb-5">
      <div class="col-md-12">
        <div class="border p-4 rounded" role="alert">
          <strong>Informasi Pengiriman Anda</strong><br>
          <div id="addressDisplay" class="mt-2">
            <p class="text-muted">Loading alamat...</p>
          </div>
        </div>
      </div>
    </div>

    <form id="checkoutForm">
      <div class="row">
        <div class="col-md-6 mb-5 mb-md-0">
          <h2 class="h3 mb-3 text-black">Detail Pengiriman</h2>
		          <div class="p-3 p-lg-5 border bg-white">
		            <div class="form-group row">
		              <div class="col-md-6">
                <label for="fullname" class="text-black">Nama Lengkap<span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="fullname" name="fullname" required>
              </div>
              <div class="col-md-6">
                <label for="email" class="text-black">Email Address <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="email" name="email" required>
		              </div>	
		            <div class="form-group row">
		              <div class="col-md-12">
		                <label for="c_companyname" class="text-black">Label Alamat (Contoh: Rumah, Kantor) </label>
		                <input type="text" class="form-control" id="c_companyname" name="c_companyname">
		              </div>
		            </div>

		            <div class="form-group row">
		              <div class="col-md-12">
		                <label for="c_address" class="text-black">Alamat <span class="text-danger">*</span></label>
		                <input type="text" class="form-control" id="c_address" name="c_address" placeholder="Alamat Lengkap">
		              </div>
					</div>

		            <div class="form-group row">
		              <div class="col-md-6">
		                <label for="c_state_country" class="text-black">Provinsi <span class="text-danger">*</span></label>
		                <input type="text" class="form-control" id="c_state_country" name="c_state_country">
		              </div>
		              <div class="col-md-6">
		                <label for="c_postal_zip" class="text-black">Kode Pos <span class="text-danger">*</span></label>
		                <input type="text" class="form-control" id="c_postal_zip" name="c_postal_zip">
		              </div>
		            </div>

		            <div class="form-group row mb-5">
		              <div class="col-md-6">
		                <label for="c_email_address" class="text-black">Kota Kabupaten <span class="text-danger">*</span></label>
		                <input type="text" class="form-control" id="c_email_address" name="c_email_address">
		              </div>
		              <div class="col-md-6">
		                <label for="c_phone" class="text-black">Phone <span class="text-danger">*</span></label>
		                <input type="text" class="form-control" id="c_phone" name="c_phone" placeholder="Phone Number">
		              </div>
		            </div>

		            <div class="form-group">
		              <label for="c_create_account" class="text-black" data-bs-toggle="collapse" href="#create_an_account" role="button" aria-expanded="false" aria-controls="create_an_account"><input type="checkbox" value="1" id="c_create_account"> Create an account?</label>
		              <div class="collapse" id="create_an_account">
		                <div class="py-2 mb-4">
		                  <p class="mb-3">Create an account by entering the information below. If you are a returning customer please login at the top of the page.</p>
		                  <div class="form-group">
		                    <label for="c_account_password" class="text-black">Account Password</label>
		                    <input type="email" class="form-control" id="c_account_password" name="c_account_password" placeholder="">
		                  </div>
		                </div>
		              </div>
		            </div>


		            <div class="form-group">
		              <label for="c_ship_different_address" class="text-black" data-bs-toggle="collapse" href="#ship_different_address" role="button" aria-expanded="false" aria-controls="ship_different_address"><input type="checkbox" value="1" id="c_ship_different_address"> Ship To A Different Address?</label>
		              <div class="collapse" id="ship_different_address">
		                <div class="py-2">

		            <div class="form-group row">
		              <div class="col-md-6">
		                <label for="c_fname" class="text-black">Nama Lengkap<span class="text-danger">*</span></label>
		                <input type="text" class="form-control" id="c_fname" name="c_fname">
		              </div>
		              <div class="col-md-6">
		                <label for="c_email_address" class="text-black">Email Address <span class="text-danger">*</span></label>
		                <input type="text" class="form-control" id="c_email_address" name="c_email_address">
		              </div>	
		            <div class="form-group row">
		              <div class="col-md-12">
		                <label for="c_companyname" class="text-black">Label Alamat (Contoh: Rumah, Kantor) </label>
		                <input type="text" class="form-control" id="c_companyname" name="c_companyname">
		              </div>
		            </div>

		            <div class="form-group row">
		              <div class="col-md-12">
		                <label for="c_address" class="text-black">Alamat <span class="text-danger">*</span></label>
		                <input type="text" class="form-control" id="c_address" name="c_address" placeholder="Alamat Lengkap">
		              </div>
					</div>

		            <div class="form-group row">
		              <div class="col-md-6">
		                <label for="c_state_country" class="text-black">Provinsi <span class="text-danger">*</span></label>
		                <input type="text" class="form-control" id="c_state_country" name="c_state_country">
		              </div>
		              <div class="col-md-6">
		                <label for="c_postal_zip" class="text-black">Kode Pos <span class="text-danger">*</span></label>
		                <input type="text" class="form-control" id="c_postal_zip" name="c_postal_zip">
		              </div>
		            </div>

		            <div class="form-group row mb-5">
		              <div class="col-md-6">
		                <label for="c_email_address" class="text-black">Kota Kabupaten <span class="text-danger">*</span></label>
		                <input type="text" class="form-control" id="c_email_address" name="c_email_address">
		              </div>
		              <div class="col-md-6">
		                <label for="c_phone" class="text-black">Phone <span class="text-danger">*</span></label>
		                <input type="text" class="form-control" id="c_phone" name="c_phone" placeholder="Phone Number">
		              </div>
		            </div>
				</div>

		            </div>
		            </div>

		            <div class="form-group">
		              <label for="c_order_notes" class="text-black">Order Notes</label>
		              <textarea name="c_order_notes" id="c_order_notes" cols="30" rows="5" class="form-control" placeholder="Write your notes here..."></textarea>
		            </div>

		          </div>
		        </div>
		        <div class="col-md-6">

		          <div class="row mb-5">
		            <div class="col-md-12">
		              <h2 class="h3 mb-3 text-black">Coupon Code</h2>
		              <div class="p-3 p-lg-5 border bg-white">

		                <label for="c_code" class="text-black mb-3">Enter your coupon code if you have one</label>
		                <div class="input-group w-75 couponcode-wrap">
		                  <input type="text" class="form-control me-2" id="c_code" placeholder="Coupon Code" aria-label="Coupon Code" aria-describedby="button-addon2">
		                  <div class="input-group-append">
		                    <button class="btn btn-black btn-sm" type="button" id="button-addon2">Apply</button>
		                  </div>
		                </div>

		              </div>
		            </div>
		          </div>

		          <div class="row mb-5">
		            <div class="col-md-12">
		              <h2 class="h3 mb-3 text-black">Your Order</h2>
		              <div class="p-3 p-lg-5 border bg-white">
		                <table class="table site-block-order-table mb-5">
		                  <thead>
		                    <th>Product</th>
		                    <th>Total</th>
		                  </thead>
		                  <tbody>
		                    <tr>
		                      <td>Kursi Kantor <strong class="mx-2">x</strong> 2</td>
		                      <td>Rp. 330.000,00</td>
		                    </tr>
		                    <tr>
		                      <td>Meja Makan <strong class="mx-2">x</strong>   1</td>
		                      <td>Rp. 100.000,00</td>
		                    </tr>
							 <tr>
		                      <td>Sofa Bed <strong class="mx-2">x</strong>   1</td>
		                      <td>Rp. 1.450.000,00</td>
		                    </tr>
		                    <tr>
		                      <td class="text-black font-weight-bold"><strong>Cart Subtotal</strong></td>
		                      <td class="text-black">Rp. 1.880.000,00</td>
		                    </tr>
		                    <tr>
		                      <td class="text-black font-weight-bold"><strong>Order Total</strong></td>
		                      <td class="text-black font-weight-bold"><strong>Rp. 1.880.000,00</strong></td>
		                    </tr>
		                  </tbody>
		                </table>

		                <div class="border p-3 mb-3">
		                  <h3 class="h6 mb-0"><a class="d-block" data-bs-toggle="collapse" href="#collapsebank" role="button" aria-expanded="false" aria-controls="collapsebank">Direct Bank Transfer</a></h3>

		                  <div class="collapse" id="collapsebank">
		                    <div class="py-2">
		                      <p class="mb-0">Make your payment directly into our bank account. Please use your Order ID as the payment reference. Your order won’t be shipped until the funds have cleared in our account.</p>
		                    </div>
		                  </div>
		                </div>

		                <div class="border p-3 mb-3">
		                  <h3 class="h6 mb-0"><a class="d-block" data-bs-toggle="collapse" href="#collapsecheque" role="button" aria-expanded="false" aria-controls="collapsecheque">Cheque Payment</a></h3>

		                  <div class="collapse" id="collapsecheque">
		                    <div class="py-2">
		                      <p class="mb-0">Make your payment directly into our bank account. Please use your Order ID as the payment reference. Your order won’t be shipped until the funds have cleared in our account.</p>
		                    </div>
		                  </div>
		                </div>

		                <div class="border p-3 mb-5">
		                  <h3 class="h6 mb-0"><a class="d-block" data-bs-toggle="collapse" href="#collapsepaypal" role="button" aria-expanded="false" aria-controls="collapsepaypal">Paypal</a></h3>

		                  <div class="collapse" id="collapsepaypal">
		                    <div class="py-2">
		                      <p class="mb-0">Make your payment directly into our bank account. Please use your Order ID as the payment reference. Your order won’t be shipped until the funds have cleared in our account.</p>
		                    </div>
		                  </div>
		                </div>

		                <div class="form-group">
		                  <button class="btn btn-black btn-lg py-3 btn-block" onclick="window.location='thankyou.php'">Place Order</button>
		                </div>

		              </div>
		            </div>
		          </div>

		        </div>
		      </div>
		      <!-- </form> -->
		    </div>
		  </div>

<!-- End Checkout Section -->

<?php include 'footer.php'; ?>

<script src="../js/bootstrap.bundle.min.js"></script>
<script>
    const userId = <?php echo $userId; ?>;
    const formatRupiah = (value) => {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
    };

    let cartItems = [];
    let vouchers = {
        'DISKON10': { discount: 0.10, label: 'Diskon 10%' },
        'DISKON20': { discount: 0.20, label: 'Diskon 20%' },
        'GRATIS': { discount: 1.0, label: 'Gratis Ongkos Kirim' }
    };
    let appliedVoucher = null;
    let totalDiscount = 0;
    const shippingCost = 50000; // Default shipping

    // Load cart from localStorage
    function loadCart() {
        const stored = localStorage.getItem('cart');
      cartItems = stored ? JSON.parse(stored) : [];
      if (cartItems.length === 0) {
        document.getElementById('productsSection').innerHTML = '<p class="text-danger">Keranjang Anda kosong. <a href="shop.php">Belanja sekarang</a></p>';
        updateTotal(0);
        return false;
      }
      renderProducts();
      return true;
    }

    // Render products
    function renderProducts() {
        let html = '';
        let subtotal = 0;

        cartItems.forEach(item => {
            const total = item.price * item.quantity;
            subtotal += total;
            html += `
                <div class="product-item">
                    <img src="${item.image || '../images/product-1.png'}" alt="${item.name}" class="product-image">
                    <div style="flex: 1;">
                        <h6 style="margin: 0; font-weight: 700;">${item.name}</h6>
                        <small class="text-muted">${item.color || 'Default'}</small>
                        <p style="margin: 5px 0 0 0;">
                            ${formatRupiah(item.price)} x ${item.quantity} = <strong>${formatRupiah(total)}</strong>
                        </p>
                    </div>
                </div>
            `;
        });

        document.getElementById('productsSection').innerHTML = html;
        updateTotal(subtotal);
    }

    // Load address
    function loadAddress() {
        const address = <?php echo json_encode($primaryAddress); ?>;
        if (!address) {
            document.getElementById('addressDisplay').innerHTML = '<p class="text-danger">Belum ada alamat. <a href="addresses.php">Tambah alamat</a></p>';
            return;
        }

        const addressHtml = `
            <strong>Alamat Pengiriman Utama</strong><br>
            ${address.jalan}<br>
            ${address.kota}, ${address.provinsi} ${address.kodepos}
        `;
        document.getElementById('addressDisplay').innerHTML = addressHtml;
    }

    // Update total payment
    function updateTotal(subtotal) {
        totalDiscount = 0;

        // Apply voucher discount
        if (appliedVoucher && vouchers[appliedVoucher]) {
            const voucherInfo = vouchers[appliedVoucher];
            if (voucherInfo.discount === 1.0) {
                // Free shipping voucher
                totalDiscount = shippingCost;
            } else {
                totalDiscount = Math.floor(subtotal * voucherInfo.discount);
            }
        }

        const finalShipping = appliedVoucher && vouchers[appliedVoucher]?.discount === 1.0 ? 0 : shippingCost;
        const totalPayment = subtotal + finalShipping - totalDiscount;

        document.getElementById('subtotalDisplay').textContent = `${formatRupiah(subtotal)} (${cartItems.length} item)`;
        document.getElementById('discountDisplay').textContent = `-${formatRupiah(totalDiscount)}`;
        document.getElementById('shippingDisplay').textContent = formatRupiah(finalShipping);
        document.getElementById('totalDisplay').textContent = formatRupiah(totalPayment);

        // Store in window for payment processing
        window.orderData = {
            subtotal: subtotal,
            discount: totalDiscount,
            shipping: finalShipping,
            total: totalPayment,
            items: cartItems
        };
    }

    // Apply voucher
    document.getElementById('applyVoucherBtn').addEventListener('click', function() {
        const code = document.getElementById('voucherCode').value.toUpperCase();
        const messageEl = document.getElementById('voucherMessage');

        if (!code) {
            messageEl.innerHTML = '<span class="text-danger">Masukkan kode voucher.</span>';
            return;
        }

        if (vouchers[code]) {
            appliedVoucher = code;
            const discountText = vouchers[code].label;
            messageEl.innerHTML = `<span class="text-success"><i class="fa fa-check-circle"></i> Voucher "${code}" berhasil diterapkan - ${discountText}</span>`;
            
            // Recalculate total
            const subtotal = cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            updateTotal(subtotal);
        } else {
            messageEl.innerHTML = '<span class="text-danger">Kode voucher tidak valid.</span>';
            appliedVoucher = null;
        }
    });

    // Process payment
    function processPayment() {
        if (!loadCart() || !window.orderData) {
            alert('Data pesanan tidak lengkap.');
            return;
        }

        const message = document.getElementById('buyerMessage').value;
        const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
        const address = <?php echo json_encode($primaryAddress); ?>;

        if (!address) {
            alert('Pilih alamat pengiriman terlebih dahulu.');
            return;
        }

        const orderData = {
            userId: userId,
            items: cartItems,
            message: message,
            paymentMethod: paymentMethod,
            address: address,
            total: window.orderData.total,
            discount: window.orderData.discount,
            shipping: window.orderData.shipping,
            voucher: appliedVoucher,
            timestamp: new Date().toISOString()
        };

        // Save order to localStorage
        const orders = JSON.parse(localStorage.getItem('orders') || '[]');
        orders.push(orderData);
        localStorage.setItem('orders', JSON.stringify(orders));

        // Add notification
        addNotification({
            type: 'success',
            message: `Pesanan berhasil dibuat - Total: ${formatRupiah(window.orderData.total)}`,
            time: new Date().toLocaleString('id-ID')
        });

        // Clear cart
        localStorage.removeItem('cart');

        // Redirect to thank you page
        setTimeout(() => {
            window.location.href = 'thankyou.php';
        }, 2000);
    }

    // Notification system
    function addNotification(notif) {
        const notifications = JSON.parse(localStorage.getItem('notifications') || '[]');
        notifications.unshift(notif);
        // Keep only last 10 notifications
        if (notifications.length > 10) notifications.pop();
        localStorage.setItem('notifications', JSON.stringify(notifications));
        updateNotificationBadge();
    }

    function updateNotificationBadge() {
        const notifications = JSON.parse(localStorage.getItem('notifications') || '[]');
        const badge = document.getElementById('notifBadge');
        badge.textContent = notifications.length;

        const dropdown = document.getElementById('notificationItems');
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

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        loadCart();
        loadAddress();
        updateNotificationBadge();
    });
</script>
</body>
</html>
