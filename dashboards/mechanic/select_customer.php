<?php
session_start();
require '../../includes/session_check.php';
checkRole('mechanic');
require '../../includes/db_connect.php';

$mechanic_id = $_SESSION['user_id'];

// Unread messages count for sidebar badge
$messagesStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$messagesStmt->execute([$mechanic_id]);
$messages = $messagesStmt->fetchColumn();

// Get customers linked to mechanic bookings
$stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.first_name AS name, u.profile_pic 
    FROM bookings b
    JOIN users u ON b.customer_id = u.id
    WHERE b.mechanic_id = ?
    ORDER BY u.first_name
");
$stmt->execute([$mechanic_id]);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Message Customers - MotorService</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #1a56db;
    --secondary: #1e40af;
    --dark-bg: #f0f4ff;
    --card-bg: #ffffff;
    --border: rgba(26, 86, 219, 0.2);
    --text-primary: #1e293b;
    --text-secondary: #475569;
    --success: #059669;
    --error: #dc2626;
    --warning: #d97706;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    -webkit-tap-highlight-color: transparent;
}

html, body {
    height: 100%;
    font-family: 'Outfit', sans-serif;
    background: linear-gradient(135deg, #f0f4ff, #e8eeff);
    color: var(--text-primary);
    overflow: hidden;
}

a {
    color: inherit;
    text-decoration: none;
}

/* HAMBURGER */
.hamburger-btn {
    display: none;
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 1001;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 12px;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.4);
    transition: all 0.3s ease;
}

.hamburger-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(26, 86, 219, 0.6);
}

.hamburger-btn span {
    display: block;
    width: 25px;
    height: 3px;
    background: #ffffff;
    margin: 5px auto;
    border-radius: 2px;
    transition: all 0.3s ease;
}

.hamburger-btn.active span:nth-child(1) { transform: rotate(45deg) translate(8px, 8px); }
.hamburger-btn.active span:nth-child(2) { opacity: 0; }
.hamburger-btn.active span:nth-child(3) { transform: rotate(-45deg) translate(7px, -7px); }

/* OVERLAY */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(26, 86, 219, 0.1);
    z-index: 999;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.sidebar-overlay.active {
    display: block;
    opacity: 1;
}

/* SIDEBAR */
.sidebar {
    position: fixed;
    top: 0; left: 0;
    width: 260px;
    height: 100vh;
    overflow-y: auto;
    background: linear-gradient(180deg, #1e3a8a 0%, #1a56db 100%);
    border-right: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    flex-direction: column;
    padding: 25px 20px;
    z-index: 1000;
    transition: transform 0.3s ease;
    max-height: 100vh;
    -webkit-overflow-scrolling: touch;
}

.sidebar::-webkit-scrollbar { width: 6px; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 3px; }

.logo {
    font-size: 1.6rem;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 35px;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 10px;
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
    color: rgba(255, 255, 255, 0.75);
    font-weight: 600;
    transition: all 0.3s ease;
    font-size: 14px;
    position: relative;
}

.sidebar nav a:hover,
.sidebar nav a.active {
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff;
    transform: translateX(5px);
}

.sidebar nav a i { width: 20px; text-align: center; }

.notification-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background-color: #ff0000;
    color: #ffffff;
    width: 18px; height: 18px; min-width: 18px;
    font-size: 10px;
    font-weight: 700;
    border-radius: 50%;
    margin-left: auto;
    line-height: 1;
    box-shadow: 0 0 8px rgba(255,0,0,0.7);
}

.sidebar .footer {
    margin-top: auto;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.5);
    text-align: center;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.15);
}

.logout-btn {
    background: linear-gradient(135deg, #ff4444, #c82333) !important;
    color: #fff !important;
    margin-top: auto !important;
    justify-content: center !important;
}

.logout-btn:hover {
    background: linear-gradient(135deg, #ff6666, #d44444) !important;
}

/* MAIN */
.main {
    margin-left: 260px;
    padding: 40px;
    width: calc(100% - 260px);
    height: 100vh;
    overflow-y: auto;
    overflow-x: hidden;
    background: linear-gradient(135deg, #f0f4ff, #e8eeff);
    -webkit-overflow-scrolling: touch;
}

.main::-webkit-scrollbar { width: 8px; }
.main::-webkit-scrollbar-track { background: rgba(26, 86, 219, 0.04); }
.main::-webkit-scrollbar-thumb { background: var(--border); border-radius: 5px; }
.main::-webkit-scrollbar-thumb:hover { background: var(--primary); }

.header {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 8px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    display: flex;
    align-items: center;
    gap: 15px;
}

.header-sub {
    color: var(--text-secondary);
    font-size: 14px;
    margin-bottom: 30px;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 30px;
    color: var(--primary);
    font-weight: 600;
    transition: all 0.3s ease;
    padding: 10px 20px;
    background: rgba(26, 86, 219, 0.06);
    border-radius: 10px;
    border: 1px solid var(--border);
    font-size: 14px;
}

.back-link:hover {
    background: rgba(26, 86, 219, 0.12);
    transform: translateX(-5px);
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.15);
}

/* CUSTOMER GRID */
.customer-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 20px;
    margin-top: 10px;
    animation: slideIn 0.4s ease;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}

.customer-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 28px 20px;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.07);
    position: relative;
    overflow: hidden;
}

