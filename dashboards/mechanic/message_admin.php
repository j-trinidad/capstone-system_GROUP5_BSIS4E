<?php
session_start();
require '../../includes/session_check.php';
checkRole('mechanic');
require '../../includes/db_connect.php';

$mechanic_id = $_SESSION['user_id'];

// Fetch mechanic info
$mechanic_stmt = $pdo->prepare("SELECT first_name AS name, profile_pic FROM users WHERE id = ?");
$mechanic_stmt->execute([$mechanic_id]);
$mechanic = $mechanic_stmt->fetch(PDO::FETCH_ASSOC);

// Get admin info
$admin_stmt = $pdo->prepare("SELECT id, first_name AS name, profile_pic FROM users WHERE role = 'admin' LIMIT 1");
$admin_stmt->execute();
$admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    echo "<p style='text-align:center;color:#ccc;margin-top:50px;'>No admin account found.</p>";
    exit();
}

$admin_id = $admin['id'];

// Send new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    header('Content-Type: application/json');
    
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $insert_stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, message_text, created_at, is_read)
            VALUES (?, ?, ?, NOW(), 0)
        ");
        $insert_stmt->execute([$mechanic_id, $admin_id, $message]);
        echo json_encode(['status' => 'success']);
        exit();
    }
    echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty']);
    exit();
}

