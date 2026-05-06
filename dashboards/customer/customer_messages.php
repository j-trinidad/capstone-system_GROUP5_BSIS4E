<?php
session_start();
require '../../includes/session_check.php';
checkRole('customer');
require '../../includes/db_connect.php';

$user_id = $_SESSION['user_id'];

// MARK ALL RECEIVED MESSAGES AS READ
$stmt = $pdo->prepare("
    UPDATE messages 
    SET is_read = 1 
    WHERE receiver_id = ? AND is_read = 0
");
$stmt->execute([$user_id]);

$customer_id = $_SESSION['user_id'];
$mechanic_id = isset($_GET['mechanic_id']) ? (int)$_GET['mechanic_id'] : 0;

// Get counts for badges
$notifStmt = $pdo->prepare("SELECT COUNT(*) FROM customer_notifications WHERE customer_id = ? AND is_read = 0");
$notifStmt->execute([$customer_id]);
$unreadNotifications = $notifStmt->fetchColumn();

$msgStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$msgStmt->execute([$customer_id]);
$messages = $msgStmt->fetchColumn();

// Fetch customer info
$customer_stmt = $pdo->prepare("SELECT first_name AS name, profile_pic FROM users WHERE id = ?");
$customer_stmt->execute([$customer_id]);
$customer = $customer_stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    echo "Customer not found.";
    exit();
}

// If mechanic_id is 0, show mechanic selection with sidebar
if ($mechanic_id === 0) {
    $mechanics_stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.first_name AS name, u.profile_pic 
        FROM users u
        INNER JOIN messages m ON (m.sender_id = u.id OR m.receiver_id = u.id)
        WHERE u.role = 'mechanic' 
          AND (m.sender_id = ? OR m.receiver_id = ?)
        ORDER BY u.first_name
    ");
    $mechanics_stmt->execute([$customer_id, $customer_id]);
    $mechanics = $mechanics_stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Messages - MotorService</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
/* ── ROOT: exact match to customer_dashboard.php ───────────────────── */
:root {
    --primary:        #1a56db;
    --secondary:      #1e40af;
    --dark-bg:        #f0f4ff;
    --card-bg:        #ffffff;
    --border:         rgba(26, 86, 219, 0.2);
    --text-primary:   #1e293b;
    --text-secondary: #475569;
    --success:        #059669;
    --error:          #dc2626;
    --warning:        #d97706;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
html, body {
    height: 100%;
    font-family: 'Outfit', sans-serif;
    background: linear-gradient(135deg, #f0f4ff, #e8eeff);
    color: var(--text-primary);
    overflow: hidden;
}
a { color: inherit; text-decoration: none; }

/* ── HAMBURGER ──────────────────────────────────────────────────────── */
.hamburger-btn {
    display: none;
    position: fixed; top: 20px; left: 20px; z-index: 1001;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border: none; width: 50px; height: 50px; border-radius: 12px;
    cursor: pointer; box-shadow: 0 4px 15px rgba(26,86,219,0.4);
    transition: all 0.3s ease;
}
.hamburger-btn:hover { transform: scale(1.05); box-shadow: 0 6px 20px rgba(26,86,219,0.6); }
.hamburger-btn span {
    display: block; width: 25px; height: 3px;
    background: #ffffff; margin: 5px auto;
    border-radius: 2px; transition: all 0.3s ease;
}
.hamburger-btn.active span:nth-child(1) { transform: rotate(45deg) translate(8px,8px); }
.hamburger-btn.active span:nth-child(2) { opacity: 0; }
.hamburger-btn.active span:nth-child(3) { transform: rotate(-45deg) translate(7px,-7px); }

/* ── NOTIFICATION BELL ──────────────────────────────────────────────── */
.notification-bell {
    position: fixed; top: 20px; right: 20px; z-index: 1001;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border: none; width: 50px; height: 50px; border-radius: 50%;
    cursor: pointer; box-shadow: 0 4px 15px rgba(26,86,219,0.4);
    transition: all 0.3s ease;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; color: #ffffff;
}
.notification-bell:hover { transform: scale(1.1); box-shadow: 0 6px 20px rgba(26,86,219,0.6); }
.notification-bell .bell-badge {
    position: absolute; top: -5px; right: -5px;
    background: #ff0000; color: #fff;
    width: 22px; height: 22px; border-radius: 50%;
    font-size: 11px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 0 10px rgba(255,0,0,0.8);
    animation: pulse 2s infinite;
}
@keyframes pulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.1)} }