.customer-card::before {
    content: '';
    position: absolute;
    top: -50%; left: -50%;
    width: 200%; height: 200%;
    background: linear-gradient(135deg, transparent, rgba(26, 86, 219, 0.05), transparent);
    transform: rotate(45deg);
    transition: 0.5s;
}

.customer-card:hover::before { left: 100%; }

.customer-card:hover {
    transform: translateY(-6px);
    border-color: var(--primary);
    box-shadow: 0 12px 30px rgba(26, 86, 219, 0.18);
}

.customer-card img {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--primary);
    box-shadow: 0 4px 12px rgba(26, 86, 219, 0.2);
}

.customer-card h3 {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.customer-card p {
    color: var(--text-secondary);
    font-size: 12px;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 5px;
}

/* EMPTY STATE */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.3;
    color: var(--primary);
    display: block;
}

.empty-state p {
    margin-bottom: 8px;
    font-size: 15px;
}

.empty-state a {
    color: var(--primary);
    font-weight: 600;
    text-decoration: underline;
}

@media (max-width: 1024px) {
    .sidebar { width: 220px; }
    .main { margin-left: 220px; width: calc(100% - 220px); padding: 30px 20px; }
    .header { font-size: 24px; }
}

@media (max-width: 768px) {
    .hamburger-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; }

    .sidebar {
        position: fixed;
        top: 0; left: 0;
        width: 80%; max-width: 300px;
        height: 100vh;
        transform: translateX(-100%);
        flex-direction: column;
        padding: 25px 20px;
        overflow-y: auto;
    }

    .sidebar.active { transform: translateX(0); }
    .sidebar nav { flex-direction: column; gap: 8px; }
    .sidebar nav a { padding: 12px 16px; font-size: 14px; }

    .main {
        margin-left: 0;
        width: 100%;
        padding: 90px 20px 40px;
        height: 100vh;
    }

    .header { font-size: 20px; margin-top: 0; margin-bottom: 5px; }

    .customer-grid {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 15px;
    }

    .customer-card { padding: 22px 15px; gap: 10px; }

    .customer-card img { width: 72px; height: 72px; }

    .customer-card h3 { font-size: 14px; }
}

@media (max-width: 480px) {
    .customer-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .customer-card img { width: 64px; height: 64px; }
}
</style>
</head>
<body>

<!-- HAMBURGER -->
<button class="hamburger-btn" id="hamburger-btn">
    <span></span><span></span><span></span>
</button>

<!-- OVERLAY -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="logo">
        <i class="fas fa-wrench"></i>
        <span>MotorService</span>
    </div>

    <nav>
        <a href="mechanic_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="service_request.php"><i class="fas fa-clipboard-list"></i> Service Requests</a>
        <a href="mechanic_history.php"><i class="fas fa-history"></i> History</a>
        <a href="message_center.php" class="active">
            <i class="fas fa-envelope"></i> Messages
            <?php if ($messages > 0): ?>
                <span class="notification-badge"><?= $messages ?></span>
            <?php endif; ?>
        </a>
        <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="change_password.php"><i class="fas fa-lock"></i> Change Password</a>
        <a href="report_absence.php"><i class="fas fa-calendar-times"></i> File Leave</a>
        <a href="../../logout.php" class="logout-btn" style="margin-top: auto;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>

    <div class="footer">v1.0 • <?= date('Y') ?></div>
</aside>

<!-- MAIN -->
<main class="main">
    <a href="message_center.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Message Center
    </a>

    <div class="header">
        <i class="fas fa-comments"></i> Message Customers
    </div>
    <p class="header-sub">Select a customer to start chatting</p>

    <?php if (empty($customers)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No customers yet.</p>
            <p>Customers assigned to your bookings will appear here!</p>
            <a href="mechanic_dashboard.php"><i class="fas fa-clipboard-list"></i> View Bookings</a>
        </div>
    <?php else: ?>
        <div class="customer-grid">
            <?php foreach ($customers as $customer): ?>
                <a href="mechanic_messages.php?customer_id=<?= $customer['id'] ?>" class="customer-card">
                    <img src="../../uploads/profile_pics/<?= htmlspecialchars($customer['profile_pic'] ?? 'default.jpg') ?>"
                         alt="<?= htmlspecialchars($customer['name']) ?>">
                    <h3><?= htmlspecialchars($customer['name']) ?></h3>
                    <p><i class="fas fa-user"></i> Customer</p>
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