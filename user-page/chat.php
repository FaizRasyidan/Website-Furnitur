<?php
session_start();
require_once __DIR__ . '/../database/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="current-user-id" content="<?php echo htmlspecialchars($userId); ?>">
    <title>Chat dengan Admin</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <style>
        body { background: #f4f7fb; }
        .chat-wrapper { max-width: 900px; margin: 40px auto; color: #333; }
        .chat-header { background: #142850; color: #fff; padding: 20px; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center; }
        .chat-header h1 { margin: 0; font-size: 1.25rem; }
        .chat-header .badge { background: #ff6b6b; font-size: .85rem; }
        .chat-body { background: #fff; border-radius: 0 0 12px 12px; padding: 20px; border: 1px solid #e5e7eb; }
        .messages-box { max-height: 520px; overflow-y: auto; margin-bottom: 20px; padding-right: 8px; }
        .message { display: flex; margin-bottom: 14px; }
        .message.admin .bubble { background: #f1f2f6; color: #222; border-radius: 18px 18px 18px 4px; }
        .message.user { justify-content: flex-end; }
        .message.user .bubble { background: #00b894; color: #fff; border-radius: 18px 18px 4px 18px; }
        .bubble { max-width: 75%; padding: 14px 16px; line-height: 1.6; box-shadow: 0 12px 24px rgba(0,0,0,.06); }
        .bubble small { display: block; color: rgba(255,255,255,.75); margin-top: 8px; font-size: .8rem; }
        .bubble.admin small { color: #666; }
        .chat-input { display: flex; gap: 10px; align-items: center; }
        .chat-input textarea { resize: none; height: 100px; flex: 1; border-radius: 12px; border: 1px solid #d1d5db; padding: 14px; }
        .chat-input button { min-width: 140px; }
        .toast-notification { position: fixed; right: 20px; bottom: 20px; background: #0b74de; color: #fff; padding: 14px 18px; border-radius: 10px; display: none; box-shadow: 0 15px 35px rgba(15,23,42,.2); }
        .nav-chat { margin-bottom: 25px; }
    </style>
</head>
<body>
    <div class="container chat-wrapper">
        <nav class="icon-btn">
            <a class="nav-link nav-icon-btn position-relative" href="user_page.php">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </a>
        </nav>

        <div class="chat-header">
            <div>
                <h1>Chat dengan Admin</h1>
                <p style="margin:0.25rem 0 0;color:#cbd5e1;">Kirim pesan langsung ke tim admin. Pesan akan muncul realtime.</p>
            </div>
            <span class="badge" id="unreadBadge">0 Pesan Baru</span>
        </div>
        <div class="chat-body">
            <div class="messages-box" id="chatBox"></div>
            <form id="chatForm" class="chat-input">
                <textarea id="chatInput" class="form-control" placeholder="Tulis pesan..." required></textarea>
                <button type="submit" class="btn btn-primary">Kirim</button>
            </form>
        </div>
    </div>
    <div class="toast-notification" id="toastNotification">Pesan baru diterima</div>

    <script src="../js/custom.js"></script>
    <script>
        const chatBox = document.getElementById('chatBox');
        const chatForm = document.getElementById('chatForm');
        const chatInput = document.getElementById('chatInput');
        const unreadBadge = document.getElementById('unreadBadge');
        const toast = document.getElementById('toastNotification');
        let lastUnread = 0;

        function formatTime(datetime) {
            const date = new Date(datetime);
            return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        }

        function renderMessages(messages) {
            chatBox.innerHTML = '';
            messages.forEach(msg => {
                const item = document.createElement('div');
                item.className = 'message ' + (msg.sender === 'admin' ? 'admin' : 'user');
                item.innerHTML = `
                    <div class="bubble ${msg.sender}">
                        ${msg.message.replace(/\n/g, '<br>')}
                        <small>${formatTime(msg.created_at)}</small>
                    </div>
                `;
                chatBox.appendChild(item);
            });
            chatBox.scrollTop = chatBox.scrollHeight;
        }

        function showToast(text) {
            toast.textContent = text;
            toast.style.display = 'block';
            setTimeout(() => toast.style.display = 'none', 2400);
        }

        async function loadMessages() {
            try {
                const res = await fetch('../database/chat_api.php?action=fetch_messages');
                const data = await res.json();
                if (!data.success) return;
                renderMessages(data.messages);
            } catch (error) {
                console.error(error);
            }
        }

        async function loadUnreadCount() {
            try {
                const res = await fetch('../database/chat_api.php?action=fetch_unread_count');
                const data = await res.json();
                if (!data.success) return;
                unreadBadge.textContent = `${data.unread} Pesan Baru`;
                if (data.unread > lastUnread) {
                    showToast('Pesan baru dari Admin!');
                }
                lastUnread = data.unread;
            } catch (error) {
                console.error(error);
            }
        }

        chatForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const message = chatInput.value.trim();
            if (!message) return;
            try {
                const formData = new FormData();
                formData.append('action', 'send_message');
                formData.append('message', message);
                const res = await fetch('../database/chat_api.php?action=send_message', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    chatInput.value = '';
                    loadMessages();
                    loadUnreadCount();
                }
            } catch (error) {
                console.error(error);
            }
        });

        window.addEventListener('load', () => {
            loadMessages();
            loadUnreadCount();
            setInterval(() => {
                loadMessages();
                loadUnreadCount();
            }, 4000);
        });
    </script>
</body>
</html>