/* ── SIDEBAR OVERLAY ────────────────────────────────────────────────── */
.sidebar-overlay {
    display: none; position: fixed; top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(26,86,219,0.1); z-index: 999;
    opacity: 0; transition: opacity 0.3s ease;
}
.sidebar-overlay.active { display: block; opacity: 1; }

/* ── SIDEBAR ────────────────────────────────────────────────────────── */
.sidebar {
    position: fixed; top: 0; left: 0;
    width: 260px; height: 100vh; overflow-y: auto;
    background: linear-gradient(180deg, #1e3a8a 0%, #1a56db 100%);
    border-right: 1px solid rgba(255,255,255,0.1);
    display: flex; flex-direction: column;
    padding: 25px 20px; z-index: 1000;
    transition: transform 0.3s ease; max-height: 100vh;
}
.sidebar::-webkit-scrollbar { width: 6px; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 3px; }
.logo {
    font-size: 1.6rem; font-weight: 700; color: #ffffff;
    margin-bottom: 35px; letter-spacing: 0.5px;
    display: flex; align-items: center; gap: 10px;
}
.sidebar nav { display: flex; flex-direction: column; gap: 8px; flex: 1; }
.sidebar nav a {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px; border-radius: 10px;
    color: rgba(255,255,255,0.75); font-weight: 600;
    transition: all 0.3s ease; font-size: 14px; position: relative;
}
.sidebar nav a:hover,
.sidebar nav a.active {
    background: rgba(255,255,255,0.2);
    color: #ffffff; transform: translateX(5px);
}
.sidebar nav a i { width: 20px; text-align: center; }
.notification-badge {
    display: inline-flex; align-items: center; justify-content: center;
    background-color: #ff0000; color: #ffffff;
    width: 18px; height: 18px; min-width: 18px;
    font-size: 10px; font-weight: 700; border-radius: 50%;
    margin-left: auto; line-height: 1; box-shadow: 0 0 8px rgba(255,0,0,0.7);
}
.sidebar .footer {
    margin-top: auto; font-size: 12px; color: rgba(255,255,255,0.5);
    text-align: center; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.15);
}
.logout-btn {
    background: linear-gradient(135deg, #ff4444, #c82333) !important;
    color: var(--text-primary) !important;
    margin-top: auto !important; justify-content: center !important;
}
.logout-btn:hover { background: linear-gradient(135deg, #ff6666, #d44444) !important; }

/* ── MAIN ───────────────────────────────────────────────────────────── */
.main {
    margin-left: 260px; padding: 40px;
    width: calc(100% - 260px); height: 100vh; overflow-y: auto;
    background: linear-gradient(135deg, #f0f4ff, #e8eeff);
}
.main::-webkit-scrollbar { width: 8px; }
.main::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

.header {
    font-size: 28px; font-weight: 700; margin-bottom: 30px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text; display: flex; align-items: center; gap: 15px;
}

/* ── MECHANIC GRID ──────────────────────────────────────────────────── */
.mechanic-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px; margin-top: 10px;
}
.mechanic-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px; padding: 25px; text-align: center;
    transition: all 0.3s ease; cursor: pointer;
    display: flex; flex-direction: column; align-items: center; gap: 15px;
    text-decoration: none; color: inherit;
    box-shadow: 0 4px 15px rgba(26,86,219,0.08);
}
.mechanic-card:hover {
    transform: translateY(-8px);
    border-color: var(--primary);
    box-shadow: 0 15px 35px rgba(26,86,219,0.2);
}
.mechanic-card img {
    width: 100px; height: 100px; border-radius: 50%;
    object-fit: cover;
    border: 4px solid var(--primary);
    box-shadow: 0 5px 15px rgba(26,86,219,0.2);
}
.mechanic-card h3 { font-size: 18px; color: var(--primary); margin: 0; font-weight: 700; }
.mechanic-card p  { color: var(--text-secondary); font-size: 13px; margin: 0; }

/* ── NO CONVERSATIONS ───────────────────────────────────────────────── */
.no-conversations {
    text-align: center; padding: 60px 20px; color: var(--text-secondary);
}
.no-conversations i {
    font-size: 60px; margin-bottom: 20px; display: block;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text; opacity: 0.4;
}
.no-conversations p { font-size: 18px; margin-bottom: 10px; }
.no-conversations a {
    display: inline-flex; align-items: center; gap: 8px;
    margin-top: 15px; padding: 12px 24px; border-radius: 10px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff; font-weight: 700; font-size: 14px;
    box-shadow: 0 4px 15px rgba(26,86,219,0.2); transition: all 0.3s;
}
.no-conversations a:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,86,219,0.3); }

