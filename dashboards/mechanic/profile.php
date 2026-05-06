<?php
require '../../includes/session_check.php';
checkRole('mechanic');
require '../../includes/db_connect.php';

$user_id = $_SESSION['user_id'];

// Fetch user info
$stmt = $pdo->prepare("SELECT first_name, last_name, email, display_name, profile_pic FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Messages count (unread)
$messagesStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$messagesStmt->execute([$user_id]);
$messages = $messagesStmt->fetchColumn();

// Check if edit mode
$isEditing = isset($_GET['edit']);

// ✅ Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');

    // Handle file upload
    $file_name = $user['profile_pic'];
    if (!empty($_FILES['profile_pic']['name'])) {
        $file = $_FILES['profile_pic'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_name = "profile_{$user_id}_" . time() . "." . $ext;
            $upload_dir = "../../uploads/profile_pics/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            move_uploaded_file($file['tmp_name'], $upload_dir . $new_name);
            $file_name = $new_name;
        }
    }

    // Update all fields (email is NOT editable)
    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, display_name = ?, profile_pic = ? WHERE id = ?");
    $stmt->execute([$first_name, $last_name, $display_name, $file_name, $user_id]);

    $_SESSION['user_name'] = $display_name ?: ($first_name . ' ' . $last_name);
    header("Location: profile.php?updated=1");
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>My Profile - MotorService</title>
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
    -webkit-overflow-scrolling: touch;
}

.sidebar::-webkit-scrollbar { width: 6px; }
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
    background: linear-gradient(135deg, #f0f4ff, #e8eeff);
    -webkit-overflow-scrolling: touch;
}

.main::-webkit-scrollbar { width: 8px; }
.main::-webkit-scrollbar-track { background: rgba(26, 86, 219, 0.04); }
.main::-webkit-scrollbar-thumb { background: var(--border); border-radius: 5px; }
.main::-webkit-scrollbar-thumb:hover { background: var(--primary); }

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
}

.back-link:hover {
    background: rgba(26, 86, 219, 0.12);
    transform: translateX(-5px);
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.15);
}

.profile-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 15px 40px rgba(26, 86, 219, 0.10), 0 0 30px rgba(26, 86, 219, 0.06);
    max-width: 800px;
    margin: 0 auto;
}

.profile-header {
    text-align: center;
    margin-bottom: 40px;
    padding-bottom: 30px;
    border-bottom: 2px solid var(--border);
}

.profile-title {
    font-size: 28px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    font-weight: 700;
}

.profile-pic {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid var(--primary);
    box-shadow: 0 8px 20px rgba(26, 86, 219, 0.25);
    margin-bottom: 20px;
    transition: transform 0.3s ease;
}

.profile-pic:hover {
    transform: scale(1.08);
}

.profile-name {
    font-size: 24px;
    color: var(--text-primary);
    margin: 15px 0;
    font-weight: 600;
}

.alert {
    background: rgba(5, 150, 105, 0.1);
    border: 1px solid var(--success);
    color: var(--success);
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideInDown 0.5s ease;
}

@keyframes slideInDown {
    from { transform: translateY(-20px); opacity: 0; }
    to   { transform: translateY(0); opacity: 1; }
}

.profile-section {
    background: rgba(26, 86, 219, 0.04);
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
    border-left: 4px solid var(--primary);
}

.section-title {
    font-size: 18px;
    color: var(--primary);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 700;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid rgba(26, 86, 219, 0.1);
    align-items: center;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    color: var(--text-secondary);
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-value {
    color: var(--text-primary);
    font-weight: 600;
    word-break: break-word;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 14px;
    color: var(--primary);
    font-weight: 600;
    margin-bottom: 8px;
}

input[type="text"],
input[type="file"] {
    width: 100%;
    background: #f8faff;
    border: 2px solid var(--border);
    border-radius: 10px;
    color: var(--text-primary);
    padding: 12px 15px;
    font-size: 14px;
    font-family: 'Outfit', sans-serif;
    transition: all 0.3s ease;
}

input[type="text"]:focus,
input[type="file"]:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 10px rgba(26, 86, 219, 0.15);
    background: #eef2ff;
}

input[type="text"]:disabled {
    background: rgba(26, 86, 219, 0.04);
    opacity: 0.6;
    cursor: not-allowed;
}

input::placeholder {
    color: #94a3b8;
}

.button-group {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin-top: 30px;
    flex-wrap: wrap;
}

.btn {
    padding: 14px 30px;
    border-radius: 10px;
    border: none;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.3);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(26, 86, 219, 0.45);
}

