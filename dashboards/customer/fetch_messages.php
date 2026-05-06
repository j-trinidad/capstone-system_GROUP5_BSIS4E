<?php
require '../../includes/session_check.php';
checkRole('customer');
require '../../includes/db_connect.php';

$customer_id = $_SESSION['user_id'];
$mechanic_id = isset($_GET['mechanic_id']) ? (int)$_GET['mechanic_id'] : 0;

if ($mechanic_id === 0) {
    echo "No mechanic selected.";
    exit();
}

// Fetch customer info using PDO
$stmt = $pdo->prepare("SELECT first_name AS name, email, profile_pic FROM users WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$customer) { 
    echo "Customer not found."; 
    exit(); 
}

// Fetch mechanic info using PDO
$stmt = $pdo->prepare("SELECT first_name AS name, email, profile_pic FROM users WHERE id = ? AND role='mechanic'");
$stmt->execute([$mechanic_id]);
$mechanic = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$mechanic) { 
    echo "Mechanic not found."; 
    exit(); 
}

// Send new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$customer_id, $mechanic_id, $message]);
        // Redirect to avoid resubmission
        header("Location: customer_messages.php?mechanic_id=$mechanic_id");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chat with <?= htmlspecialchars($mechanic['name']); ?> - MotorService</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #ff8c00;
    --secondary: #e52e71;
    --dark-bg: #0a0e27;
    --card-bg: #1a1f3a;
    --border: rgba(255, 140, 0, 0.2);
    --text-primary: #fff;
    --text-secondary: #b0b8d4;
    --success: #00d084;
    --error: #ff4757;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html, body {
    height: 100%;
    font-family: 'Outfit', sans-serif;
    background: linear-gradient(135deg, var(--dark-bg), #1a1f3a);
    color: var(--text-primary);
    overflow: hidden;
}

a {
    color: inherit;
    text-decoration: none;
}

/* HEADER */
.header {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #1a1f3a;
    padding: 15px 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    position: relative;
    box-shadow: 0 4px 15px rgba(255, 140, 0, 0.3);
}

.back-btn {
    color: #1a1f3a;
    font-size: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.back-btn:hover {
    opacity: 0.8;
}

.header-info {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.mechanic-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
}

.mechanic-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.mechanic-name h2 {
    font-size: 16px;
    margin: 0;
    font-weight: 700;
}

.mechanic-name p {
    font-size: 12px;
    margin: 2px 0 0 0;
    opacity: 0.9;
}

/* CHAT BOX */
.chat-box {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
    background: var(--dark-bg);
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.chat-box::-webkit-scrollbar {
    width: 6px;
}

.chat-box::-webkit-scrollbar-track {
    background: transparent;
}

.chat-box::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 3px;
}

.message {
    display: flex;
    align-items: flex-end;
    gap: 8px;
}

.message img {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}

.message-content {
    max-width: 70%;
    padding: 10px 14px;
    border-radius: 16px;
    font-size: 14px;
    line-height: 1.4;
    word-wrap: break-word;
}

/* CUSTOMER MESSAGE (RIGHT) */
.message.customer {
    justify-content: flex-end;
}

.message.customer .message-content {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #1a1f3a;
    border-bottom-right-radius: 4px;
}

.message.customer img {
    order: 2;
}

/* MECHANIC MESSAGE (LEFT) */
.message.mechanic {
    justify-content: flex-start;
}

.message.mechanic .message-content {
    background: var(--card-bg);
    color: var(--text-primary);
    border: 1px solid var(--border);
    border-bottom-left-radius: 4px;
}

.message-time {
    font-size: 11px;
    color: var(--text-secondary);
    margin-top: 3px;
    padding: 0 14px;
}

/* FORM */
.input-area {
    background: var(--card-bg);
    padding: 12px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 8px;
    align-items: flex-end;
}

textarea {
    flex: 1;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border);
    border-radius: 16px;
    color: var(--text-primary);
    padding: 10px 14px;
    font-size: 14px;
    font-family: 'Outfit', sans-serif;
    resize: none;
    min-height: 44px;
    max-height: 120px;
    transition: all 0.3s ease;
}

textarea:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(255, 140, 0, 0.1);
    box-shadow: 0 0 10px rgba(255, 140, 0, 0.2);
}

textarea::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.send-btn {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #1a1f3a;
    border: none;
    cursor: pointer;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(255, 140, 0, 0.3);
}

.send-btn:hover {
    transform: scale(1.08);
    box-shadow: 0 6px 16px rgba(255, 140, 0, 0.4);
}

.send-btn:active {
    transform: scale(0.96);
}

/* MAIN CONTAINER */
.chat-container {
    display: flex;
    flex-direction: column;
    height: 100vh;
}