/* ── MOBILE ─────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .hamburger-btn { display: block; }
    .sidebar { transform: translateX(-100%); }
    .sidebar.active { transform: translateX(0); }
    .sidebar nav { padding-bottom: 20px; }
    .main { margin-left: 0; width: 100%; padding: 80px 20px 20px 20px; }
    .header { font-size: 20px; margin-top: 20px; }
    .mechanic-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; }
    .mechanic-card { padding: 20px; gap: 12px; }
    .mechanic-card img { width: 80px; height: 80px; border-width: 3px; }
    .mechanic-card h3 { font-size: 16px; }
}
</style>
    </head>
    <body>

    <!-- HAMBURGER BUTTON -->
    <button class="hamburger-btn" id="hamburger-btn">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <!-- NOTIFICATION BELL -->
    <button class="notification-bell" id="notification-bell">
        <i class="fas fa-bell"></i>
        <?php if ($unreadNotifications > 0): ?>
            <span class="bell-badge"><?= $unreadNotifications ?></span>
        <?php endif; ?>
    </button>

    <!-- SIDEBAR OVERLAY -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <i class="fas fa-tools"></i>
            <span>MotorService</span>
        </div>

        <nav>
            <a href="customer_dashboard.php">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="customer_my_bookings.php">
                <i class="fas fa-calendar-alt"></i> My Bookings
            </a>
            <a href="customer_history.php">
                <i class="fas fa-history"></i> History
            </a>
            <a href="customer_messages.php" class="active">
                <i class="fas fa-envelope"></i> Messages
                <?php if ($messages > 0): ?>
                    <span class="notification-badge"><?= $messages ?></span>
                <?php endif; ?>
            </a>
            <!-- ✅ MY RECEIPTS — BAGO -->
            <a href="customer_my_receipts.php">
                <i class="fas fa-receipt"></i> My Receipts
            </a>
            <a href="customer_address.php">
                <i class="fas fa-map-marker-alt"></i> Address
            </a>
            <a href="customer_profile.php">
                <i class="fas fa-user-circle"></i> Profile
            </a>
            <a href="customer_change_password.php">
                <i class="fas fa-lock"></i> Change Password
            </a>
            <a href="../../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>

        <div class="footer">v1.0 • <?= date('Y') ?></div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">
        <div class="header">
            <i class="fas fa-comments"></i> Messages
        </div>

        <?php if (empty($mechanics)): ?>
            <div class="no-conversations">
                <i class="fas fa-inbox"></i>
                <p>No conversations yet</p>
                <p>Book a service and start chatting with your mechanic!</p>
                <a href="customer_dashboard.php">📅 Book Service</a>
            </div>
        <?php else: ?>
            <div class="mechanic-grid">
                <?php foreach ($mechanics as $mechanic): ?>
                    <a href="?mechanic_id=<?= $mechanic['id'] ?>" class="mechanic-card">
                        <img src="../../uploads/profile_pics/<?= htmlspecialchars($mechanic['profile_pic'] ?? 'default.jpg'); ?>" alt="<?= htmlspecialchars($mechanic['name']); ?>">
                        <h3><?= htmlspecialchars($mechanic['name']); ?></h3>
                        <p>👨‍🔧 Mechanic</p>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
    const hamburgerBtn   = document.getElementById('hamburger-btn');
    const sidebar        = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');

    hamburgerBtn.addEventListener('click', () => {
        hamburgerBtn.classList.toggle('active');
        sidebar.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');
    });
    sidebarOverlay.addEventListener('click', () => {
        hamburgerBtn.classList.remove('active');
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
    });
    sidebar.querySelectorAll('nav a').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                hamburgerBtn.classList.remove('active');
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            }
        });
    });
    document.getElementById('notification-bell').addEventListener('click', () => {
        window.location.href = 'customer_notifications.php';
    });
    </script>

    </body>
    </html>
    <?php
    exit();
}

// Fetch mechanic info
$mechanic_stmt = $pdo->prepare("SELECT first_name AS name, profile_pic FROM users WHERE id = ? AND role = 'mechanic'");
$mechanic_stmt->execute([$mechanic_id]);
$mechanic = $mechanic_stmt->fetch(PDO::FETCH_ASSOC);

if (!$mechanic) {
    echo "Mechanic not found or not a mechanic.";
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
        $insert_stmt->execute([$customer_id, $mechanic_id, $message]);
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
    $mark_read_stmt->execute([$mechanic_id, $customer_id]);

    $chat_stmt = $pdo->prepare("
        SELECT m.sender_id, m.receiver_id, m.message_text, m.created_at, m.is_read, u.first_name AS name, u.profile_pic 
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    $chat_stmt->execute([$customer_id, $mechanic_id, $mechanic_id, $customer_id]);
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
<title>Chat with <?= htmlspecialchars($mechanic['name']); ?> - MotorService</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
* {
    margin: 0; padding: 0; box-sizing: border-box;
    -webkit-tap-highlight-color: transparent;
}
html { height: 100%; overflow: hidden; }
body {
    font-family: 'Outfit', sans-serif;
    background: #f0f4ff;
    color: #1e293b;
    height: 100%; overflow: hidden; position: relative;
}

/* ── CHAT WRAPPER ───────────────────────────────────────────────────── */
.chat-wrapper {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    display: flex; flex-direction: column;
    background: #f0f4ff;
    max-height: 100vh; max-height: 100dvh;
}