.btn-secondary {
    background: rgba(26, 86, 219, 0.06);
    color: var(--primary);
    border: 2px solid var(--border);
}

.btn-secondary:hover {
    background: rgba(26, 86, 219, 0.12);
    border-color: var(--primary);
    transform: translateY(-3px);
}

/* MODAL */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(26, 86, 219, 0.15);
    justify-content: center;
    align-items: center;
    z-index: 9999;
    animation: modalFadeIn 0.3s ease;
}

@keyframes modalFadeIn {
    from { opacity: 0; }
    to   { opacity: 1; }
}

.modal.show {
    display: flex;
}

.modal-content {
    background: #ffffff;
    padding: 35px;
    border-radius: 20px;
    text-align: center;
    max-width: 400px;
    width: 90%;
    color: var(--text-primary);
    box-shadow: 0 15px 40px rgba(26, 86, 219, 0.2);
    border: 1px solid var(--border);
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from { transform: translateY(-50px); opacity: 0; }
    to   { transform: translateY(0); opacity: 1; }
}

.modal-content h3 {
    font-size: 20px;
    margin-bottom: 15px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.modal-content p {
    color: var(--text-secondary);
    margin-bottom: 25px;
    font-size: 14px;
}

.modal-buttons {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

.modal-buttons button {
    padding: 12px 25px;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    font-weight: 700;
    transition: 0.3s ease;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-confirm {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff;
}

.modal-confirm:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(26, 86, 219, 0.4);
}

.modal-cancel {
    background: rgba(26, 86, 219, 0.06);
    color: var(--text-primary);
    border: 2px solid var(--border);
}

.modal-cancel:hover {
    background: rgba(26, 86, 219, 0.12);
    transform: translateY(-3px);
}

@media (max-width: 1024px) {
    .sidebar { width: 220px; }
    .main { margin-left: 220px; width: calc(100% - 220px); padding: 30px 20px; }
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
    }

    .sidebar.active { transform: translateX(0); }

    .main {
        margin-left: 0;
        width: 100%;
        padding: 80px 20px 150px;
    }

    .profile-card {
        padding: 25px 20px;
        margin-bottom: 30px;
    }

    .profile-title { font-size: 22px; }

    .profile-pic {
        width: 120px;
        height: 120px;
        border-width: 4px;
    }

    .profile-name { font-size: 20px; }

    .button-group {
        flex-direction: column;
        margin-bottom: 60px;
    }

    .btn {
        width: 100%;
        padding: 16px 30px;
    }

    .info-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }

    .profile-section { padding: 20px 15px; }
    .section-title { font-size: 16px; }
}

