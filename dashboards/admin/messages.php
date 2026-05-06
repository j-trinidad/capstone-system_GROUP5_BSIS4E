<?php
session_start();
require '../../includes/session_check.php';
checkRole('admin');
require '../../includes/db_connect.php';

$admin_id = $_SESSION['user_id'];
$mechanic_id = isset($_GET['mechanic_id']) ? (int)$_GET['mechanic_id'] : 0;

// Fetch admin info
$admin_stmt = $pdo->prepare("SELECT first_name AS name, profile_pic FROM users WHERE id = ?");
$admin_stmt->execute([$admin_id]);
$admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    echo "Admin not found.";
    exit();
}

$currentPage = basename($_SERVER['PHP_SELF']);

// If mechanic_id is 0, show mechanic selection with sidebar
if ($mechanic_id === 0) {
    // Get all mechanics (with or without prior conversation)
    $mechanics_stmt = $pdo->prepare("
        SELECT id, first_name AS name, profile_pic, is_disabled
        FROM users
        WHERE role = 'mechanic'
        ORDER BY first_name
    ");
    $mechanics_stmt->execute();
    $mechanics = $mechanics_stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Messages - MotorService Admin</title>
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

        /* HAMBURGER BUTTON */
        .hamburger-btn {
            display: none;
            position: fixed; top: 20px; left: 20px; z-index: 1001;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none; width: 50px; height: 50px; border-radius: 12px;
            cursor: pointer; box-shadow: 0 4px 15px rgba(255,140,0,0.4);
            transition: all 0.3s ease;
        }
        .hamburger-btn:hover { transform: scale(1.05); }
        .hamburger-btn span {
            display: block; width: 25px; height: 3px;
            background: #1a1f3a; margin: 5px auto;
            border-radius: 2px; transition: all 0.3s ease;
        }
        .hamburger-btn.active span:nth-child(1) { transform: rotate(45deg) translate(8px,8px); }
        .hamburger-btn.active span:nth-child(2) { opacity: 0; }
        .hamburger-btn.active span:nth-child(3) { transform: rotate(-45deg) translate(7px,-7px); }

        /* SIDEBAR OVERLAY */
        .sidebar-overlay {
            display: none; position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.7); z-index: 999;
            opacity: 0; transition: opacity 0.3s ease;
        }
        .sidebar-overlay.active { display: block; opacity: 1; }

        a {
            color: inherit;
            text-decoration: none;
        }

        /* FIXED SIDEBAR */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            overflow-y: auto;
            background: linear-gradient(180deg, #0f1419 0%, #1a1f3a 100%);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            padding: 25px 20px;
            z-index: 1000;
            transition: transform 0.3s ease;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 3px;
        }

        .logo {
            font-size: 1.6rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 35px;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo i {
            font-size: 24px;
        }

        .sidebar nav {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
        }

        .sidebar nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 10px;
            color: var(--text-secondary);
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .sidebar nav a:hover,
        .sidebar nav a.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #1a1f3a;
            transform: translateX(5px);
        }

        .sidebar nav a i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        .sidebar .footer {
            margin-top: auto;
            font-size: 12px;
            color: var(--text-secondary);
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        /* MAIN CONTENT */
        .main {
            margin-left: 260px;
            padding: 40px;
            width: calc(100% - 260px);
            height: 100vh;
            overflow-y: auto;
        }

        .main::-webkit-scrollbar {
            width: 8px;
        }

        .main::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
        }

        .header {
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .mechanic-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .mechanic-card {
            background: linear-gradient(135deg, rgba(255, 140, 0, 0.1), rgba(229, 46, 113, 0.1));
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            color: inherit;
            position: relative;
        }

        .mechanic-card:hover:not(.disabled) {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(255, 140, 0, 0.4);
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(255, 140, 0, 0.2), rgba(229, 46, 113, 0.2));
        }

        .mechanic-card.disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .mechanic-card img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary);
            box-shadow: 0 5px 15px rgba(255, 140, 0, 0.3);
        }

        .mechanic-card h3 {
            font-size: 18px;
            color: var(--primary);
            margin: 0;
            font-weight: 700;
        }

        .mechanic-card p {
            color: var(--text-secondary);
            font-size: 13px;
            margin: 0;
        }

        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-badge.active {
            background: rgba(0, 208, 132, 0.2);
            color: var(--success);
            border: 1px solid var(--success);
        }

        .status-badge.inactive {
            background: rgba(255, 71, 87, 0.2);
            color: var(--error);
            border: 1px solid var(--error);
        }

        .no-mechanics {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .no-mechanics i {
            font-size: 60px;
            color: rgba(255, 140, 0, 0.3);
            margin-bottom: 20px;
            display: block;
        }

        .no-mechanics p {
            font-size: 18px;
            margin-bottom: 20px;
        }

        @media (max-width: 1024px) {
            .sidebar {
                width: 220px;
            }

            .main {
                margin-left: 220px;
                width: calc(100% - 220px);
                padding: 30px 20px;
            }
        }

        @media (max-width: 768px) {
            .hamburger-btn { display: block; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .sidebar nav { padding-bottom: 20px; }

            .main {
                margin-left: 0;
                width: 100%;
                padding: 80px 20px 20px 20px;
            }

            .header h1 { font-size: 22px; }

            .mechanic-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 15px;
                margin-top: 20px;
            }

            .mechanic-card { padding: 20px; gap: 12px; }
            .mechanic-card img { width: 80px; height: 80px; border-width: 3px; }
            .mechanic-card h3 { font-size: 16px; }
        }

        @media (max-width: 480px) {
            .main { padding: 75px 15px 15px 15px; }
            .mechanic-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .mechanic-card { padding: 15px; }
            .mechanic-card img { width: 65px; height: 65px; }
            .mechanic-card h3 { font-size: 14px; }
        }
    </style>
    </head>
    <body>
        <button class="hamburger-btn" id="hamburger-btn">
            <span></span><span></span><span></span>
        </button>

        <div class="sidebar-overlay" id="sidebar-overlay"></div>

        <aside class="sidebar" id="sidebar">
            <div class="logo">
                <i class="fas fa-tools"></i>
                <span>MotorService</span>
            </div>

            <nav>
                <a href="admin_profile.php" class="<?= $currentPage == 'admin_profile.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-circle"></i> Profile
                </a>
                <a href="admin_dashboard.php" class="<?= $currentPage == 'admin_dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
                <a href="activity_log.php" class="<?= $currentPage == 'activity_log.php' ? 'active' : '' ?>">
                    <i class="fas fa-clipboard-list"></i> Activity Log
                </a>
                <a href="messages.php" class="<?= $currentPage == 'messages.php' ? 'active' : '' ?>">
                    <i class="fas fa-comments"></i> Messages
                </a>
                <a href="sales.php" class="<?= $currentPage == 'sales.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar"></i> Sales
                </a>
                <a href="admin_receipts.php" class="<?= $currentPage == 'admin_receipts.php' ? 'active' : '' ?>">
                    <i class="fas fa-receipt"></i> All Receipts
                </a>
                <a href="admin_change_password.php" class="<?= $currentPage == 'admin_change_password.php' ? 'active' : '' ?>">
                    <i class="fas fa-key"></i> Change Password
                </a>
                <a href="../../logout.php" style="margin-top: auto;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>

            <div class="footer">v1.0 • <?= date('Y') ?></div>
        </aside>

        <main class="main">
            <div class="header">
                <h1><i class="fas fa-comments"></i> Messages</h1>
                <p>Select a mechanic to start chatting</p>
            </div>

            <?php if (empty($mechanics)): ?>
                <div class="no-mechanics">
                    <i class="fas fa-inbox"></i>
                    <p>No mechanics found</p>
                    <p>Add mechanics first to start messaging!</p>
                </div>
            <?php else: ?>
                <div class="mechanic-grid">
                    <?php foreach ($mechanics as $mechanic): ?>
                        <a href="?mechanic_id=<?= $mechanic['id'] ?>" class="mechanic-card <?= $mechanic['is_disabled'] ? 'disabled' : '' ?>">
                            <span class="status-badge <?= $mechanic['is_disabled'] ? 'inactive' : 'active' ?>">
                                <?= $mechanic['is_disabled'] ? 'Disabled' : 'Active' ?>
                            </span>
                            <img src="../../uploads/profile_pics/<?= htmlspecialchars($mechanic['profile_pic'] ?? 'default.jpg'); ?>" alt="<?= htmlspecialchars($mechanic['name']); ?>">
                            <h3><?= htmlspecialchars($mechanic['name']); ?></h3>
                            <p>🔧 Mechanic</p>
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
    echo "Mechanic not found.";
    exit();
}