/* ── CHAT HEADER ────────────────────────────────────────────────────── */
.chat-header {
    background: linear-gradient(135deg, #1a56db, #1e40af);
    padding: 15px 20px;
    display: flex; align-items: center; justify-content: space-between;
    box-shadow: 0 2px 15px rgba(26,86,219,0.3);
    z-index: 10; flex-shrink: 0;
}
.header-left {
    display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0;
}
.header-left img {
    width: 45px; height: 45px; border-radius: 50%;
    object-fit: cover; border: 2px solid rgba(255,255,255,0.7); flex-shrink: 0;
}
.header-info { min-width: 0; flex: 1; }
.header-info h2 {
    margin: 0; font-size: 16px; color: #ffffff;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.header-info p { margin: 2px 0 0 0; font-size: 11px; color: rgba(255,255,255,0.85); }
.back-btn {
    color: #ffffff; text-decoration: none; font-size: 20px; padding: 8px;
    background: rgba(255,255,255,0.15); border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; width: 36px; height: 36px;
    transition: background 0.2s ease;
}
.back-btn:hover { background: rgba(255,255,255,0.25); }

/* ── CHAT MESSAGES ──────────────────────────────────────────────────── */
.chat-messages {
    flex: 1 1 auto; overflow-y: auto; overflow-x: hidden;
    padding: 15px; padding-bottom: 80px;
    background: #eef2ff;
    display: flex; flex-direction: column; gap: 12px;
    -webkit-overflow-scrolling: touch; min-height: 0;
}
.chat-messages::-webkit-scrollbar { width: 4px; }
.chat-messages::-webkit-scrollbar-thumb { background: rgba(26,86,219,0.2); border-radius: 4px; }

/* ── MESSAGES ───────────────────────────────────────────────────────── */
.message {
    display: flex; gap: 10px; align-items: flex-end;
    animation: slideIn 0.3s ease;
}
@keyframes slideIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
.message.sent   { align-self: flex-end;   flex-direction: row-reverse; max-width: 80%; }
.message.received { align-self: flex-start; max-width: 80%; }

.message img {
    width: 35px; height: 35px; border-radius: 50%;
    object-fit: cover; border: 2px solid #1a56db; flex-shrink: 0;
}
.message-wrapper { display: flex; flex-direction: column; gap: 4px; }
.message.sent     .message-wrapper { align-items: flex-end; }
.message.received .message-wrapper { align-items: flex-start; }

.message-content {
    padding: 10px 14px; border-radius: 12px;
    word-wrap: break-word; line-height: 1.4; font-size: 14px;
    box-shadow: 0 2px 8px rgba(26,86,219,0.1);
}
.message.sent .message-content {
    background: linear-gradient(135deg, #1a56db, #1e40af);
    color: #ffffff; border-bottom-right-radius: 4px;
}
.message.received .message-content {
    background: #ffffff;
    color: #1e293b; border-bottom-left-radius: 4px;
    border: 1px solid rgba(26,86,219,0.15);
}
.message-time { font-size: 11px; color: #94a3b8; padding: 0 4px; }

/* ── SEEN INDICATOR ─────────────────────────────────────────────────── */
.seen-indicator {
    display: flex; justify-content: flex-end; align-items: center;
    gap: 5px; padding-right: 6px;
    margin-top: -4px; margin-bottom: 2px; align-self: flex-end;
}
.seen-indicator img {
    width: 16px; height: 16px; border-radius: 50%; object-fit: cover;
    border: 1.5px solid #1a56db; opacity: 0.9;
}
.seen-indicator span { font-size: 10px; color: #94a3b8; font-style: italic; }

/* ── LOADING ────────────────────────────────────────────────────────── */
.loading {
    text-align: center; color: #94a3b8; font-style: italic;
    padding: 40px 20px; display: flex; align-items: center;
    justify-content: center; height: 100%;
}

/* ── INPUT AREA ─────────────────────────────────────────────────────── */
.chat-input-wrapper {
    background: #ffffff;
    padding: 12px 15px;
    padding-bottom: max(12px, env(safe-area-inset-bottom));
    display: flex; gap: 10px; align-items: flex-end;
    border-top: 1px solid rgba(26,86,219,0.15);
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 1000;
    box-shadow: 0 -4px 15px rgba(26,86,219,0.08);
}
.input-container {
    flex: 1; display: flex; align-items: center;
    background: #f8faff; border: 2px solid rgba(26,86,219,0.2);
    border-radius: 20px; padding: 0 15px; transition: all 0.3s ease;
}
.input-container:focus-within {
    border-color: #1a56db;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(26,86,219,0.1);
}
textarea {
    flex: 1; min-height: 38px; max-height: 100px;
    background: transparent; border: none; color: #1e293b;
    padding: 10px 0; resize: none;
    font-family: 'Outfit', sans-serif; font-size: 14px; outline: none;
}
textarea::placeholder { color: #94a3b8; }
.send-btn {
    background: linear-gradient(135deg, #1a56db, #1e40af);
    color: #fff; border: none; border-radius: 50%;
    width: 44px; height: 44px; cursor: pointer; font-size: 16px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 3px 10px rgba(26,86,219,0.3); flex-shrink: 0;
    transition: all 0.2s ease;
}
.send-btn:hover  { transform: scale(1.08); box-shadow: 0 5px 15px rgba(26,86,219,0.4); }
.send-btn:active { transform: scale(0.95); }

/* ── DESKTOP TWEAKS ─────────────────────────────────────────────────── */
@media (min-width: 768px) {
    .chat-header { padding: 20px 30px; }
    .header-left img { width: 55px; height: 55px; }
    .header-info h2 { font-size: 20px; }
    .header-info p  { font-size: 13px; }
    .back-btn { width: auto; padding: 8px 16px; gap: 8px; }
    .back-btn::after { content: 'Back'; font-size: 14px; font-weight: 600; }
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
            <img src="../../uploads/profile_pics/<?= htmlspecialchars($mechanic['profile_pic'] ?? 'default.jpg'); ?>" alt="<?= htmlspecialchars($mechanic['name']); ?>">
            <div class="header-info">
                <h2><?= htmlspecialchars($mechanic['name']); ?></h2>
                <p>👨‍🔧 Mechanic</p>
            </div>
        </div>
        <a href="customer_messages.php" class="back-btn">
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

// Track last known state to avoid unnecessary redraws
let lastMessageSignature = '';

function buildSignature(messages) {
    if (!messages.length) return '';
    const last = messages[messages.length - 1];
    const seenCount = messages.filter(m => m.sender_id == <?= $customer_id ?> && parseInt(m.is_read) === 1).length;
    return messages.length + '_' + last.created_at + '_' + last.sender_id + '_seen' + seenCount;
}

function renderMessages(messages) {
    const chatBox = document.getElementById('chat-box');

    if (!Array.isArray(messages) || messages.length === 0) {
        chatBox.innerHTML = '<div class="loading">No messages yet. Start the conversation!</div>';
        return;
    }

    const isAtBottom = chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < 60;

    // Find last customer-sent message that the mechanic has read (is_read = 1)
    let lastSeenIdx = -1;
    for (let i = messages.length - 1; i >= 0; i--) {
        if (messages[i].sender_id == <?= $customer_id ?> && parseInt(messages[i].is_read) === 1) {
            lastSeenIdx = i;
            break;
        }
    }

    // Get mechanic's profile pic for seen indicator
    let mechanicPic = '../../assets/img/default_profile.png';
    for (let i = 0; i < messages.length; i++) {
        if (messages[i].sender_id != <?= $customer_id ?> && messages[i].profile_pic) {
            mechanicPic = '../../uploads/profile_pics/' + messages[i].profile_pic;
            break;
        }
    }

    const sig = buildSignature(messages);
    if (sig === lastMessageSignature) return; // Nothing changed — no DOM update
    lastMessageSignature = sig;

    let html = '';
    messages.forEach((message, idx) => {
        const isCustomer = message.sender_id == <?= $customer_id ?>;
        const profilePic = message.profile_pic
            ? '../../uploads/profile_pics/' + message.profile_pic
            : '../../assets/img/default_profile.png';

        html += `
            <div class="message ${isCustomer ? 'sent' : 'received'}">
                <img src="${profilePic}" alt="Profile" onerror="this.src='../../assets/img/default_profile.png'">
                <div class="message-wrapper">
                    <div class="message-content">${escapeHtml(message.message_text)}</div>
                    <div class="message-time">${formatMessageTime(message.created_at)}</div>
                </div>
            </div>`;

        // Show seen indicator right after the last read sent message
        if (isCustomer && idx === lastSeenIdx) {
            html += `
            <div class="seen-indicator">
                <img src="${mechanicPic}" alt="Seen" title="Seen by Mechanic" onerror="this.src='../../assets/img/default_profile.png'">
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
    fetch("?fetch_messages=true&mechanic_id=<?= $mechanic_id ?>")
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

    // Optimistic clear
    textarea.value = '';
    textarea.style.height = 'auto';

    fetch('', {
        method: 'POST',
        body: new URLSearchParams({ 'message': message })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            lastMessageSignature = ''; // force re-render
            fetchMessages();
        }
    })
    .catch(error => console.error('Error sending message:', error));
});

fetchMessages();
setInterval(fetchMessages, 3000);

// Auto-grow textarea
const textarea = document.querySelector('textarea');
textarea.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 100) + 'px';
});

// Prevent zoom on iOS
let lastTouchEnd = 0;
document.addEventListener('touchend', function (event) {
    const now = (new Date()).getTime();
    if (now - lastTouchEnd <= 300) event.preventDefault();
    lastTouchEnd = now;
}, false);

// Scroll to bottom when keyboard shows (mobile)
if ('visualViewport' in window) {
    window.visualViewport.addEventListener('resize', () => {
        const chatBox = document.getElementById('chat-box');
        setTimeout(() => { chatBox.scrollTop = chatBox.scrollHeight; }, 100);
    });
}
</script>

</body>
</html>