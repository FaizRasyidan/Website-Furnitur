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
$addresses = [];
$check = $conn->query("SHOW TABLES LIKE 'user_addresses'");
if ($check && $check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_primary DESC, updated_at DESC");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $normalized = [];
            $normalized['id'] = $row['id'] ?? null;
            $normalized['label'] = $row['label'] ?? 'Rumah';
            $normalized['recipient_name'] = $row['recipient_name'] ?? '';
            $normalized['phone'] = $row['phone'] ?? '';
            $normalized['jalan'] = $row['address'] ?? $row['jalan'] ?? $row['alamat'] ?? $row['street'] ?? '';
            $normalized['kota'] = $row['city'] ?? $row['kota'] ?? '';
            $normalized['provinsi'] = $row['province'] ?? $row['provinsi'] ?? $row['prov'] ?? '';
            $normalized['kodepos'] = $row['postal_code'] ?? $row['kodepos'] ?? $row['zip'] ?? '';
            $normalized['is_primary'] = isset($row['is_primary']) ? (int)$row['is_primary'] : (isset($row['PRIMARY_ADDRESS']) ? (int)$row['PRIMARY_ADDRESS'] : 0);
            $addresses[] = $normalized;
        }
        $stmt->close();
    }
}

// Determine primary address
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

if (!$primaryAddress && count($addresses) > 0) {
    foreach ($addresses as $addr) {
        if ($addr['is_primary'] == 1) {
            $primaryAddress = $addr;
            break;
        }
    }
}

if (!$primaryAddress && count($addresses) > 0) {
    $primaryAddress = $addresses[0];
}

// Fetch categories for footer
$categories = [];
$stmt = $conn->prepare("SELECT id, name FROM categories ORDER BY name ASC");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    $stmt->close();
}

