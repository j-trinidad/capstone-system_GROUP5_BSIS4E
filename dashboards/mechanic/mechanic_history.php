<?php
require '../../includes/session_check.php';
checkRole('mechanic');
require '../../includes/db_connect.php';

$mechanic_id = $_SESSION['user_id'];
$mechanic_name = $_SESSION['user_name'] ?? 'Mechanic';

// Messages count (unread)
$messagesStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$messagesStmt->execute([$mechanic_id]);
$messages = $messagesStmt->fetchColumn();

// Filter logic
$filter = $_GET['filter'] ?? 'all';

if ($filter === 'declined') {
    $where_clause = "b.mechanic_id = ? AND b.status = 'cancelled' AND b.cancelled_by = 'mechanic'";
} elseif ($filter === 'completed') {
    $where_clause = "b.mechanic_id = ? AND b.status = 'completed'";
} else { // all
    $where_clause = "b.mechanic_id = ? AND b.status IN ('completed','cancelled') AND (b.cancelled_by IS NULL OR b.cancelled_by IN ('mechanic','customer'))";
}

// Fetch bookings with service names
$stmt = $pdo->prepare("
    SELECT 
        b.*,
        u.first_name AS customer_first,
        u.last_name AS customer_last,
        u.email AS customer_email,
        s.name AS service_name,
        s.service_key
    FROM bookings b
    JOIN users u ON b.customer_id = u.id
    LEFT JOIN services s ON b.service_type = s.service_key
    WHERE $where_clause
    ORDER BY b.updated_at DESC
");
$stmt->execute([$mechanic_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>History - MotorService</title>
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
    --info: #0284c7;
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

/* HAMBURGER MENU BUTTON */
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

.hamburger-btn.active span:nth-child(1) {
    transform: rotate(45deg) translate(8px, 8px);
}

.hamburger-btn.active span:nth-child(2) {
    opacity: 0;
}

.hamburger-btn.active span:nth-child(3) {
    transform: rotate(-45deg) translate(7px, -7px);
}

/* SIDEBAR OVERLAY */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(26, 86, 219, 0.1);
    z-index: 999;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.sidebar-overlay.active {
    display: block;
    opacity: 1;
}

/* FIXED SIDEBAR */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
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

.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}

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

.sidebar nav a i {
    width: 20px;
    text-align: center;
}

.notification-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background-color: #ff0000;
    color: #ffffff;
    width: 18px;
    height: 18px;
    min-width: 18px;
    font-size: 10px;
    font-weight: 700;
    border-radius: 50%;
    margin-left: auto;
    line-height: 1;
    box-shadow: 0 0 8px rgba(255, 0, 0, 0.7);
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

/* MAIN CONTENT */
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

.main::-webkit-scrollbar {
    width: 8px;
}

.main::-webkit-scrollbar-track {
    background: rgba(26, 86, 219, 0.04);
}

.main::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 5px;
}

.main::-webkit-scrollbar-thumb:hover {
    background: var(--primary);
}

.header {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 30px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    display: flex;
    align-items: center;
    gap: 15px;
}

/* FILTER BUTTONS */
.filters {
    display: flex;
    gap: 12px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 10px 20px;
    background: rgba(26, 86, 219, 0.06);
    border: 1px solid var(--border);
    color: var(--text-secondary);
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.filter-btn:hover,
.filter-btn.active {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff;
    border-color: var(--primary);
}

/* BOOKING CARDS */
.bookings-container {
    display: block;
    margin-top: 20px;
}

.booking-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.08);
    transition: all 0.3s ease;
    animation: slideIn 0.4s ease;
    margin-bottom: 20px;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.booking-card:hover {
    transform: translateY(-3px);
    border-color: var(--primary);
    box-shadow: 0 12px 30px rgba(26, 86, 219, 0.15);
}

.booking-header {
    padding: 20px;
    background: linear-gradient(90deg, rgba(26, 86, 219, 0.06), rgba(30, 64, 175, 0.04));
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
}

.booking-header:hover {
    background: linear-gradient(90deg, rgba(26, 86, 219, 0.1), rgba(30, 64, 175, 0.08));
}

.booking-header-left {
    display: flex;
    align-items: center;
    gap: 15px;
    flex: 1;
}

.booking-icon {
    font-size: 28px;
    color: var(--primary);
}

.booking-info h4 {
    color: var(--text-primary);
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 5px;
}

.booking-info p {
    color: var(--text-secondary);
    font-size: 13px;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 6px;
}

.booking-header-right {
    text-align: right;
    min-width: 150px;
}

.booking-price {
    font-size: 20px;
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 8px;
}

.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.completed {
    background: rgba(5, 150, 105, 0.12);
    color: var(--success);
    border: 1px solid rgba(5, 150, 105, 0.3);
}

.status-badge.cancelled {
    background: rgba(220, 38, 38, 0.12);
    color: var(--error);
    border: 1px solid rgba(220, 38, 38, 0.3);
}

/* BOOKING DETAILS */
.booking-details {
    display: none;
    padding: 20px;
    background: rgba(26, 86, 219, 0.02);
}

.booking-details.show {
    display: block;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 0;
}

.detail-item {
    background: #f8faff;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid var(--border);
}

.detail-item label {
    color: var(--primary);
    font-weight: 700;
    font-size: 12px;
    display: block;
    margin-bottom: 6px;
}

.detail-item p {
    color: var(--text-primary);
    font-size: 13px;
    margin: 0;
    word-break: break-word;
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
}

.empty-state p {
    margin-bottom: 8px;
}

.empty-state small {
    color: #94a3b8;
    font-size: 12px;
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

    .header {
        font-size: 24px;
    }

    .details-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .hamburger-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 80%;
        max-width: 300px;
        height: 100vh;
        transform: translateX(-100%);
        flex-direction: column;
        padding: 25px 20px;
        overflow-y: auto;
    }

    .sidebar.active {
        transform: translateX(0);
    }

    .sidebar nav {
        flex-direction: column;
        gap: 8px;
    }

    .sidebar nav a {
        padding: 12px 16px;
        font-size: 14px;
    }

    .main {
        margin-left: 0;
        width: 100%;
        padding: 90px 20px 100px 20px;
        height: 100vh;
    }

    .header {
        font-size: 20px;
        margin-top: 0;
        margin-bottom: 20px;
    }

    .filters {
        gap: 8px;
        margin-bottom: 20px;
    }

    .filter-btn {
        padding: 8px 12px;
        font-size: 11px;
    }

    .booking-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
        padding: 15px;
    }

    .booking-header-left {
        width: 100%;
    }

    .booking-header-right {
        width: 100%;
        text-align: left;
    }

    .details-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .booking-card {
        margin-bottom: 15px;
    }

    .booking-icon {
        font-size: 24px;
    }

    .booking-info h4 {
        font-size: 15px;
    }

    .booking-info p {
        font-size: 12px;
    }

    .booking-price {
        font-size: 18px;
    }
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