// Send new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $insert_stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, message_text, created_at, is_read)
            VALUES (?, ?, ?, NOW(), 0)
        ");
        $insert_stmt->execute([$admin_id, $mechanic_id, $message]);
        echo json_encode(['status' => 'success']);
        exit();
    }
    echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty']);
    exit();
}

// Fetch chat history (AJAX)
if (isset($_GET['fetch_messages']) && $_GET['fetch_messages'] == 'true') {
    $mark_read_stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    $mark_read_stmt->execute([$mechanic_id, $admin_id]);

    $chat_stmt = $pdo->prepare("
        SELECT m.sender_id, m.receiver_id, m.message_text, m.created_at, m.is_read, u.first_name AS name, u.profile_pic 
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    $chat_stmt->execute([$admin_id, $mechanic_id, $mechanic_id, $admin_id]);
    $messages = $chat_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($messages);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chat with <?= htmlspecialchars($mechanic['name']); ?> - MotorService Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
    :root {
        --primary: #ff8c00;
        --secondary: #e52e71;
        --dark-bg: #0a0e27;
        --card-bg: #1a1f3a;
        --border: rgba(255, 140, 0, 0.2);
        --text-primary: #fff;
        --text-secondary: #b0b8d4;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    html, body {
        font-family: 'Outfit', sans-serif;
        background: linear-gradient(135deg, var(--dark-bg), #1a1f3a);
        color: var(--text-primary);
        height: 100vh;
        overflow: hidden;
    }

    .chat-container {
        width: 100%;
        height: 100vh;
        display: flex;
        flex-direction: column;
        background: #0f0f0f;
    }

    .chat-header {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        padding: 20px 30px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        flex-shrink: 0;
        min-height: 80px;
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 15px;
        flex: 1;
    }

    .header-left img {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #fff;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.3);
    }

    .header-info h2 {
        margin: 0;
        font-size: 20px;
        color: #fff;
        font-weight: 700;
    }

    .header-info p {
        margin: 3px 0 0 0;
        font-size: 13px;
        color: rgba(255, 255, 255, 0.9);
    }

    .back-btn {
        color: #fff;
        text-decoration: none;
        font-size: 18px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 10px;
        font-weight: 700;
        border: none;
        cursor: pointer;
    }

    .back-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateX(-3px);
    }

    .chat-box {
        flex: 1;
        overflow-y: auto;
        padding: 25px 30px;
        background: #0f0f0f;
        display: flex;
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }

    .chat-box::-webkit-scrollbar {
        width: 8px;
    }

    .chat-box::-webkit-scrollbar-track {
        background: transparent;
    }

    .chat-box::-webkit-scrollbar-thumb {
        background: rgba(255, 140, 0, 0.3);
        border-radius: 4px;
    }

    .chat-box::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 140, 0, 0.5);
    }

    .message {
        display: flex;
        gap: 12px;
        animation: slideIn 0.3s ease;
        align-items: flex-end;
        margin-bottom: 12px;
    }

    @keyframes slideIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .message.sent {
        justify-content: flex-end;
        margin-left: auto;
        margin-right: 0;
        width: fit-content;
        max-width: 65%;
        flex-direction: row-reverse;
    }

    .message.received {
        justify-content: flex-start;
        margin-right: auto;
        margin-left: 0;
        width: fit-content;
        max-width: 65%;
    }

    .message img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--primary);
        flex-shrink: 0;
    }

    .message-wrapper {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .message.sent .message-wrapper {
        align-items: flex-end;
    }

    .message.received .message-wrapper {
        align-items: flex-start;
    }

    .message-content {
        padding: 12px 16px;
        border-radius: 15px;
        word-wrap: break-word;
        line-height: 1.4;
        font-size: 15px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .message.sent .message-content {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: #fff;
        border-bottom-right-radius: 4px;
    }

    .message.received .message-content {
        background: #2c2c2c;
        color: var(--text-primary);
        border-bottom-left-radius: 4px;
    }

    .message-time {
        font-size: 12px;
        color: var(--text-secondary);
        padding: 0 5px;
    }

    /* ── Seen indicator ── */
    .seen-indicator {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 5px;
        padding-right: 6px;
        margin-top: -6px;
        margin-bottom: 4px;
    }

    .seen-indicator img {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        object-fit: cover;
        border: 1.5px solid var(--primary);
        opacity: 0.9;
    }

    .seen-indicator span {
        font-size: 11px;
        color: var(--text-secondary);
        font-style: italic;
    }

    .loading {
        text-align: center;
        color: var(--text-secondary);
        font-style: italic;
        padding: 40px 20px;
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .message-form {
        background: #1e1e1e;
        padding: 20px 30px;
        display: flex;
        gap: 12px;
        align-items: flex-end;
        border-top: 1px solid var(--border);
        flex-shrink: 0;
    }

    textarea {
        flex: 1;
        min-height: 50px;
        max-height: 120px;
        background: #2c2c2c;
        border: 2px solid #444;
        border-radius: 20px;
        color: var(--text-primary);
        padding: 12px 20px;
        resize: none;
        font-family: 'Outfit', sans-serif;
        font-size: 15px;
        transition: all 0.3s ease;
    }

    textarea:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 12px rgba(255, 140, 0, 0.4);
        background: #333;
    }

    textarea::placeholder {
        color: #888;
    }

    .send-btn {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: #fff;
        border: none;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        cursor: pointer;
        font-size: 18px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(255, 140, 0, 0.3);
        flex-shrink: 0;
        font-weight: 700;
    }

    .send-btn:hover {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 6px 20px rgba(255, 140, 0, 0.5);
    }

    .send-btn:active {
        transform: scale(0.95);
    }

    @media (max-width: 768px) {
        .chat-header {
            padding: 15px 20px;
            min-height: 70px;
        }

        .header-left img {
            width: 50px;
            height: 50px;
        }

        .header-info h2 {
            font-size: 18px;
        }

        .header-info p {
            font-size: 12px;
        }

        .back-btn {
            font-size: 14px;
            padding: 6px 12px;
        }

        .back-btn span {
            display: none;
        }

        .chat-box {
            padding: 15px 20px;
            gap: 12px;
        }

        .message-wrapper {
            max-width: 75%;
        }

        .message-content {
            padding: 10px 14px;
            font-size: 14px;
            border-radius: 12px;
        }

        .message-form {
            padding: 15px 20px;
            gap: 10px;
        }

        textarea {
            min-height: 45px;
            padding: 10px 16px;
            font-size: 14px;
        }

        .send-btn {
            width: 45px;
            height: 45px;
            font-size: 16px;
        }
    }

    @media (max-width: 480px) {
        .chat-container {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 0;
        }

        .chat-header {
            padding: 12px 15px;
            min-height: 65px;
        }

        .header-left {
            gap: 10px;
        }

        .header-left img {
            width: 45px;
            height: 45px;
            border-width: 2px;
        }

        .header-info h2 {
            font-size: 16px;
        }

        .header-info p {
            font-size: 11px;
        }

        .back-btn {
            font-size: 16px;
            padding: 5px 10px;
        }

        .chat-box {
            padding: 12px 15px;
            gap: 10px;
        }

        .message-wrapper {
            max-width: 80%;
        }

        .message-content {
            padding: 9px 12px;
            font-size: 13px;
        }

        .message-time {
            font-size: 11px;
        }

        .message-form {
            padding: 12px 15px;
            gap: 8px;
        }

        textarea {
            min-height: 40px;
            padding: 9px 14px;
            font-size: 13px;
            border-radius: 18px;
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
    <div class="chat-header">
        <div class="header-left">
            <img src="../../uploads/profile_pics/<?= htmlspecialchars($mechanic['profile_pic'] ?? 'default.jpg'); ?>" alt="<?= htmlspecialchars($mechanic['name']); ?>">
            <div class="header-info">
                <h2><?= htmlspecialchars($mechanic['name']); ?></h2>
                <p>🔧 Mechanic</p>
            </div>
        </div>
        <a href="messages.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            <span>Back</span>
        </a>
    </div>

    <div class="chat-box" id="chat-box">
        <div class="loading">Loading messages...</div>
    </div>

    <form class="message-form" id="message-form">
        <textarea name="message" placeholder="Type your message..." required></textarea>
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

    if (diffMinutes < 1) {
        return 'Just now';
    } else if (diffMinutes < 60) {
        return diffMinutes + 'm ago';
    } else if (diffHours < 24) {
        return messageDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    } else if (diffDays === 1) {
        return 'Yesterday';
    } else if (diffDays < 7) {
        return messageDate.toLocaleDateString([], { weekday: 'long' });
    } else {
        return messageDate.toLocaleDateString([], { month: 'short', day: 'numeric' });
    }
}

// Track last known state to avoid unnecessary redraws
let lastMessageSignature = '';

function buildSignature(messages) {
    if (!messages.length) return '';
    const last = messages[messages.length - 1];
    // Count how many admin messages have been read (seen count)
    const seenCount = messages.filter(m => m.sender_id == <?= $admin_id ?> && parseInt(m.is_read) === 1).length;
    return messages.length + '_' + last.created_at + '_' + last.sender_id + '_seen' + seenCount;
}

function renderMessages(messages) {
    const chatBox = document.getElementById('chat-box');

    if (messages.length === 0) {
        chatBox.innerHTML = '<div class="loading">No messages yet. Start the conversation!</div>';
        return;
    }

    // Check if we were already scrolled to the bottom before re-render
    const isAtBottom = chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < 60;

    // Find the index of the LAST message sent by admin that has been read (is_read=1)
    // This is where we show the "Seen" indicator with mechanic's profile pic
    let lastSeenIdx = -1;
    for (let i = messages.length - 1; i >= 0; i--) {
        const m = messages[i];
        if (m.sender_id == <?= $admin_id ?> && parseInt(m.is_read) === 1) {
            lastSeenIdx = i;
            break;
        }
    }

    // Mechanic profile pic (from the first received message, or fallback)
    let mechanicPic = '../../assets/img/default_profile.png';
    for (let i = 0; i < messages.length; i++) {
        if (messages[i].sender_id != <?= $admin_id ?> && messages[i].profile_pic) {
            mechanicPic = '../../uploads/profile_pics/' + messages[i].profile_pic;
            break;
        }
    }

    // Build new HTML string
    let html = '';
    messages.forEach((message, idx) => {
        const isAdmin = message.sender_id == <?= $admin_id ?>;
        const profilePic = message.profile_pic
            ? '../../uploads/profile_pics/' + message.profile_pic
            : '../../assets/img/default_profile.png';

        html += `
            <div class="message ${isAdmin ? 'sent' : 'received'}">
                <img src="${profilePic}" alt="Profile">
                <div class="message-wrapper">
                    <div class="message-content">${message.message_text}</div>
                    <div class="message-time">${formatMessageTime(message.created_at)}</div>
                </div>
            </div>`;

        // Show seen indicator RIGHT after the last read sent message
        if (isAdmin && idx === lastSeenIdx) {
            html += `
            <div class="seen-indicator">
                <img src="${mechanicPic}" alt="Seen" title="Seen">
                <span>Seen</span>
            </div>`;
        }
    });

    // Only update DOM if content actually changed
    const sig = buildSignature(messages);
    if (sig !== lastMessageSignature) {
        lastMessageSignature = sig;
        chatBox.innerHTML = html;
        if (isAtBottom) {
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    }
}

function fetchMessages() {
    fetch("?fetch_messages=true&mechanic_id=<?= $mechanic_id ?>")
        .then(response => response.json())
        .then(messages => renderMessages(messages))
        .catch(error => console.error('Error fetching messages:', error));
}

document.getElementById('message-form').addEventListener('submit', function (e) {
    e.preventDefault();
    const textarea = document.querySelector('textarea[name="message"]');
    const message = textarea.value.trim();

    if (message === '') return;

    // Optimistic: clear input immediately
    textarea.value = '';
    textarea.style.height = 'auto';

    fetch('', {
        method: 'POST',
        body: new URLSearchParams({ 'message': message })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Force a fresh fetch so new message appears without full blink
            lastMessageSignature = '';
            fetchMessages();
        }
    })
    .catch(error => console.error('Error sending message:', error));
});

// Initial load then poll every 3s (was 2s — reduces server load too)
fetchMessages();
setInterval(fetchMessages, 3000);

// Auto-grow textarea
document.querySelector('textarea').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});
</script>

</body>
</html>