// Fetch chat history (AJAX)
if (isset($_GET['fetch_messages']) && $_GET['fetch_messages'] == 'true') {
    header('Content-Type: application/json');
    
    $mark_read_stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    $mark_read_stmt->execute([$admin_id, $mechanic_id]);

    $chat_stmt = $pdo->prepare("
        SELECT m.sender_id, m.receiver_id, m.message_text, m.created_at, m.is_read, u.first_name AS name, u.profile_pic 
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    $chat_stmt->execute([$mechanic_id, $admin_id, $admin_id, $mechanic_id]);
    $messages = $chat_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($messages ?: []);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Chat with Admin - MotorService</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        -webkit-tap-highlight-color: transparent;
    }

    html {
        height: 100%;
        overflow: hidden;
    }

    body {
        font-family: 'Outfit', sans-serif;
        background: #f0f4ff;
        color: #1e293b;
        height: 100%;
        overflow: hidden;
        position: relative;
    }

    .chat-wrapper {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        display: flex;
        flex-direction: column;
        background: #f0f4ff;
        max-height: 100vh;
        max-height: 100dvh;
    }

    .chat-header {
        background: linear-gradient(135deg, #1a56db, #1e40af);
        padding: 15px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 2px 12px rgba(26, 86, 219, 0.25);
        z-index: 10;
        flex-shrink: 0;
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 1;
        min-width: 0;
    }

    .header-left img {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(255, 255, 255, 0.8);
        flex-shrink: 0;
    }

    .header-info {
        min-width: 0;
        flex: 1;
    }

    .header-info h2 {
        margin: 0;
        font-size: 16px;
        color: #fff;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-weight: 700;
    }

    .header-info p {
        margin: 2px 0 0 0;
        font-size: 11px;
        color: rgba(255, 255, 255, 0.9);
    }

    .back-btn {
        color: #fff;
        text-decoration: none;
        font-size: 20px;
        padding: 8px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 36px;
        height: 36px;
        transition: background 0.2s;
    }

    .back-btn:hover { background: rgba(255, 255, 255, 0.3); }

    .chat-messages {
        flex: 1 1 auto;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 15px;
        padding-bottom: 80px;
        background: #f0f4ff;
        display: flex;
        flex-direction: column;
        gap: 12px;
        -webkit-overflow-scrolling: touch;
        min-height: 0;
    }

    .chat-messages::-webkit-scrollbar { width: 4px; }
    .chat-messages::-webkit-scrollbar-thumb { background: rgba(26, 86, 219, 0.2); border-radius: 4px; }

    .message {
        display: flex;
        gap: 10px;
        align-items: flex-end;
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from { opacity: 0; transform: translateY(10px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .message.sent {
        align-self: flex-end;
        flex-direction: row-reverse;
        max-width: 80%;
    }

    .message.received {
        align-self: flex-start;
        max-width: 80%;
    }

    .message img {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(26, 86, 219, 0.3);
        flex-shrink: 0;
    }

    .message-wrapper {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .message.sent .message-wrapper     { align-items: flex-end; }
    .message.received .message-wrapper { align-items: flex-start; }

    .message-content {
        padding: 10px 14px;
        border-radius: 12px;
        word-wrap: break-word;
        line-height: 1.4;
        font-size: 14px;
        box-shadow: 0 2px 8px rgba(26, 86, 219, 0.08);
    }

    .message.sent .message-content {
        background: linear-gradient(135deg, #1a56db, #1e40af);
        color: #fff;
        border-bottom-right-radius: 4px;
    }

    .message.received .message-content {
        background: #ffffff;
        color: #1e293b;
        border-bottom-left-radius: 4px;
        border: 1px solid rgba(26, 86, 219, 0.12);
    }

    .message-time {
        font-size: 11px;
        color: #94a3b8;
        padding: 0 4px;
    }

    /* ── Seen indicator ── */
    .seen-indicator {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 5px;
        padding-right: 6px;
        margin-top: -4px;
        margin-bottom: 2px;
        align-self: flex-end;
    }

    .seen-indicator img {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        object-fit: cover;
        border: 1.5px solid rgba(26, 86, 219, 0.4);
        opacity: 0.9;
    }

    .seen-indicator span {
        font-size: 10px;
        color: #94a3b8;
        font-style: italic;
    }

    .loading {
        text-align: center;
        color: #94a3b8;
        font-style: italic;
        padding: 40px 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
    }

    .chat-input-wrapper {
        background: #ffffff;
        padding: 12px 15px;
        padding-bottom: max(12px, env(safe-area-inset-bottom));
        display: flex;
        gap: 10px;
        align-items: flex-end;
        border-top: 1px solid rgba(26, 86, 219, 0.15);
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 1000;
        box-shadow: 0 -4px 15px rgba(26, 86, 219, 0.08);
    }

    .input-container {
        flex: 1;
        display: flex;
        align-items: center;
        background: #f0f4ff;
        border: 2px solid rgba(26, 86, 219, 0.2);
        border-radius: 20px;
        padding: 0 15px;
        transition: all 0.3s ease;
    }

    .input-container:focus-within {
        border-color: #1a56db;
        background: #e8eeff;
        box-shadow: 0 0 8px rgba(26, 86, 219, 0.15);
    }

    textarea {
        flex: 1;
        min-height: 38px;
        max-height: 100px;
        background: transparent;
        border: none;
        color: #1e293b;
        padding: 10px 0;
        resize: none;
        font-family: 'Outfit', sans-serif;
        font-size: 14px;
        outline: none;
    }

    textarea::placeholder { color: #94a3b8; }

    .send-btn {
        background: linear-gradient(135deg, #1a56db, #1e40af);
        color: #fff;
        border: none;
        border-radius: 50%;
        width: 44px;
        height: 44px;
        cursor: pointer;
        font-size: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 3px 10px rgba(26, 86, 219, 0.3);
        flex-shrink: 0;
        transition: all 0.2s ease;
    }

    .send-btn:hover  { box-shadow: 0 5px 15px rgba(26, 86, 219, 0.4); transform: translateY(-1px); }
    .send-btn:active { transform: scale(0.95); }

    @media (min-width: 768px) {
        .chat-header { padding: 20px 30px; }
        .header-left img { width: 55px; height: 55px; }
        .header-info h2 { font-size: 20px; }
        .header-info p  { font-size: 13px; }
        .back-btn {
            width: auto;
            padding: 8px 16px;
            gap: 8px;
        }
        .back-btn::after {
            content: 'Back';
            font-size: 14px;
            font-weight: 600;
        }
        .chat-messages { padding: 25px 30px; padding-bottom: 100px; gap: 15px; }
        .message.sent, .message.received { max-width: 65%; }
        .message img { width: 40px; height: 40px; }
        .message-content { padding: 12px 16px; font-size: 15px; }
        .chat-input-wrapper { padding: 20px 30px; gap: 12px; }
        textarea { min-height: 44px; font-size: 15px; }
        .send-btn { width: 50px; height: 50px; font-size: 18px; }
    }
</style>
</head>
<body>

<div class="chat-wrapper">
    <div class="chat-header">
        <div class="header-left">
            <img src="../../uploads/profile_pics/<?= htmlspecialchars($admin['profile_pic'] ?? 'default.jpg'); ?>" alt="<?= htmlspecialchars($admin['name']); ?>">
            <div class="header-info">
                <h2><?= htmlspecialchars($admin['name']); ?></h2>
                <p>👨‍💼 Administrator</p>
            </div>
        </div>
        <a href="message_center.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
        </a>
    </div>

    <div class="chat-messages" id="chat-box">
        <div class="loading">Loading messages...</div>
    </div>

    <form class="chat-input-wrapper" id="message-form">
        <div class="input-container">
            <textarea name="message" placeholder="Type a message..." required rows="1"></textarea>
        </div>
        <button type="submit" class="send-btn"><i class="fas fa-paper-plane"></i></button>
    </form>
</div>

<script>
function formatMessageTime(createdAt) {
    const now = new Date();
    const messageDate = new Date(createdAt);
    const diffTime = now - messageDate;
    const diffMinutes = Math.floor(diffTime / (1000 * 60));
    const diffHours = Math.floor(diffTime / (1000 * 60 * 60));
    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

    if (diffMinutes < 1) return 'Just now';
    if (diffMinutes < 60) return diffMinutes + 'm ago';
    if (diffHours < 24) return messageDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7) return messageDate.toLocaleDateString([], { weekday: 'long' });
    return messageDate.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

let lastMessageSignature = '';

function buildSignature(messages) {
    if (!messages.length) return '';
    const last = messages[messages.length - 1];
    const seenCount = messages.filter(m => m.sender_id == <?= $mechanic_id ?> && parseInt(m.is_read) === 1).length;
    return messages.length + '_' + last.created_at + '_' + last.sender_id + '_seen' + seenCount;
}

function renderMessages(messages) {
    const chatBox = document.getElementById('chat-box');

    if (!Array.isArray(messages) || messages.length === 0) {
        chatBox.innerHTML = '<div class="loading">No messages yet. Start the conversation!</div>';
        return;
    }

    const isAtBottom = chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < 60;

    let lastSeenIdx = -1;
    for (let i = messages.length - 1; i >= 0; i--) {
        if (messages[i].sender_id == <?= $mechanic_id ?> && parseInt(messages[i].is_read) === 1) {
            lastSeenIdx = i;
            break;
        }
    }

    let adminPic = '../../assets/img/default_profile.png';
    for (let i = 0; i < messages.length; i++) {
        if (messages[i].sender_id != <?= $mechanic_id ?> && messages[i].profile_pic) {
            adminPic = '../../uploads/profile_pics/' + messages[i].profile_pic;
            break;
        }
    }

    const sig = buildSignature(messages);
    if (sig === lastMessageSignature) return;
    lastMessageSignature = sig;

    let html = '';
    messages.forEach((message, idx) => {
        const isMechanic = message.sender_id == <?= $mechanic_id ?>;
        const profilePic = message.profile_pic
            ? '../../uploads/profile_pics/' + message.profile_pic
            : '../../assets/img/default_profile.png';

        html += `
            <div class="message ${isMechanic ? 'sent' : 'received'}">
                <img src="${profilePic}" alt="Profile" onerror="this.src='../../assets/img/default_profile.png'">
                <div class="message-wrapper">
                    <div class="message-content">${escapeHtml(message.message_text)}</div>
                    <div class="message-time">${formatMessageTime(message.created_at)}</div>
                </div>
            </div>`;

        if (isMechanic && idx === lastSeenIdx) {
            html += `
            <div class="seen-indicator">
                <img src="${adminPic}" alt="Seen" title="Seen by Admin" onerror="this.src='../../assets/img/default_profile.png'">
                <span>Seen</span>
            </div>`;
        }
    });

    chatBox.innerHTML = html;

    if (isAtBottom) {
        setTimeout(() => { chatBox.scrollTop = chatBox.scrollHeight; }, 0);
    }
}

function fetchMessages() {
    fetch("?fetch_messages=true")
        .then(response => {
            if (!response.ok) throw new Error('Network error');
            return response.json();
        })
        .then(messages => renderMessages(messages))
        .catch(error => console.error('Error fetching messages:', error));
}

document.getElementById('message-form').addEventListener('submit', function (e) {
    e.preventDefault();
    const textarea = document.querySelector('textarea[name="message"]');
    const message = textarea.value.trim();

    if (message === '') return;

    textarea.value = '';
    textarea.style.height = 'auto';

    fetch('', {
        method: 'POST',
        body: new URLSearchParams({ 'message': message })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            lastMessageSignature = '';
            fetchMessages();
        }
    })
    .catch(error => console.error('Error sending message:', error));
});

fetchMessages();
setInterval(fetchMessages, 3000);

const textarea = document.querySelector('textarea');
textarea.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 100) + 'px';
});

let lastTouchEnd = 0;
document.addEventListener('touchend', function (event) {
    const now = (new Date()).getTime();
    if (now - lastTouchEnd <= 300) event.preventDefault();
    lastTouchEnd = now;
}, false);

if ('visualViewport' in window) {
    window.visualViewport.addEventListener('resize', () => {
        const chatBox = document.getElementById('chat-box');
        setTimeout(() => { chatBox.scrollTop = chatBox.scrollHeight; }, 100);
    });
}
</script>

</body>
</html>