@media (max-width: 480px) {
    .main { padding: 70px 15px 180px; }

    .profile-card {
        padding: 20px 15px;
        margin-bottom: 40px;
    }

    .profile-pic { width: 100px; height: 100px; }
    .profile-name { font-size: 18px; }

    .button-group { margin-bottom: 80px; }

    .btn { padding: 18px 30px; font-size: 15px; }

    .modal-content { padding: 25px 20px; }

    .modal-buttons { flex-direction: column; }
    .modal-buttons button { width: 100%; }
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
        <a href="mechanic_dashboard.php" class="<?= $currentPage == 'mechanic_dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="service_request.php" class="<?= $currentPage == 'service_request.php' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-list"></i> Service Requests
        </a>
        <a href="mechanic_history.php" class="<?= $currentPage == 'mechanic_history.php' ? 'active' : '' ?>">
            <i class="fas fa-history"></i> History
        </a>
        <a href="message_center.php" class="<?= $currentPage == 'message_center.php' ? 'active' : '' ?>">
            <i class="fas fa-envelope"></i> Messages
            <?php if ($messages > 0): ?>
                <span class="notification-badge"><?= $messages ?></span>
            <?php endif; ?>
        </a>
        <a href="profile.php" class="<?= $currentPage == 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        <a href="change_password.php" class="<?= $currentPage == 'change_password.php' ? 'active' : '' ?>">
            <i class="fas fa-lock"></i> Change Password
        </a>
        <a href="report_absence.php" class="<?= $currentPage == 'report_absence.php' ? 'active' : '' ?>">
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
    <div class="profile-card">
        <div class="profile-header">
            <h2 class="profile-title">
                <i class="fas fa-user-circle"></i> My Profile
            </h2>
            <?php 
                $profile_img = $user['profile_pic'] 
                    ? "../../uploads/profile_pics/" . htmlspecialchars($user['profile_pic'])
                    : "../../assets/img/default_profile.png";
            ?>
            <img id="previewImage" src="<?= $profile_img ?>" class="profile-pic" alt="Profile Picture">
            <div class="profile-name">
                <?= htmlspecialchars($user['display_name'] ?: ($user['first_name'] . ' ' . $user['last_name'])) ?>
            </div>
        </div>

        <?php if (isset($_GET['updated'])): ?>
        <div class="alert">
            <i class="fas fa-check-circle"></i>
            <span>Profile updated successfully!</span>
        </div>
        <?php endif; ?>

        <?php if (!$isEditing): ?>
        <!-- ✅ VIEW MODE -->
        <div class="profile-section">
            <h3 class="section-title"><i class="fas fa-user"></i> Personal Information</h3>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-user"></i> First Name</span>
                <span class="info-value"><?= htmlspecialchars($user['first_name']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-user"></i> Last Name</span>
                <span class="info-value"><?= htmlspecialchars($user['last_name']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-envelope"></i> Email Address</span>
                <span class="info-value"><?= htmlspecialchars($user['email']) ?></span>
            </div>
            <?php if ($user['display_name']): ?>
            <div class="info-row">
                <span class="info-label"><i class="fas fa-tag"></i> Display Name</span>
                <span class="info-value"><?= htmlspecialchars($user['display_name']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="button-group">
            <a href="?edit=1" class="btn btn-primary"><i class="fas fa-edit"></i> Edit Profile</a>
        </div>

        <?php else: ?>
        <!-- ✅ EDIT MODE -->
        <form id="profileForm" method="POST" enctype="multipart/form-data">
            <div class="profile-section">
                <h3 class="section-title"><i class="fas fa-camera"></i> Profile Picture</h3>
                <div class="form-group">
                    <label for="profile_pic"><i class="fas fa-upload"></i> Upload New Photo</label>
                    <input type="file" name="profile_pic" id="profileInput" accept="image/*">
                </div>
            </div>

            <div class="profile-section">
                <h3 class="section-title"><i class="fas fa-user"></i> Personal Information</h3>
                
                <div class="form-group">
                    <label for="first_name"><i class="fas fa-user"></i> First Name</label>
                    <input type="text" name="first_name" id="first_name" 
                           value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" 
                           placeholder="Enter first name" required>
                </div>

                <div class="form-group">
                    <label for="last_name"><i class="fas fa-user"></i> Last Name</label>
                    <input type="text" name="last_name" id="last_name" 
                           value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" 
                           placeholder="Enter last name" required>
                </div>

                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email Address (Read Only)</label>
                    <input type="text" name="email" id="email" 
                           value="<?= htmlspecialchars($user['email'] ?? '') ?>" 
                           placeholder="Your email address" disabled>
                </div>

                <div class="form-group">
                    <label for="display_name"><i class="fas fa-tag"></i> Display Name (Optional)</label>
                    <input type="text" name="display_name" id="display_name" 
                           value="<?= htmlspecialchars($user['display_name'] ?? '') ?>" 
                           placeholder="Enter display name">
                </div>
            </div>

            <div class="button-group">
                <button type="button" class="btn btn-primary" id="saveBtn">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="profile.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</main>

<!-- ✅ Confirmation Modal -->
<div class="modal" id="confirmModal">
    <div class="modal-content">
        <h3><i class="fas fa-question-circle"></i> Save Changes?</h3>
        <p>Are you sure you want to save these profile updates?</p>
        <div class="modal-buttons">
            <button class="modal-confirm" id="confirmYes">
                <i class="fas fa-check"></i> Yes, Save
            </button>
            <button class="modal-cancel" id="confirmNo">
                <i class="fas fa-times"></i> Cancel
            </button>
        </div>
    </div>
</div>

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

document.getElementById('profileInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(ev) {
            document.getElementById('previewImage').src = ev.target.result;
        }
        reader.readAsDataURL(file);
    }
});

const saveBtn = document.getElementById('saveBtn');
const modal   = document.getElementById('confirmModal');
const yesBtn  = document.getElementById('confirmYes');
const noBtn   = document.getElementById('confirmNo');

saveBtn?.addEventListener('click', () => { modal.classList.add('show'); });
noBtn?.addEventListener('click',   () => { modal.classList.remove('show'); });
yesBtn?.addEventListener('click',  () => { document.getElementById('profileForm').submit(); });

modal?.addEventListener('click', (e) => {
    if (e.target === modal) modal.classList.remove('show');
});

let lastTouchEnd = 0;
document.addEventListener('touchend', function (event) {
    const now = (new Date()).getTime();
    if (now - lastTouchEnd <= 300) event.preventDefault();
    lastTouchEnd = now;
}, false);

console.log('✅ Mechanic Profile page initialized');
</script>

</body>
</html>