// Fetch store locations for footer
$storeLocations = [];
$storeLocationsJson = '[]';
$stmt = $conn->prepare("SELECT id, name, latitude, longitude, address FROM store_locations ORDER BY name ASC");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $storeLocations[] = $row;
    }
    $storeLocationsJson = json_encode($storeLocations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt->close();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="author" content="Furni">
    <link rel="shortcut icon" href="logo.png">
    <meta name="description" content="Checkout - Offo Living Furniture Store" />
    <meta name="keywords" content="furniture, checkout, online store" />
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
        <a class="navbar-brand" href="user_page.php">Offo<span>Living</span></a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbars" aria-controls="navbars" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbars">
            <ul class="custom-navbar-nav navbar-nav me-auto mb-2 mb-md-0">
                <li class="nav-item"><a class="nav-link" href="user_page.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="shop.php">Shop</a></li>
                <li class="nav-item"><a class="nav-link" href="about.php">About Us</a></li>
                <li class="nav-item"><a class="nav-link" href="pesanan.php">Pesanan Saya</a></li>
            </ul>

            <div class="navbar-right d-flex align-items-center justify-content-end">
                <ul class="navbar-nav navbar-icons">
                    <li class="nav-item">
                        <a class="nav-link nav-icon-btn position-relative" href="chat.php">
                            <span class="fa fa-comment"></span>
                            <span class="notif-badge" id="chatBadge">0</span>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link nav-icon-btn position-relative" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="fa fa-bell"></span>
                            <span class="notif-badge" id="notifBadge">0</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" id="notificationItems">
                            <li class="dropdown-item text-center text-muted">Tidak ada notifikasi</li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-icon-btn position-relative" href="cart.php">
                            <span class="fa fa-shopping-bag"></span>
                            <span class="notif-badge" id="cartBadge">0</span>
                        </a>
                    </li>
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
                <h1 class="h2 mb-3">Checkout</h1>
                <div class="border p-4 rounded bg-light">
                    <strong>Informasi Pengiriman Anda</strong><br>
                    <div id="addressDisplay" class="mt-2 small">
                        <p class="text-muted">Loading alamat...</p>
                    </div>
                </div>
            </div>
        </div>

        <form id="checkoutForm" method="POST">
            <div class="row">
                <!-- Left Column: Billing Details -->
                <div class="col-lg-6 mb-5">
                    <h2 class="h3 mb-4 text-black">Detail Pengiriman</h2>
                    <div class="p-4 border bg-white">
                        <div class="form-group row mb-3">
                            <div class="col-md-6">
                                <label for="fullname" class="form-label text-black">Nama Lengkap<span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="fullname" name="fullname" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label text-black">Email<span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>

                        <div class="form-group row mb-3">
                            <div class="col-md-12">
                                <label for="selectedAddress" class="form-label text-black">Pilih Alamat Pengiriman<span class="text-danger">*</span></label>
                                <select class="form-select" id="selectedAddress" name="selectedAddress" required onchange="loadAddressDetails()">
                                    <option value="">-- Pilih alamat --</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group row mb-3">
                            <div class="col-md-12">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.location.href='addresses.php'">
                                    <i class="fa fa-plus me-2"></i> Tambah Alamat Baru
                                </button>
                            </div>
                        </div>

                        <!-- Hidden fields untuk address details -->
                        <input type="hidden" id="address_label" name="address_label">
                        <input type="hidden" id="address" name="address">
                        <input type="hidden" id="province" name="province">
                        <input type="hidden" id="city" name="city">
                        <input type="hidden" id="postal_code" name="postal_code">
                        <input type="hidden" id="phone" name="phone">

                        <div class="form-group mb-3">
                            <label for="notes" class="form-label text-black">Catatan Pesanan</label>
                            <textarea name="notes" id="notes" rows="4" class="form-control" placeholder="Tambahkan catatan khusus untuk pesanan Anda (opsional)..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Order Summary, Voucher, Payment Method -->
                <div class="col-lg-6">
                    <!-- Order Summary -->
                    <div class="mb-5">
                        <h2 class="h3 mb-4 text-black">Ringkasan Pesanan</h2>
                        <div class="p-4 border bg-white">
                            <div id="productsSection" class="mb-4" style="max-height: 280px; overflow-y: auto; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                                <p class="text-center text-muted small">Loading produk dari keranjang...</p>
                            </div>

                            <table class="table table-sm mb-3">
                                <tbody>
                                    <tr>
                                        <td class="text-muted">Subtotal</td>
                                        <td class="text-end"><strong id="subtotalDisplay">Rp 0</strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Diskon/Voucher</td>
                                        <td class="text-end"><strong id="discountDisplay" class="text-success">-Rp 0</strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Ongkos Kirim</td>
                                        <td class="text-end"><strong id="shippingDisplay">Rp 50.000</strong></td>
                                    </tr>
                                    <tr class="border-top border-2">
                                        <td><strong>Total Pembayaran</strong></td>
                                        <td class="text-end"><strong id="totalDisplay" style="font-size: 1.2em; color: #d9534f;">Rp 0</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Voucher Section -->
                    <div class="mb-5">
                        <h2 class="h3 mb-4 text-black">Kode Voucher</h2>
                        <div class="p-4 border bg-white">
                            <label for="voucherCode" class="form-label text-black mb-2">Masukkan kode voucher (jika ada)</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="voucherCode" placeholder="Contoh: DISKON10" autocomplete="off">
                                <button class="btn btn-outline-secondary" type="button" id="applyVoucherBtn">Terapkan</button>
                            </div>
                            <div id="voucherMessage" class="mt-2"></div>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="mb-5">
                        <h2 class="h3 mb-4 text-black">Metode Pembayaran</h2>
                        <div class="p-4 border bg-white">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="paymentMethod" id="bank_transfer" value="bank_transfer" required>
                                <label class="form-check-label" for="bank_transfer">
                                    <strong>Transfer Bank</strong>
                                    <br><small class="text-muted">Transfer langsung ke rekening bank kami</small>
                                </label>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="paymentMethod" id="e_wallet" value="e_wallet">
                                <label class="form-check-label" for="e_wallet">
                                    <strong>E-Wallet</strong>
                                    <br><small class="text-muted">GCash, PayMaya, atau e-wallet lainnya</small>
                                </label>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="paymentMethod" id="cod" value="cod">
                                <label class="form-check-label" for="cod">
                                    <strong>Cash on Delivery (COD)</strong>
                                    <br><small class="text-muted">Bayar saat barang diterima</small>
                                </label>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="paymentMethod" id="midtrans" value="midtrans">
                                <label class="form-check-label" for="midtrans">
                                    <strong>Midtrans Payment Gateway</strong>
                                    <br><small class="text-muted">Berbagai metode pembayaran terintegrasi</small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div>
                        <button type="button" class="btn btn-black btn-lg w-100 py-3" id="submitPaymentBtn" onclick="processPayment(event)">
                            <i class="fa fa-check-circle me-2"></i> Buat Pesanan
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<!-- End Checkout Section -->

<!-- Payment Simulation Modals -->
<!-- Bank Transfer Simulation Modal -->
<div class="modal fade" id="bankTransferModal" tabindex="-1" aria-labelledby="bankTransferLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="bankTransferLabel">Simulasi Transfer Bank</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-5">
                <div id="bankTransferContent">
                    <div class="mb-4">
                        <i class="fas fa-university fa-4x text-primary mb-3"></i>
                        <h5>Menghubungkan ke Bank...</h5>
                    </div>
                    <div class="progress mb-3">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%;"></div>
                    </div>
                    <p class="text-muted small">Mohon tunggu, sedang memproses pembayaran...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- E-Wallet Simulation Modal -->
<div class="modal fade" id="eWalletModal" tabindex="-1" aria-labelledby="eWalletLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="eWalletLabel">Simulasi Pembayaran E-Wallet</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-5">
                <div id="eWalletContent">
                    <div class="mb-4">
                        <i class="fas fa-wallet fa-4x text-info mb-3"></i>
                        <h5>Mengarahkan ke E-Wallet...</h5>
                    </div>
                    <div class="progress mb-3">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%;"></div>
                    </div>
                    <p class="text-muted small">Sedang mengarahkan ke aplikasi e-wallet...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- COD Simulation Modal -->
<div class="modal fade" id="codModal" tabindex="-1" aria-labelledby="codLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="codLabel">Konfirmasi Cash on Delivery</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-5">
                <div id="codContent">
                    <div class="mb-4">
                        <i class="fas fa-truck fa-4x text-success mb-3"></i>
                        <h5>Pesanan siap dikirim!</h5>
                    </div>
                    <div class="alert alert-info">
                        <p class="mb-0 small">Anda akan membayar saat barang sampai di tangan Anda. Pastikan Anda siap untuk menerima barang.</p>
                    </div>
                    <div class="progress mb-3">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 100%;"></div>
                    </div>
                    <p class="text-muted small">Memproses pesanan COD...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Midtrans Simulation Modal -->
<div class="modal fade" id="midtransModal" tabindex="-1" aria-labelledby="midtransLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="midtransLabel">Simulasi Midtrans Payment Gateway</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-5">
                <div id="midtransContent">
                    <div class="mb-4">
                        <i class="fas fa-credit-card fa-4x text-warning mb-3"></i>
                        <h5>Membuka halaman pembayaran...</h5>
                    </div>
                    <div class="progress mb-3">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning" role="progressbar" style="width: 100%;"></div>
                    </div>
                    <p class="text-muted small">Menghubungkan ke Midtrans...</p>
                    <p class="text-muted small">Pilih metode pembayaran yang Anda inginkan di halaman berikutnya.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script src="../js/bootstrap.bundle.min.js"></script>
<script src="../js/custom.js"></script>
<script>
    const userId = <?php echo $userId; ?>;
    const primaryAddress = <?php echo json_encode($primaryAddress); ?>;
    const allAddresses = <?php echo json_encode($addresses); ?>;

    // Formatting function
    const formatRupiah = (value) => {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
    };

    // Data
    let cartItems = [];
    let vouchers = {
        'DISKON10': { discount: 0.10, label: 'Diskon 10%' },
        'DISKON20': { discount: 0.20, label: 'Diskon 20%' },
        'GRATIS': { discount: 1.0, label: 'Gratis Ongkos Kirim' }
    };
    let appliedVoucher = null;
    let totalDiscount = 0;
    const shippingCost = 50000;

    // Load cart from localStorage
    function loadCart() {
        const userCartKey = 'cart_' + userId;
        const stored = localStorage.getItem(userCartKey);
        cartItems = stored ? JSON.parse(stored) : [];
        
        if (cartItems.length === 0) {
            document.getElementById('productsSection').innerHTML = '<p class="text-danger text-center"><i class="fa fa-shopping-cart me-2"></i>Keranjang Anda kosong. <a href="shop.php" class="btn btn-sm btn-outline-primary">Belanja Sekarang</a></p>';
            updateTotal(0);
            return false;
        }
        
        renderProducts();
        return true;
    }

    // Render cart products
    function renderProducts() {
        let html = '';
        let subtotal = 0;

        cartItems.forEach(item => {
            const total = item.price * item.quantity;
            subtotal += total;
            html += `
                <div class="d-flex gap-3 mb-3">
                    <img src="${item.image || '../images/product-placeholder.png'}" alt="${item.name}" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                    <div class="flex-grow-1 small">
                        <strong>${item.name}</strong>
                        <br><span class="text-muted">${item.color || 'Standard'}</span>
                        <br><strong>${formatRupiah(item.price)} × ${item.quantity} = ${formatRupiah(total)}</strong>
                    </div>
                </div>
            `;
        });

        document.getElementById('productsSection').innerHTML = html;
        updateTotal(subtotal);
    }

    // Load and display address selector
    function loadAddress() {
        const selectEl = document.getElementById('selectedAddress');
        
        if (!allAddresses || allAddresses.length === 0) {
            document.getElementById('addressDisplay').innerHTML = '<p class="text-danger"><i class="fa fa-exclamation-triangle me-2"></i>Belum ada alamat. <a href="addresses.php" class="btn btn-sm btn-outline-primary">Tambah Alamat</a></p>';
            selectEl.innerHTML = '<option value="">-- Tidak ada alamat --</option>';
            return;
        }

        // Populate dropdown
        let options = '<option value="">-- Pilih alamat --</option>';
        let primaryAddressId = null;
        
        allAddresses.forEach(addr => {
            const displayText = `${addr.label} - ${addr.jalan}, ${addr.kota}`;
            options += `<option value="${addr.id}" data-address='${JSON.stringify(addr)}'>${displayText}</option>`;
            if (addr.is_primary === 1) {
                primaryAddressId = addr.id;
            }
        });
        
        selectEl.innerHTML = options;
        
        // Select primary address by default
        if (primaryAddressId) {
            selectEl.value = primaryAddressId;
            loadAddressDetails();
        }

        // Display primary address info
        const displayAddr = allAddresses.find(a => a.is_primary === 1) || allAddresses[0];
        if (displayAddr) {
            const addressHtml = `
                <strong>${displayAddr.label}</strong><br>
                <i class="fa fa-user me-2"></i> ${displayAddr.recipient_name}<br>
                <i class="fa fa-phone me-2"></i> ${displayAddr.phone}<br>
                <i class="fa fa-map-marker me-2"></i> ${displayAddr.jalan}, ${displayAddr.kota}, ${displayAddr.provinsi} ${displayAddr.kodepos}
            `;
            document.getElementById('addressDisplay').innerHTML = addressHtml;
        }
    }

    // Load selected address details
    function loadAddressDetails() {
        const selectEl = document.getElementById('selectedAddress');
        const selectedOption = selectEl.options[selectEl.selectedIndex];
        
        if (!selectedOption || !selectedOption.value) {
            return;
        }

        const address = JSON.parse(selectedOption.getAttribute('data-address'));
        
        // Fill hidden fields
        document.getElementById('address_label').value = address.label || 'Rumah';
        document.getElementById('address').value = address.jalan || '';
        document.getElementById('province').value = address.provinsi || '';
        document.getElementById('city').value = address.kota || '';
        document.getElementById('postal_code').value = address.kodepos || '';
        document.getElementById('phone').value = address.phone || '';

        // Update display
        const addressHtml = `
            <strong>${address.label}</strong><br>
            <i class="fa fa-user me-2"></i> ${address.recipient_name}<br>
            <i class="fa fa-phone me-2"></i> ${address.phone}<br>
            <i class="fa fa-map-marker me-2"></i> ${address.jalan}, ${address.kota}, ${address.provinsi} ${address.kodepos}
        `;
        document.getElementById('addressDisplay').innerHTML = addressHtml;
    }

    // Update total
    function updateTotal(subtotal) {
        totalDiscount = 0;

        if (appliedVoucher && vouchers[appliedVoucher]) {
            const voucherInfo = vouchers[appliedVoucher];
            if (voucherInfo.discount === 1.0) {
                totalDiscount = shippingCost;
            } else {
                totalDiscount = Math.floor(subtotal * voucherInfo.discount);
            }
        }

        const finalShipping = appliedVoucher && vouchers[appliedVoucher]?.discount === 1.0 ? 0 : shippingCost;
        const totalPayment = subtotal + finalShipping - totalDiscount;

        document.getElementById('subtotalDisplay').textContent = formatRupiah(subtotal);
        document.getElementById('discountDisplay').textContent = `-${formatRupiah(totalDiscount)}`;
        document.getElementById('shippingDisplay').textContent = formatRupiah(finalShipping);
        document.getElementById('totalDisplay').textContent = formatRupiah(totalPayment);

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
        const code = document.getElementById('voucherCode').value.toUpperCase().trim();
        const messageEl = document.getElementById('voucherMessage');

        if (!code) {
            messageEl.innerHTML = '<div class="alert alert-warning alert-sm py-2 mb-0"><small><i class="fa fa-info-circle me-2"></i>Masukkan kode voucher.</small></div>';
            return;
        }

        if (vouchers[code]) {
            appliedVoucher = code;
            const discountText = vouchers[code].label;
            messageEl.innerHTML = `<div class="alert alert-success alert-sm py-2 mb-0"><small><i class="fa fa-check-circle me-2"></i>Voucher "${code}" berhasil diterapkan - ${discountText}</small></div>`;
            
            const subtotal = cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            updateTotal(subtotal);
        } else {
            messageEl.innerHTML = '<div class="alert alert-danger alert-sm py-2 mb-0"><small><i class="fa fa-times-circle me-2"></i>Kode voucher tidak valid.</small></div>';
            appliedVoucher = null;
        }
    });

    // Generate order ID
    function generateOrderId() {
        const timestamp = Date.now();
        const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
        return `ORD-${userId}-${timestamp}-${random}`;
    }

    // Process payment with simulation
    function processPayment(event) {
        if (!loadCart()) {
            alert('Keranjang Anda kosong. Silakan tambahkan produk sebelum checkout.');
            window.location.href = 'shop.php';
            return;
        }

        // Validate form
        const form = document.getElementById('checkoutForm');
        
        // Check if address is selected
        const selectedAddressEl = document.getElementById('selectedAddress');
        if (!selectedAddressEl.value) {
            alert('Silakan pilih alamat pengiriman.');
            selectedAddressEl.focus();
            return;
        }

        if (!form.checkValidity()) {
            alert('Silakan isi semua field yang wajib diisi.');
            return;
        }

        // Validate payment method
        const paymentMethodEl = document.querySelector('input[name="paymentMethod"]:checked');
        if (!paymentMethodEl) {
            alert('Pilih metode pembayaran.');
            return;
        }

        if (!window.orderData) {
            alert('Data pesanan tidak valid.');
            return;
        }

        // Show payment simulation modal
        const paymentMethod = paymentMethodEl.value;
        let modalId;
        
        switch(paymentMethod) {
            case 'bank_transfer':
                modalId = 'bankTransferModal';
                break;
            case 'e_wallet':
                modalId = 'eWalletModal';
                break;
            case 'cod':
                modalId = 'codModal';
                break;
            case 'midtrans':
                modalId = 'midtransModal';
                break;
            default:
                modalId = null;
        }

        if (modalId) {
            const modal = new bootstrap.Modal(document.getElementById(modalId));
            modal.show();
            
            // Process payment after simulation completes (2-3 seconds)
            setTimeout(() => {
                modal.hide();
                performPaymentProcessing(paymentMethod);
            }, 3000);
        } else {
            performPaymentProcessing(paymentMethod);
        }
    }

    // Perform actual payment processing
    function performPaymentProcessing(paymentMethod) {
        // Disable button to prevent multiple submissions
        const btnSubmit = document.getElementById('submitPaymentBtn');
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i> Memproses...';

        const fullname = document.getElementById('fullname').value.trim();
        const nameParts = fullname.split(' ');
        const firstName = nameParts[0] || '';
        const lastName = nameParts.slice(1).join(' ') || '';
        
        const orderId = generateOrderId();
        const email = document.getElementById('email').value.trim();
        const phone = document.getElementById('phone').value.trim();

        const orderData = {
            order_id: orderId,
            user_id: userId,
            items: cartItems,
            fullname: fullname,
            first_name: firstName,
            last_name: lastName,
            email: email,
            phone: phone,
            address: {
                label: document.getElementById('address_label').value,
                street: document.getElementById('address').value,
                city: document.getElementById('city').value,
                province: document.getElementById('province').value,
                postalCode: document.getElementById('postal_code').value
            },
            message: document.getElementById('notes').value,
            payment_method: paymentMethod,
            payment_type: paymentMethod === 'cod' ? 'cod' : 'immediate',
            total: window.orderData.total,
            discount: window.orderData.discount,
            shipping: window.orderData.shipping,
            voucher: appliedVoucher || '',
            timestamp: new Date().toISOString()
        };

        // Step 1: Call fake_gateway for payment token
        const gatewayPayload = {
            order_id: orderId,
            gross_amount: window.orderData.total,
            payment_method: paymentMethod,
            customer_details: {
                first_name: firstName,
                last_name: lastName,
                email: email,
                phone: phone
            }
        };

        fetch('../database/fake_gateway.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(gatewayPayload)
        })
        .then(res => res.json())
        .then(gatewayResponse => {
            if (!gatewayResponse.payment_token) {
                throw new Error(gatewayResponse.error || 'Payment gateway error');
            }

            // Step 2: Save order to database
            const processPayload = {
                order_id: orderId,
                total: window.orderData.total,
                discount: window.orderData.discount,
                shipping: window.orderData.shipping,
                voucher: appliedVoucher || '',
                payment_method: paymentMethod,
                payment_type: orderData.payment_type,
                message: document.getElementById('notes').value,
                address: orderData.address,
                items: cartItems
            };

            return fetch('../database/process_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(processPayload)
            })
            .then(res => res.json())
            .then(paymentResponse => {
                if (!paymentResponse.ok && !paymentResponse.success) {
                    throw new Error(paymentResponse.error || 'Payment processing failed');
                }
                
                // Store order data in localStorage for thank you page
                const orders = JSON.parse(localStorage.getItem('orders') || '[]');
                orders.push({
                    ...orderData,
                    payment_token: gatewayResponse.payment_token,
                    payment_status: orderData.payment_type === 'cod' ? 'pending' : 'success'
                });
                localStorage.setItem('orders', JSON.stringify(orders));

                // Add notification
                addNotification({
                    type: 'success',
                    message: `Pesanan ${orderId} berhasil dibuat - Total: ${formatRupiah(window.orderData.total)}`,
                    time: new Date().toLocaleString('id-ID')
                });

                // Clear cart
                const userCartKey = 'cart_' + userId;
                localStorage.removeItem(userCartKey);

                // Store order details for thank you page
                localStorage.setItem('lastOrderId', orderId);
                localStorage.setItem('lastOrderData', JSON.stringify(orderData));

                // Redirect after success
                setTimeout(() => {
                    window.location.href = 'thankyou.php';
                }, 1500);
            });
        })
        .catch(error => {
            console.error('Payment error:', error);
            addNotification({
                type: 'error',
                message: `Pembayaran gagal: ${error.message}`,
                time: new Date().toLocaleString('id-ID')
            });
            
            // Re-enable button
            const btnSubmit = document.getElementById('submitPaymentBtn');
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fa fa-check-circle me-2"></i> Buat Pesanan';
            
            alert(`Pembayaran gagal: ${error.message}`);
        });
    }

    // Notification system
    function addNotification(notif) {
        const userNotificationKey = 'notifications_' + userId;
        const notifications = JSON.parse(localStorage.getItem(userNotificationKey) || '[]');
        notifications.unshift(notif);
        if (notifications.length > 10) notifications.pop();
        localStorage.setItem(userNotificationKey, JSON.stringify(notifications));
        updateNotificationBadge();
    }

    function updateNotificationBadge() {
        const userNotificationKey = 'notifications_' + userId;
        const notifications = JSON.parse(localStorage.getItem(userNotificationKey) || '[]');
        const badge = document.getElementById('notifBadge');
        if (badge) badge.textContent = notifications.length;
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadCart();
        loadAddress();
        updateNotificationBadge();
    });
</script>
</body>
</html>