/* MOBILE RESPONSIVE */
@media (max-width: 768px) {
    html, body {
        height: 100dvh;
        overflow: hidden;
    }

    .chat-container {
        height: 100dvh;
    }

    .header {
        padding: 12px;
        gap: 10px;
    }

    .back-btn {
        font-size: 18px;
    }

    .mechanic-avatar {
        width: 40px;
        height: 40px;
    }

    .mechanic-name h2 {
        font-size: 14px;
    }

    .mechanic-name p {
        font-size: 11px;
    }

    .chat-box {
        padding: 10px;
    }

    .message-content {
        max-width: 75%;
        font-size: 13px;
        padding: 8px 12px;
    }

    .message-time {
        font-size: 10px;
    }

    .input-area {
        padding: 10px;
        gap: 6px;
    }

    textarea {
        padding: 8px 12px;
        font-size: 13px;
        min-height: 40px;
    }

    .send-btn {
        width: 40px;
        height: 40px;
        font-size: 14px;
    }
}
</style>
</head>
<body>

<div class="chat-container">
    <!-- HEADER -->
    <div class="header">
        <a href="customer_dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="header-info">
            <div class="mechanic-avatar">
                <?php if ($mechanic['profile_pic']): ?>
                    <img src="../../uploads/profile_pics/<?= htmlspecialchars($mechanic['profile_pic']) ?>" alt="<?= htmlspecialchars($mechanic['name']) ?>">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
            </div>
            <div class="mechanic-name">
                <h2><?= htmlspecialchars($mechanic['name']); ?></h2>
                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($mechanic['email']); ?></p>
            </div>
        </div>
    </div>

    <!-- CHAT BOX -->
    <div class="chat-box" id="chat-box">
        <!-- Messages will be loaded here via AJAX -->
    </div>

    <!-- INPUT AREA -->
    <div class="input-area">
        <form id="chat-form" style="display: flex; gap: 8px; width: 100%; align-items: flex-end;">
            <textarea name="message" id="messageInput" placeholder="Type your message..." required></textarea>
            <button type="submit" class="send-btn" title="Send message">
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>
    </div>
</div>

<script>
const chatBox = document.getElementById('chat-box');
const form = document.getElementById('chat-form');
const messageInput = document.getElementById('messageInput');
const mechanicId = <?= $mechanic_id ?>;
const customerId = <?= $customer_id ?>;
const customerName = "<?= htmlspecialchars($customer['name']) ?>";

// Fetch messages
function loadMessages() {
    fetch(`fetch_messages.php?mechanic_id=${mechanicId}`)
        .then(res => res.json())
        .then(data => {
            chatBox.innerHTML = '';
            data.forEach(msg => {
                const messageDiv = document.createElement('div');
                messageDiv.classList.add('message');
                messageDiv.classList.add(msg.sender_id == customerId ? 'customer' : 'mechanic');
                
                const contentDiv = document.createElement('div');
                contentDiv.classList.add('message-content');
                contentDiv.textContent = msg.message_text;
                
                const timeDiv = document.createElement('div');
                timeDiv.classList.add('message-time');
                timeDiv.textContent = formatTime(msg.created_at);
                
                // Add avatar only for mechanic messages
                if (msg.sender_id != customerId) {
                    const imgDiv = document.createElement('img');
                    if (msg.profile_pic) {
                        imgDiv.src = `../../uploads/profile_pics/${msg.profile_pic}`;
                    } else {
                        imgDiv.src = '../../assets/img/default_profile.png';
                    }
                    messageDiv.appendChild(imgDiv);
                }
                
                const wrapperDiv = document.createElement('div');
                wrapperDiv.style.width = '100%';
                wrapperDiv.appendChild(contentDiv);
                wrapperDiv.appendChild(timeDiv);
                
                messageDiv.appendChild(wrapperDiv);
                
                if (msg.sender_id == customerId) {
                    const imgDiv = document.createElement('img');
                    if (msg.profile_pic) {
                        imgDiv.src = `../../uploads/profile_pics/${msg.profile_pic}`;
                    } else {
                        imgDiv.src = '../../assets/img/default_profile.png';
                    }
                    messageDiv.appendChild(imgDiv);
                }
                
                chatBox.appendChild(messageDiv);
            });
            chatBox.scrollTop = chatBox.scrollHeight;
        })
        .catch(err => console.error('Error loading messages:', err));
}

// Format time
function formatTime(dateString) {
    const date = new Date(dateString);
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    
    if (date.toDateString() === today.toDateString()) {
        return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    } else if (date.toDateString() === yesterday.toDateString()) {
        return 'Yesterday ' + date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    } else {
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }
}

// Submit message without reload
form.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(form);
    fetch(`customer_messages.php?mechanic_id=${mechanicId}`, {
        method: 'POST',
        body: formData
    })
    .then(() => {
        messageInput.value = '';
        messageInput.focus();
        loadMessages();
    })
    .catch(err => console.error('Error sending message:', err));
});

// Auto-grow textarea
messageInput.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

// Refresh every 2 seconds
setInterval(loadMessages, 2000);
loadMessages();
</script>

</body>
</html>