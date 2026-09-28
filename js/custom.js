(function() {
	'use strict';

	var tinyslider = function() {
		var el = document.querySelectorAll('.testimonial-slider');

		if (el.length > 0) {
			var slider = tns({
				container: '.testimonial-slider',
				items: 1,
				axis: "horizontal",
				controlsContainer: "#testimonial-nav",
				swipeAngle: false,
				speed: 700,
				nav: true,
				controls: true,
				autoplay: true,
				autoplayHoverPause: true,
				autoplayTimeout: 3500,
				autoplayButtonOutput: false
			});
		}
	};
	tinyslider();

	


	var sitePlusMinus = function() {

		var value,
    		quantity = document.getElementsByClassName('quantity-container');

		function createBindings(quantityContainer) {
	      var quantityAmount = quantityContainer.getElementsByClassName('quantity-amount')[0];
	      var increase = quantityContainer.getElementsByClassName('increase')[0];
	      var decrease = quantityContainer.getElementsByClassName('decrease')[0];
	      increase.addEventListener('click', function (e) { increaseValue(e, quantityAmount); });
	      decrease.addEventListener('click', function (e) { decreaseValue(e, quantityAmount); });
	    }

	    function init() {
	        for (var i = 0; i < quantity.length; i++ ) {
						createBindings(quantity[i]);
	        }
	    };

	    function increaseValue(event, quantityAmount) {
	        value = parseInt(quantityAmount.value, 10);

	        console.log(quantityAmount, quantityAmount.value);

	        value = isNaN(value) ? 0 : value;
	        value++;
	        quantityAmount.value = value;
	    }

	    function decreaseValue(event, quantityAmount) {
	        value = parseInt(quantityAmount.value, 10);

	        value = isNaN(value) ? 0 : value;
	        if (value > 0) value--;

	        quantityAmount.value = value;
	    }
	    
	    init();
		
	};
	sitePlusMinus();
	function getCurrentUserId() {
		if (window.currentUserId) return window.currentUserId;
		const meta = document.querySelector('meta[name="current-user-id"]');
		return meta ? meta.getAttribute('content') : null;
	}

	function getUserStorageKey(key) {
		const userId = getCurrentUserId();
		if (userId && userId !== 'guest') {
			return `${key}_${userId}`;
		}
		// No authenticated user: use a per-browser guest id to avoid a single global key
		let guestId = localStorage.getItem('__guest_id');
		if (!guestId) {
			guestId = 'g_' + Math.random().toString(36).slice(2, 10);
			try { localStorage.setItem('__guest_id', guestId); } catch (e) { /* ignore */ }
		}
		return `${key}_guest_${guestId}`;
	}

	function getCartCount() {
		const cart = JSON.parse(localStorage.getItem(getUserStorageKey('cart')) || '[]');
		return cart.reduce((sum, item) => sum + (parseInt(item.quantity || item.qty || 1, 10) || 0), 0);
	}

	function updateCartBadge() {
		const cartBadge = document.getElementById('cartBadge');
		if (!cartBadge) return;
		cartBadge.textContent = getCartCount();
	}

	function getNotifications() {
		return JSON.parse(localStorage.getItem(getUserStorageKey('notifications')) || '[]');
	}

	function getUnreadNotificationCount(notifications) {
		return (notifications || []).filter(notif => !notif.read).length;
	}

	function markAllNotificationsRead() {
		const notifications = getNotifications();
		let updated = false;
		notifications.forEach(notif => {
			if (!notif.read) {
				notif.read = true;
				updated = true;
			}
		});
		if (updated) {
			localStorage.setItem(getUserStorageKey('notifications'), JSON.stringify(notifications));
		}
		updateNotificationBadgeCommon();
	}

	function addCommonNotification(notif) {
		const notifications = getNotifications();
		notif.read = notif.read === undefined ? false : notif.read;
		notifications.unshift(notif);
		if (notifications.length > 10) notifications.pop();
		localStorage.setItem(getUserStorageKey('notifications'), JSON.stringify(notifications));
		updateNotificationBadgeCommon();
	}

	function updateNotificationBadgeCommon() {
		const notifications = getNotifications();
		const unreadCount = getUnreadNotificationCount(notifications);
		const badge = document.getElementById('notifBadge');
		if (badge) badge.textContent = unreadCount > 0 ? unreadCount : '';

		const dropdown = document.getElementById('notificationItems');
		if (!dropdown) return;
		if (notifications.length === 0) {
			dropdown.innerHTML = '<li class="dropdown-item text-center text-muted">Tidak ada notifikasi</li>';
		} else {
			let html = '';
			notifications.forEach(notif => {
				const iconClass = notif.type === 'success' ? 'fa-check-circle text-success' : 'fa-info-circle text-info';
				const readClass = notif.read ? 'opacity-60' : 'font-weight-bold';
				html += `
					<li class="dropdown-item ${readClass}" style="border-bottom: 1px solid #eee; padding: 10px;">
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

	function getOrderStatusNotificationMessage(order) {
		const orderId = order.orderId || order.order_id || 'pesanan';
		if (order.paymentStatus === 'notdone' || order.paymentStatus === 'pending' || order.paymentStatus === 'cod') {
			return `Pesanan ${orderId} belum dibayar.`;
		}
		if (order.status === 'ready') {
			return `Pesanan ${orderId} siap dikirim.`;
		}
		if (order.status === 'delivered') {
			return `Pesanan ${orderId} telah dikirim.`;
		}
		if (order.status === 'completed') {
			return `Pesanan ${orderId} sudah sampai.`;
		}
		return null;
	}

	function generateOrderStatusNotifications() {
		const orders = JSON.parse(localStorage.getItem(getUserStorageKey('orders')) || '[]');
		const sent = JSON.parse(localStorage.getItem(getUserStorageKey('orderNotificationsSent')) || '{}');
		let updated = false;
		orders.forEach(order => {
			const message = getOrderStatusNotificationMessage(order);
			if (!message) return;
			const key = `${order.orderId || order.order_id}_${order.paymentStatus || order.payment_status}_${order.status || order.orderStatus}`;
			if (!sent[key]) {
				addCommonNotification({
					type: 'info',
					message,
					time: new Date().toLocaleString('id-ID')
				});
				sent[key] = true;
				updated = true;
			}
		});
		if (updated) {
			localStorage.setItem(getUserStorageKey('orderNotificationsSent'), JSON.stringify(sent));
		}
	}

	function updateChatBadge() {
		const chatBadge = document.getElementById('chatBadge');
		if (!chatBadge) return;
		fetch('../database/chat_api.php?action=fetch_unread_count', { cache: 'no-store' })
			.then(response => response.json())
			.then(data => {
				if (data && data.success) {
					chatBadge.textContent = data.unread;
				}
			})
			.catch(() => {
				// ignore network errors silently
			});
	}

	function updateCommonBadges() {
		updateCartBadge();
		updateNotificationBadgeCommon();
		generateOrderStatusNotifications();
		updateChatBadge();
	}

	window.updateCommonBadges = updateCommonBadges;
	window.addCommonNotification = addCommonNotification;

	// Expose individual updaters for compatibility with per-page scripts
	window.updateCartBadge = updateCartBadge;
	window.updateChatBadge = updateChatBadge;
	window.updateNotificationBadgeCommon = updateNotificationBadgeCommon;

	// Backwards-compatible aliases: some pages use these names
	window.addNotification = function(notif) {
		if (window.addCommonNotification) return window.addCommonNotification(notif);
		const notifications = JSON.parse(localStorage.getItem(getUserStorageKey('notifications')) || '[]');
		notifications.unshift(notif);
		if (notifications.length > 10) notifications.pop();
		localStorage.setItem(getUserStorageKey('notifications'), JSON.stringify(notifications));
		if (window.updateNotificationBadgeCommon) window.updateNotificationBadgeCommon();
	};

	window.updateNotificationBadge = function() {
		if (window.updateNotificationBadgeCommon) return window.updateNotificationBadgeCommon();
		const notifications = JSON.parse(localStorage.getItem(getUserStorageKey('notifications')) || '[]');
		const badge = document.getElementById('notifBadge');
		if (badge) badge.textContent = notifications.length;
	};

	document.addEventListener('show.bs.dropdown', function(e) {
		if (!e.target || !e.target.nextElementSibling) return;
		if (e.target.nextElementSibling.id === 'notificationDropdown') {
			markAllNotificationsRead();
		}
	});

	function migrateLegacyKeys() {
		try {
			const legacyKeys = ['appliedVoucher','cart','notifications','orders','orderNotificationsSent'];
			legacyKeys.forEach(k => {
				const val = localStorage.getItem(k);
				if (val !== null) {
					const target = getUserStorageKey(k);
					// if target empty, prefer writing to guest namespace
					if (!localStorage.getItem(target)) {
						localStorage.setItem(target, val);
					}
					localStorage.removeItem(k);
				}
			});
		} catch (e) {
			// ignore storage errors
		}
	}

	document.addEventListener('DOMContentLoaded', function() {
		migrateLegacyKeys();
		updateCommonBadges();
	});

	// Refresh chat count periodically and react to storage changes across tabs
	setInterval(updateChatBadge, 5000);
	window.addEventListener('storage', function(e) {
		if (!e.key) return;
		if (/^(cart|notifications|orders|orderNotificationsSent)(_.+)?$/.test(e.key)) {
			updateCommonBadges();
		}
	});

	// Poll cart and notifications as a fallback for pages that update localStorage
	setInterval(function() {
		try {
			updateCartBadge();
			updateNotificationBadgeCommon();
		} catch (err) {
			// ignore errors
		}
	}, 1500);
})()