<!-- SIDEBAR OVERLAY -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="logo">
        <i class="fas fa-wrench"></i>
        <span>MotorService</span>
    </div>

    <nav>
        <a href="mechanic_dashboard.php">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="service_request.php">
            <i class="fas fa-clipboard-list"></i> Service Requests
        </a>
        <a href="mechanic_history.php" class="active">
            <i class="fas fa-history"></i> History
        </a>
        <a href="message_center.php">
            <i class="fas fa-envelope"></i> Messages
            <?php if ($messages > 0): ?>
                <span class="notification-badge"><?= $messages ?></span>
            <?php endif; ?>
        </a>
        <a href="profile.php">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        <a href="change_password.php">
            <i class="fas fa-lock"></i> Change Password
        </a>
        <a href="report_absence.php">
            <i class="fas fa-calendar-times"></i> File Leave
        </a>
        <a href="../../logout.php" class="logout-btn" style="margin-top: auto;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>

    <div class="footer">v1.0 • <?= date('Y') ?></div>
</aside>

<!-- MAIN CONTENT -->
<main class="main">
    <div class="header">
        <i class="fas fa-history"></i> History
    </div>

    <!-- FILTER BUTTONS -->
    <div class="filters">
        <a href="?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
            <i class="fas fa-list"></i> All
        </a>
        <a href="?filter=completed" class="filter-btn <?= $filter === 'completed' ? 'active' : '' ?>">
            <i class="fas fa-check-circle"></i> Completed
        </a>
        <a href="?filter=declined" class="filter-btn <?= $filter === 'declined' ? 'active' : '' ?>">
            <i class="fas fa-times-circle"></i> Declined
        </a>
    </div>

    <!-- BOOKINGS CONTAINER -->
    <div class="bookings-container">
        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No bookings found for this filter.</p>
                <small>Completed and declined bookings will appear here.</small>
            </div>
        <?php else: ?>
            <?php foreach ($bookings as $b): ?>
                <?php
                $parts = json_decode($b['parts'], true) ?: [];
                $partsList = !empty($parts) ? implode(', ', array_map(fn($p) => htmlspecialchars($p['name']), $parts)) : 'None';
                $homeFee = ($b['service_location'] === 'home') ? 150 : 0;
                $total = $b['labor_fee'] + $homeFee + $b['service_fee'] + $b['parts_total'];
                $statusClass = ($b['status'] === 'completed') ? 'completed' : 'cancelled';
                $customerName = htmlspecialchars($b['customer_first'] . ' ' . $b['customer_last']);
                $customerEmail = htmlspecialchars($b['customer_email']);
                $serviceName = htmlspecialchars($b['service_name'] ?: $b['service_type']);
                ?>
                <div class="booking-card">
                    <div class="booking-header" onclick="toggleDetails(this)">
                        <div class="booking-header-left">
                            <div class="booking-icon">
                                <i class="fas fa-<?= $b['status'] === 'completed' ? 'check-circle' : 'times-circle' ?>"></i>
                            </div>
                            <div class="booking-info">
                                <h4>Booking #<?= $b['id'] ?></h4>
                                <p><strong><?= $customerName ?></strong> • <?= $serviceName ?></p>
                                <p><i class="fas fa-calendar"></i> <?= date('M d, Y h:i A', strtotime($b['schedule'])) ?></p>
                            </div>
                        </div>
                        <div class="booking-header-right">
                            <div class="booking-price">₱<?= number_format($total, 2) ?></div>
                            <span class="status-badge <?= $statusClass ?>">
                                <?php 
                                    if ($b['status'] === 'cancelled' && $b['cancelled_by'] === 'mechanic') {
                                        echo 'Declined';
                                    } else {
                                        echo ucfirst(str_replace('_', ' ', $b['status']));
                                    }
                                ?>
                            </span>
                        </div>
                    </div>

                    <div class="booking-details">
                        <div class="details-grid">
                            <div class="detail-item">
                                <label><i class="fas fa-envelope"></i> Customer Email</label>
                                <p><?= $customerEmail ?></p>
                            </div>
                            <div class="detail-item">
                                <label><i class="fas fa-motorcycle"></i> Motorcycle Brand</label>
                                <p><?= htmlspecialchars($b['brand']) ?></p>
                            </div>
                            <div class="detail-item">
                                <label><i class="fas fa-car"></i> Vehicle Type</label>
                                <p><?= htmlspecialchars($b['vehicle_type']) ?></p>
                            </div>
                            <div class="detail-item">
                                <label><i class="fas fa-map-marker-alt"></i> Service Location</label>
                                <p><?= ucfirst($b['service_location']) ?></p>
                            </div>
                            <?php if ($b['service_location'] === 'home'): ?>
                            <div class="detail-item">
                                <label><i class="fas fa-home"></i> Address</label>
                                <p><?= htmlspecialchars($b['service_address']) ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($b['tire_size'])): ?>
                            <div class="detail-item">
                                <label><i class="fas fa-circle"></i> Tire Size</label>
                                <p><?= htmlspecialchars($b['tire_size']) ?></p>
                            </div>
                            <?php endif; ?>
                            <div class="detail-item">
                                <label><i class="fas fa-dollar-sign"></i> Labor Fee</label>
                                <p>₱<?= number_format($b['labor_fee'], 2) ?></p>
                            </div>
                            <?php if ($homeFee > 0): ?>
                            <div class="detail-item">
                                <label><i class="fas fa-house-user"></i> Home Fee</label>
                                <p>₱<?= number_format($homeFee, 2) ?></p>
                            </div>
                            <?php endif; ?>
                            <div class="detail-item">
                                <label><i class="fas fa-receipt"></i> Service Fee</label>
                                <p>₱<?= number_format($b['service_fee'], 2) ?></p>
                            </div>
                            <div class="detail-item">
                                <label><i class="fas fa-box"></i> Parts Total</label>
                                <p>₱<?= number_format($b['parts_total'], 2) ?></p>
                            </div>
                            <div class="detail-item">
                                <label><i class="fas fa-tools"></i> Parts Used</label>
                                <p><?= $partsList ?></p>
                            </div>
                            <div class="detail-item">
                                <label><i class="fas fa-sticky-note"></i> Customer Note</label>
                                <p><?= htmlspecialchars($b['note'] ?: '-') ?></p>
                            </div>
                            <?php if (!empty($b['mechanic_note'])): ?>
                            <div class="detail-item">
                                <label><i class="fas fa-comment"></i> Mechanic Note</label>
                                <p><?= htmlspecialchars($b['mechanic_note']) ?></p>
                            </div>
                            <?php endif; ?>
                            <div class="detail-item">
                                <label><i class="fas fa-calendar-check"></i> Booked At</label>
                                <p><?= date('M d, Y h:i A', strtotime($b['created_at'])) ?></p>
                            </div>
                            <div class="detail-item">
                                <label><i class="fas fa-history"></i> Updated At</label>
                                <p><?= date('M d, Y h:i A', strtotime($b['updated_at'])) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<script>
// Hamburger menu toggle
const hamburgerBtn = document.getElementById('hamburger-btn');
const sidebar = document.getElementById('sidebar');
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

// Close sidebar when clicking a link on mobile
sidebar.querySelectorAll('nav a').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            hamburgerBtn.classList.remove('active');
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        }
    });
});

// Toggle details
function toggleDetails(element) {
    const details = element.nextElementSibling;
    details.classList.toggle('show');
}
</script>

</body>
</html>