<?php
require '../../includes/session_check.php';
checkRole('customer');
require '../../includes/db_connect.php';

$user_id = $_SESSION['user_id'];

// Get counts for badges
$notifStmt = $pdo->prepare("SELECT COUNT(*) FROM customer_notifications WHERE customer_id = ? AND is_read = 0");
$notifStmt->execute([$user_id]);
$unreadNotifications = $notifStmt->fetchColumn();

$msgStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$msgStmt->execute([$user_id]);
$messages = $msgStmt->fetchColumn();

// Fetch user info
$stmt = $pdo->prepare("SELECT first_name, last_name, email, display_name, profile_pic FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch saved addresses
$stmt = $pdo->prepare("SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if edit mode
$isEditing = isset($_GET['edit']);

// Handle update
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

    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, display_name = ?, profile_pic = ? WHERE id = ?");
    $stmt->execute([$first_name, $last_name, $display_name, $file_name, $user_id]);

    $_SESSION['user_name'] = $display_name ?: ($first_name . ' ' . $last_name);
    header("Location: customer_profile.php?updated=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Profile - MotorService</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary:        #1a56db;
    --secondary:      #1e40af;
    --dark-bg:        #f0f4ff;
    --card-bg:        #ffffff;
    --border:         rgba(26,86,219,0.2);
    --text-primary:   #1e293b;
    --text-secondary: #475569;
    --success:        #059669;
    --error:          #dc2626;
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
    box-shadow: 0 4px 15px rgba(26,86,219,0.4);
    transition: all 0.3s ease;
}

.hamburger-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(26,86,219,0.6);
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
    background: rgba(26,86,219,0.1);
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
    background: linear-gradient(180deg, #1e3a8a, #1a56db);
    border-right: 1px solid rgba(255,255,255,0.1);
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
    background: rgba(255,255,255,0.2);
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
    color: rgba(255,255,255,0.75);
    font-weight: 600;
    transition: all 0.3s ease;
    font-size: 14px;
    position: relative;
}

.sidebar nav a:hover,
.sidebar nav a.active {
    background: rgba(255,255,255,0.2);
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
    box-shadow: 0 0 8px rgba(255,0,0,0.7);
}

.sidebar .footer {
    margin-top: auto;
    font-size: 12px;
    color: rgba(255,255,255,0.5);
    text-align: center;
    padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.15);
}

.logout-btn {
    background: linear-gradient(135deg, #ff4444, #c82333) !important;
    color: var(--text-primary) !important;
    margin-top: auto !important;
    justify-content: center !important;
}

/* MAIN CONTENT */
.main {
    margin-left: 260px;
    padding: 40px;
    width: calc(100% - 260px);
    height: 100vh;
    overflow-y: auto;
    background: linear-gradient(135deg, #f0f4ff, #e8eeff);
    display: flex;
    flex-direction: column;
    align-items: center;
    -webkit-overflow-scrolling: touch;
}

.main::-webkit-scrollbar {
    width: 8px;
}

.main::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 4px;
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
    width: 100%;
    max-width: 650px;
}

.profile-container {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 30px;
    width: 100%;
    max-width: 650px;
    box-shadow: 0 4px 15px rgba(26,86,219,0.08);
}

.profile-header {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 25px;
    border-bottom: 1px solid var(--border);
}

.profile-pic {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid var(--primary);
    box-shadow: 0 8px 20px rgba(26,86,219,0.25);
    margin-bottom: 15px;
}

.profile-name {
    font-size: 22px;
    color: var(--text-primary);
    margin: 0;
    font-weight: 700;
}

.profile-section {
    background: rgba(26,86,219,0.04);
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    border: 1px solid var(--border);
    border-left: 4px solid var(--primary);
    position: relative;
    overflow: hidden;
}

.section-title {
    font-size: 14px;
    color: var(--primary);
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid rgba(26,86,219,0.07);
    align-items: center;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.info-value {
    color: var(--text-primary);
    font-weight: 600;
    font-size: 13px;
}

.addresses-list {
    max-height: 250px;
    overflow-y: auto;
}

.addresses-list::-webkit-scrollbar {
    width: 5px;
}

.addresses-list::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 3px;
}

.address-card {
    background: rgba(26,86,219,0.04);
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 10px;
    border: 1px solid var(--border);
    transition: all 0.3s;
}

.address-card:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(26,86,219,0.1);
}

.address-card p {
    margin: 4px 0;
    font-size: 13px;
    color: var(--text-secondary);
}

.no-addresses {
    text-align: center;
    padding: 25px;
    color: var(--text-secondary);
}

.no-addresses i {
    font-size: 35px;
    margin-bottom: 8px;
    display: block;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    opacity: 0.4;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    font-size: 11px;
    color: var(--primary);
    font-weight: 700;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
}

input[type="text"],
input[type="file"] {
    width: 100%;
    background: #f8faff;
    border: 1px solid var(--border);
    border-radius: 9px;
    color: var(--text-primary);
    padding: 11px 14px;
    font-size: 13px;
    font-family: 'Outfit', sans-serif;
    transition: all 0.3s ease;
}

input[type="text"]:focus,
input[type="file"]:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(26,86,219,0.12);
    background: #ffffff;
}

