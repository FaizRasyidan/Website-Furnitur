<?php
session_start();
require_once __DIR__ . '/../database/config.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ../index.php');
    exit();
}
$adminName = $_SESSION['admin_name'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Chat</title>
    <link href='https://cdn.jsdelivr.net/npm/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .chat-grid { display: grid; grid-template-columns: 320px 1fr; gap: 24px; padding: 24px; }
        .chat-panel, .thread-panel { background: #fff; border-radius: 18px; box-shadow: 0 18px 45px rgba(15,23,42,.08); }
        .thread-panel { overflow: hidden; }
        .thread-header, .panel-header { padding: 20px; border-bottom: 1px solid #edf2f7; }
        .thread-list { max-height: 640px; overflow-y: auto; }
        .thread-item { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid #f1f5f9; cursor: pointer; }
        .thread-item.active { background: #e8f4ff; }
        .thread-item h4 { margin: 0 0 4px; font-size: 1rem; }
        .thread-item small { color: #718096; }
        .thread-item .badge { background: #ff6b6b; color: #fff; font-size: .75rem; padding: 6px 10px; border-radius: 999px; }
        .chat-box { min-height: 520px; max-height: 520px; overflow-y: auto; padding: 20px; }
        .message-item { margin-bottom: 14px; display: flex; }
        .message-item.admin { justify-content: flex-start; }
        .message-item.user { justify-content: flex-end; }
        .message-content { max-width: 72%; padding: 16px 18px; border-radius: 18px; background: #f1f5f9; position: relative; }
        .message-content.user { background: #142850; color: #fff; }
        .message-content small { display: block; margin-top: 8px; color: rgba(0,0,0,.55); font-size: .8rem; }
        .message-content.user small { color: rgba(255,255,255,.7); }
        .chat-actions { padding: 20px; border-top: 1px solid #edf2f7; display: flex; gap: 12px; align-items: center; }
        .chat-actions textarea { width: 100%; min-height: 100px; border-radius: 16px; border: 1px solid #cbd5e0; padding: 14px; }
        .chat-actions button { min-width: 150px; }
        .sidebar-link.active { background: rgba(0,0,0,.05); }
        .thread-meta { color: #718096; font-size: .85rem; }
    </style>
</head>
<body>
    <!-- SIDEBAR / NAVBAR -->
    <nav class="sidebar">
        <div class="logo">
            <i class='bx bxs-couch'></i>
            <span>Offo Living</span>
        </div>

        <div class="menu-section">
            <div class="menu-title">Menu Utama</div>
            <ul class="nav-links">
                <li class="nav-item">
                    <a class="nav-link" onclick="toggleSubmenu(this)" href="admin_page.php">
                        <i class='bx bxs-home'></i>
                        <span>Beranda</span>
                    </a>
                <!-- Menu Toko with Submenu -->
                <li class="nav-item">
                    <a class="nav-link" onclick="toggleSubmenu(this)">
                        <i class='bx bxs-store'></i>
                        <span>Toko</span>
                        <i class='bx bxs-chevron-down arrow'></i>
                    </a>
                    <ul class="submenu">
                        <li><a href="laporan_pemasukan.php">Laporan Pemasukan</a></li>
                        <li><a href="data_pesanan.php">Data Pesanan</a></li>
                        <li><a href="data_produk.php">Data Produk</a></li>
                        <li><a href="voucher.php">Voucher</a></li>
                        <li><a href="pengiriman.php">Pengiriman</a></li>
                    </ul>
                </li>

                <!-- Menu Blog with Submenu -->
                <li class="nav-item">
                    <a class="nav-link" onclick="toggleSubmenu(this)">
                        <i class='bx bxs-edit'></i>
                        <span>Blog</span>
                        <i class='bx bxs-chevron-down arrow'></i>
                    </a>
                    <ul class="submenu">
                        <li><a href="kategori_produk.php">Kategori Produk</a></li>
                        <li><a href="store_locations.php">Lokasi Toko</a></li>
                        <li><a href="pelanggan.php">Pelanggan</a></li>
                    </ul>
                </li>

                <!-- Menu Chat -->
                <li class="nav-item">
                    <a class="nav-link" href="chat.php">
                        <i class='bx bxs-message-rounded-dots'></i>
                        <span>Chat</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="../database/logout.php">
                        <i class='bx bxs-log-out'></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>


    <div class="main-content">
        <header>
            <div class="header-title">Chat Admin</div>
            <div class="user-profile">
                <div>
                    <span style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($adminName); ?></span><br>
                    <span style="font-size: 12px; color: #888;">Admin</span>
                </div>
                <div class="avatar"><i class='bx bxs-user'></i></div>
            </div>
        </header>
        <div class="chat-grid">
            <div class="thread-panel">
                <div class="thread-header">
                    <h3>Daftar Chat</h3>
                    <p style="color:#67748a; margin:8px 0 0;">Pilih user untuk melihat pesan.</p>
                </div>
                <div class="thread-list" id="threadList"></div>
            </div>
            <div class="chat-panel">
                <div class="panel-header">
                    <h3 id="chatTitle">Pilih user dari daftar</h3>
                    <p class="thread-meta" id="chatMeta">Menunggu pilihan...</p>
                </div>
                <div class="chat-box" id="chatBox"></div>
                <div class="chat-actions">
                    <textarea id="chatMessage" placeholder="Tulis pesan untuk user..." disabled></textarea>
                    <button id="sendBtn" class="btn btn-primary" disabled>Kirim</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/dashboard.js"></script>
    <script>
        let currentUserId = null;
        let threads = [];
        const threadList = document.getElementById('threadList');
        const chatBox = document.getElementById('chatBox');
        const chatTitle = document.getElementById('chatTitle');
        const chatMeta = document.getElementById('chatMeta');
        const chatMessage = document.getElementById('chatMessage');
        const sendBtn = document.getElementById('sendBtn');

        function formatTime(datetime) {
            const date = new Date(datetime);
            return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        }

        function renderThreads(list) {
            threadList.innerHTML = '';
            list.forEach(thread => {
                const item = document.createElement('div');
                item.className = 'thread-item ' + (thread.user_id === currentUserId ? 'active' : '');
                item.innerHTML = `
                    <div>
                        <h4>${thread.name}</h4>
                        <small>${thread.email}</small>
                    </div>
                    <div style="text-align:right;">
                        ${thread.unread_count > 0 ? `<span class="badge">${thread.unread_count}</span>` : ''}
                        <small style="display:block; color:#718096; margin-top:6px;">${thread.last_time ? formatTime(thread.last_time) : ''}</small>
                    </div>
                `;
                item.addEventListener('click', () => selectThread(thread.user_id, thread.name, thread.email));
                threadList.appendChild(item);
            });
        }

        function renderMessages(messages) {
            chatBox.innerHTML = '';
            messages.forEach(msg => {
                const item = document.createElement('div');
                item.className = 'message-item ' + (msg.sender === 'admin' ? 'admin' : 'user');
                item.innerHTML = `
                    <div class="message-content ${msg.sender === 'admin' ? '' : 'user'}">
                        ${msg.message.replace(/\n/g, '<br>')}
                        <small>${formatTime(msg.created_at)}</small>
                    </div>
                `;
                chatBox.appendChild(item);
            });
            chatBox.scrollTop = chatBox.scrollHeight;
        }

        async function fetchThreads() {
            const res = await fetch('../database/chat_api.php?action=fetch_threads');
            const data = await res.json();
            if (!data.success) return;
            threads = data.threads;
            renderThreads(threads);
        }

        async function fetchConversation(userId) {
            if (!userId) return;
            const res = await fetch(`../database/chat_api.php?action=fetch_messages&user_id=${userId}`);
            const data = await res.json();
            if (!data.success) return;
            renderMessages(data.messages);
        }

        function selectThread(userId, name, email) {
            currentUserId = userId;
            chatTitle.textContent = `Chat dengan ${name}`;
            chatMeta.textContent = `Email: ${email}`;
            chatMessage.disabled = false;
            sendBtn.disabled = false;
            fetchConversation(userId);
            renderThreads(threads);
        }

        sendBtn.addEventListener('click', async () => {
            if (!currentUserId) return;
            const message = chatMessage.value.trim();
            if (!message) return;
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('user_id', currentUserId);
            formData.append('message', message);
            const res = await fetch('../database/chat_api.php?action=send_message', { method: 'POST', body: formData });
            const data = await res.json();
            if (!data.success) return;
            chatMessage.value = '';
            await fetchConversation(currentUserId);
            await fetchThreads();
        });

        window.addEventListener('load', async () => {
            await fetchThreads();
            setInterval(async () => {
                await fetchThreads();
                if (currentUserId) {
                    await fetchConversation(currentUserId);
                }
            }, 4000);
        });
    </script>
</body>
</html>
