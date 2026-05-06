<?php
session_start();
require '../../includes/session_check.php';
checkRole('mechanic');
require '../../includes/db_connect.php';

$mechanic_id = $_SESSION['user_id'];
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

// Fetch mechanic info
$mechanic_stmt = $pdo->prepare("SELECT first_name AS name, profile_pic FROM users WHERE id = ?");
$mechanic_stmt->execute([$mechanic_id]);
$mechanic = $mechanic_stmt->fetch(PDO::FETCH_ASSOC);

if (!$mechanic) {
    echo "Mechanic not found.";
    exit();
}

// If customer_id is 0, show customer selection
if ($customer_id === 0) {
    $customers_stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.first_name AS name, u.profile_pic 
        FROM users u
        INNER JOIN messages m ON (m.sender_id = u.id OR m.receiver_id = u.id)
        WHERE u.role = 'customer' 
          AND (m.sender_id = ? OR m.receiver_id = ?)
        ORDER BY u.first_name
    ");
    $customers_stmt->execute([$mechanic_id, $mechanic_id]);
    $customers = $customers_stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Messages - MotorService</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #f0f4ff, #e8eeff);
            color: #1e293b;
            animation: fadeIn 1s ease-in-out;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .container {
            width: 100%;
            max-width: 1200px;
            background: #ffffff;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 40px rgba(26, 86, 219, 0.12), 0 0 30px rgba(26, 86, 219, 0.08);
            border: 1px solid rgba(26, 86, 219, 0.2);
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        .header h1 {
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, #1a56db, #1e40af);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }
        .header p {
            color: #475569;
            font-size: 16px;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 30px;
            color: #1a56db;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s ease;
            padding: 10px 20px;
            background: rgba(26, 86, 219, 0.06);
            border-radius: 10px;
            border: 1px solid rgba(26, 86, 219, 0.2);
        }
        .back-link:hover {
            background: rgba(26, 86, 219, 0.12);
            transform: translateX(-5px);
        }
        .customer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .customer-card {
            background: #f8faff;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid rgba(26, 86, 219, 0.2);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            color: inherit;
        }
        .customer-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(26, 86, 219, 0.2);
            border-color: #1a56db;
            background: #eef2ff;
        }
        .customer-card img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #1a56db;
            box-shadow: 0 5px 15px rgba(26, 86, 219, 0.2);
        }
        .customer-card h3 {
            font-size: 18px;
            font-weight: 700;
            color: #1a56db;
            margin: 0;
        }
        .customer-card p {
            color: #475569;
            font-size: 13px;
            margin: 0;
        }
        .no-conversations {
            text-align: center;
            padding: 60px 20px;
            color: #475569;
        }
        .no-conversations i {
            font-size: 60px;
            color: rgba(26, 86, 219, 0.2);
            margin-bottom: 20px;
            display: block;
        }
        .no-conversations p {
            font-size: 18px;
            margin-bottom: 20px;
        }
        .no-conversations a {
            color: #1a56db;
            text-decoration: none;
            font-weight: 600;
        }
        @media (max-width: 768px) {
            .container { padding: 25px; border-radius: 15px; }
            .header h1 { font-size: 24px; }
            .customer-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; }
            .customer-card { padding: 20px; gap: 12px; }
            .customer-card img { width: 80px; height: 80px; border-width: 3px; }
            .customer-card h3 { font-size: 16px; }
        }
        @media (max-width: 480px) {
            .container { padding: 20px; }
            .header h1 { font-size: 20px; }
            .customer-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 12px; }
            .customer-card { padding: 15px; gap: 10px; }
            .customer-card img { width: 70px; height: 70px; }
            .customer-card h3 { font-size: 14px; }
        }
    </style>
    </head>
    <body>
        <div class="container">
            <a href="mechanic_dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            
            <div class="header">
                <h1><i class="fas fa-comments"></i> Messages</h1>
                <p>Select a customer to start chatting</p>
            </div>

            <?php if (empty($customers)): ?>
                <div class="no-conversations">
                    <i class="fas fa-inbox"></i>
                    <p>No conversations yet</p>
                    <p>Customers will message you when they book a service!</p>
                </div>
            <?php else: ?>
                <div class="customer-grid">
                    <?php foreach ($customers as $customer): ?>
                        <a href="?customer_id=<?= $customer['id'] ?>" class="customer-card">
                            <img src="../../uploads/profile_pics/<?= htmlspecialchars($customer['profile_pic'] ?? 'default.jpg'); ?>" alt="<?= htmlspecialchars($customer['name']); ?>">
                            <h3><?= htmlspecialchars($customer['name']); ?></h3>
                            <p>👤 Customer</p>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Fetch customer info
$customer_stmt = $pdo->prepare("SELECT first_name AS name, profile_pic FROM users WHERE id = ? AND role = 'customer'");
$customer_stmt->execute([$customer_id]);
$customer = $customer_stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    echo "Customer not found or not a customer.";
    exit();
}

// Send new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    header('Content-Type: application/json');
    
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $insert_stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, message_text, created_at, is_read)
            VALUES (?, ?, ?, NOW(), 0)
        ");
        $insert_stmt->execute([$mechanic_id, $customer_id, $message]);
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
    $mark_read_stmt->execute([$customer_id, $mechanic_id]);

    $chat_stmt = $pdo->prepare("
        SELECT m.sender_id, m.receiver_id, m.message_text, m.created_at, m.is_read, u.first_name AS name, u.profile_pic 
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    $chat_stmt->execute([$mechanic_id, $customer_id, $customer_id, $mechanic_id]);
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
<title>Chat with <?= htmlspecialchars($customer['name']); ?> - MotorService</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
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
        to { opacity: 1; transform: translateY(0); }
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
    
    .message.sent .message-wrapper { align-items: flex-end; }
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
        .header-info p { font-size: 13px; }
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
            <img src="../../uploads/profile_pics/<?= htmlspecialchars($customer['profile_pic'] ?? 'default.jpg'); ?>" alt="<?= htmlspecialchars($customer['name']); ?>">
            <div class="header-info">
                <h2><?= htmlspecialchars($customer['name']); ?></h2>
                <p>👤 Customer</p>
            </div>
        </div>
        <a href="select_customer.php" class="back-btn">
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

    let customerPic = '../../assets/img/default_profile.png';
    for (let i = 0; i < messages.length; i++) {
        if (messages[i].sender_id != <?= $mechanic_id ?> && messages[i].profile_pic) {
            customerPic = '../../uploads/profile_pics/' + messages[i].profile_pic;
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
                <img src="${customerPic}" alt="Seen" title="Seen by Customer" onerror="this.src='../../assets/img/default_profile.png'">
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
    fetch("?fetch_messages=true&customer_id=<?= $customer_id ?>")
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