input:disabled {
    background: rgba(26,86,219,0.04);
    color: var(--text-secondary);
    cursor: not-allowed;
}

.button-group {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-top: 25px;
}

.btn {
    padding: 10px 25px;
    border-radius: 10px;
    border: none;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 12px rgba(26,86,219,0.2);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(26,86,219,0.35);
}

.btn-secondary {
    background: transparent;
    color: var(--text-secondary);
    border: 1px solid var(--border);
    box-shadow: none;
}

.btn-secondary:hover {
    background: rgba(26,86,219,0.06);
    color: var(--text-primary);
    transform: translateY(-2px);
}

.alert {
    background: rgba(5,150,105,0.1);
    border: 1px solid #059669;
    color: #059669;
    padding: 12px 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.manage-link {
    text-align: center;
    margin-top: 12px;
}

.manage-link a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 700;
    font-size: 12px;
    transition: 0.3s ease;
}

.manage-link a:hover {
    color: var(--secondary);
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15,23,42,0.7);
    justify-content: center;
    align-items: center;
    z-index: 9999;
    animation: modalFadeIn 0.3s ease;
}

@keyframes modalFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal.show {
    display: flex;
}

.modal-content {
    background: var(--card-bg);
    padding: 30px;
    border-radius: 16px;
    text-align: center;
    max-width: 350px;
    color: var(--text-primary);
    box-shadow: 0 20px 60px rgba(26,86,219,0.2);
    border: 1px solid var(--border);
    animation: modalSlideIn 0.35s ease;
}

@keyframes modalSlideIn {
    from { transform: scale(0.93) translateY(-16px); opacity: 0; }
    to { transform: scale(1) translateY(0); opacity: 1; }
}

.modal-content h3 {
    font-size: 18px;
    margin-bottom: 12px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 700;
}

.modal-content p {
    color: var(--text-secondary);
    margin-bottom: 20px;
    font-size: 13px;
}

.modal-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.modal-buttons button {
    padding: 9px 20px;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    font-weight: 700;
    transition: 0.3s ease;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-transform: uppercase;
}

.modal-confirm {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(26,86,219,0.25);
}

.modal-confirm:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(26,86,219,0.35);
}

.modal-cancel {
    background: transparent;
    color: var(--text-secondary);
    border: 1px solid var(--border);
}

.modal-cancel:hover {
    background: rgba(26,86,219,0.06);
    color: var(--text-primary);
}

/* MOBILE RESPONSIVE */
@media (max-width: 768px) {
    .hamburger-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .sidebar {
        transform: translateX(-100%);
        height: 100vh;
        max-height: 100vh;
        padding-bottom: 20px;
    }

    .sidebar.active {
        transform: translateX(0);
    }

    .sidebar nav {
        padding-bottom: 20px;
    }

    .main {
        margin-left: 0;
        width: 100%;
        padding: 80px 20px 150px;
        height: 100vh;
        align-items: stretch;
    }

    .header {
        max-width: 100%;
        font-size: 20px;
        margin-top: 20px;
    }

    .profile-container {
        max-width: 100%;
        padding: 20px;
        margin-bottom: 30px;
    }

    .button-group {
        flex-direction: column;
        margin-bottom: 60px;
    }

    .btn {
        justify-content: center;
        width: 100%;
        padding: 16px 25px;
    }

    .info-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }

    .modal-content {
        width: 95%;
        max-width: 95%;
    }
}

