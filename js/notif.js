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

        cartItems = cartItems.map(item => {
            // Standardize cart quantity field
            const q = (item && (item.quantity ?? item.qty)) ?? 1;
            return { ...item, quantity: Math.max(1, parseInt(q, 10) || 1) };
        });

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
        let freeShipping = false;

        // Apply voucher discount
        if (appliedVoucher && vouchers[appliedVoucher]) {
            const voucherInfo = vouchers[appliedVoucher];
            if (voucherInfo.discount === 1.0) {
                // Free shipping voucher
                freeShipping = true;
                totalDiscount = 0;
            } else {
                totalDiscount = Math.floor(subtotal * voucherInfo.discount);
            }
        }

        const finalShipping = freeShipping ? 0 : shippingCost;
        const totalPayment = subtotal + finalShipping - totalDiscount;

        document.getElementById('subtotalDisplay').textContent = `${formatRupiah(subtotal)} (${cartItems.length} item)`;
        document.getElementById('discountDisplay').textContent = totalDiscount > 0 ? `-${formatRupiah(totalDiscount)}` : formatRupiah(0);
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
    document.addEventListener('DOMContentLoaded', function() {
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
                const subtotal = cartItems.reduce((sum, item) => sum + (item.price * (item.quantity ?? item.qty ?? 1)), 0);
                // Persist normalized quantity (optional)
                cartItems = cartItems.map(it => ({ ...it, quantity: it.quantity ?? it.qty ?? 1 }));
                updateTotal(subtotal);

            } else {
                messageEl.innerHTML = '<span class="text-danger">Kode voucher tidak valid.</span>';
                appliedVoucher = null;
            }
        });

        // Initialize page
        loadCart();
        loadAddress();
        updateNotificationBadge();
    });

    // Process payment
    function processPayment() {
        if (!window.orderData || !window.orderData.items || window.orderData.items.length === 0) {
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
            items: window.orderData.items,
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
        }, 1200);
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