@media (max-width: 480px) {
    .main {
        padding: 70px 15px 180px;
    }

    .profile-container {
        padding: 20px 15px;
        margin-bottom: 40px;
    }

    .button-group {
        margin-bottom: 80px;
    }

    .btn {
        padding: 18px 25px;
        font-size: 13px;
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
        <a href="customer_messages.php">
            <i class="fas fa-envelope"></i> Messages
            <?php if ($messages > 0): ?>
                <span class="notification-badge"><?= $messages ?></span>
            <?php endif; ?>
        </a>
        <a href="customer_my_receipts.php">
            <i class="fas fa-receipt"></i> My Receipts
        </a>
        <a href="customer_address.php">
            <i class="fas fa-map-marker-alt"></i> Address
        </a>
        <a href="customer_profile.php" class="active">
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
        <i class="fas fa-user-circle"></i> My Profile
    </div>

    <div class="profile-container">
        <div class="profile-header">
            <?php 
                $profile_img = $user['profile_pic'] 
                    ? "../../uploads/profile_pics/" . htmlspecialchars($user['profile_pic'])
                    : "../../assets/img/default_profile.png";
            ?>
            <img id="previewImage" src="<?= $profile_img ?>" class="profile-pic" alt="Profile Picture">
            <h2 class="profile-name">
                <?= htmlspecialchars($user['display_name'] ?: ($user['first_name'] . ' ' . $user['last_name'])) ?>
            </h2>
        </div>

        <?php if (isset($_GET['updated'])): ?>
        <div class="alert">
            <i class="fas fa-check-circle"></i>
            <span>Profile updated successfully!</span>
        </div>
        <?php endif; ?>

        <?php if (!$isEditing): ?>
        <!-- VIEW MODE -->
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

        <div class="profile-section">
            <h3 class="section-title"><i class="fas fa-map-marker-alt"></i> Saved Addresses</h3>
            <div class="addresses-list">
                <?php if (!$addresses): ?>
                    <div class="no-addresses">
                        <i class="fas fa-inbox"></i>
                        <p>No saved addresses yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($addresses as $addr): ?>
                        <div class="address-card">
                            <p><i class="fas fa-home"></i> <strong><?= htmlspecialchars($addr['address']) ?></strong></p>
                            <p><i class="fas fa-map"></i> <?= htmlspecialchars($addr['barangay']) ?>, <?= htmlspecialchars($addr['city']) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="manage-link">
                <a href="customer_address.php"><i class="fas fa-edit"></i> Manage Addresses</a>
            </div>
        </div>

        <div class="button-group">
            <a href="?edit=1" class="btn btn-primary"><i class="fas fa-edit"></i> Edit Profile</a>
        </div>

        <?php else: ?>
        <!-- EDIT MODE -->
        <form id="profileForm" method="POST" enctype="multipart/form-data">
            <div class="profile-section">
                <h3 class="section-title"><i class="fas fa-camera"></i> Profile Picture</h3>
                <div class="form-group">
                    <label for="profile_pic">Upload New Photo</label>
                    <input type="file" name="profile_pic" id="profileInput" accept="image/*">
                </div>
            </div>

            <div class="profile-section">
                <h3 class="section-title"><i class="fas fa-user"></i> Personal Information</h3>
                
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" name="first_name" id="first_name" 
                           value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" 
                           placeholder="Enter first name" required>
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" name="last_name" id="last_name" 
                           value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" 
                           placeholder="Enter last name" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address (Read Only)</label>
                    <input type="text" name="email" id="email" 
                           value="<?= htmlspecialchars($user['email'] ?? '') ?>" 
                           placeholder="Your email address" disabled>
                </div>

                <div class="form-group">
                    <label for="display_name">Display Name (Optional)</label>
                    <input type="text" name="display_name" id="display_name" 
                           value="<?= htmlspecialchars($user['display_name'] ?? '') ?>" 
                           placeholder="Enter display name">
                </div>
            </div>

            <div class="button-group">
                <button type="button" class="btn btn-primary" id="saveBtn"><i class="fas fa-save"></i> Save Changes</button>
                <a href="customer_profile.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</main>

<!-- Confirmation Modal -->
<div class="modal" id="confirmModal">
    <div class="modal-content">
        <h3><i class="fas fa-question-circle"></i> Save Changes?</h3>
        <p>Are you sure you want to save these profile updates?</p>
        <div class="modal-buttons">
            <button class="modal-confirm" id="confirmYes"><i class="fas fa-check"></i> Yes, Save</button>
            <button class="modal-cancel" id="confirmNo"><i class="fas fa-times"></i> Cancel</button>
        </div>
    </div>
</div>

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

// Preview uploaded image
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

// Confirmation popup
const saveBtn = document.getElementById('saveBtn');
const modal = document.getElementById('confirmModal');
const yesBtn = document.getElementById('confirmYes');
const noBtn = document.getElementById('confirmNo');

saveBtn?.addEventListener('click', () => {
    modal.classList.add('show');
});

noBtn?.addEventListener('click', () => {
    modal.classList.remove('show');
});

yesBtn?.addEventListener('click', () => {
    document.getElementById('profileForm').submit();
});

modal?.addEventListener('click', (e) => {
    if (e.target === modal) {
        modal.classList.remove('show');
    }
});

// Prevent zoom on double tap (iOS)
let lastTouchEnd = 0;
document.addEventListener('touchend', function (event) {
    const now = (new Date()).getTime();
    if (now - lastTouchEnd <= 300) {
        event.preventDefault();
    }
    lastTouchEnd = now;
}, false);
</script>

</body>
</html>