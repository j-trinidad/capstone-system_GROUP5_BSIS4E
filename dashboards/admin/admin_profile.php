<?php
require '../../includes/session_check.php';
checkRole('admin');
require '../../includes/db_connect.php';

$user_id = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Fetch admin info
$stmt = $pdo->prepare("SELECT first_name, last_name, email, profile_pic FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if edit mode
$isEditing = isset($_GET['edit']);

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));

    if (empty($first_name) || empty($last_name) || empty($email)) {
        $message = "All fields are required!";
        $messageType = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email address!";
        $messageType = "error";
    } else {
        // Check if email is already used by another user
        $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkEmail->execute([$email, $user_id]);
        if ($checkEmail->fetch()) {
            $message = "This email is already in use!";
            $messageType = "error";
        } else {
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

            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, profile_pic = ? WHERE id = ?");
            $stmt->execute([$first_name, $last_name, $email, $file_name, $user_id]);

            $_SESSION['user_name'] = $first_name . ' ' . $last_name;
            header("Location: admin_profile.php?updated=1");
            exit;
        }
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Profile - MotorService</title>
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

* { margin: 0; padding: 0; box-sizing: border-box; }

html, body {
    height: 100%;
    font-family: 'Outfit', sans-serif;
    background: linear-gradient(135deg, var(--dark-bg), #1a1f3a);
    color: var(--text-primary);
    overflow: hidden;
}

a { color: inherit; text-decoration: none; }

/* HAMBURGER BUTTON */
.hamburger-btn {
    display: none;
    position: fixed; top: 20px; left: 20px; z-index: 1001;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border: none; width: 50px; height: 50px; border-radius: 12px;
    cursor: pointer; box-shadow: 0 4px 15px rgba(255,140,0,0.4);
    transition: all 0.3s ease;
}
.hamburger-btn:hover { transform: scale(1.05); box-shadow: 0 6px 20px rgba(255,140,0,0.6); }
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

/* SIDEBAR */
.sidebar {
    position: fixed;
    top: 0; left: 0;
    width: 260px; height: 100vh;
    overflow-y: auto;
    background: linear-gradient(180deg, #0f1419 0%, #1a1f3a 100%);
    border-right: 1px solid var(--border);
    display: flex; flex-direction: column;
    padding: 25px 20px;
    z-index: 1000;
    transition: transform 0.3s ease;
}
.sidebar::-webkit-scrollbar { width: 6px; }
.sidebar::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

.logo {
    font-size: 1.6rem; font-weight: 700;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 35px; letter-spacing: 0.5px;
    display: flex; align-items: center; gap: 10px;
}
.logo i { font-size: 24px; }

.sidebar nav { display: flex; flex-direction: column; gap: 8px; flex: 1; }
.sidebar nav a {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px; border-radius: 10px;
    color: var(--text-secondary); font-weight: 600;
    transition: all 0.3s ease; font-size: 14px;
}
.sidebar nav a:hover, .sidebar nav a.active {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #1a1f3a; transform: translateX(5px);
}
.sidebar nav a i { width: 20px; text-align: center; font-size: 16px; }

.logout-btn {
    background: linear-gradient(135deg, #ff4444, #c82333) !important;
    color: var(--text-primary) !important;
    margin-top: auto !important; justify-content: center !important;
}
.logout-btn:hover { background: linear-gradient(135deg, #ff6666, #d44444) !important; }

.sidebar .footer {
    margin-top: auto; font-size: 12px; color: var(--text-secondary);
    text-align: center; padding-top: 20px; border-top: 1px solid var(--border);
}

/* MAIN */
.main {
    margin-left: 260px;
    padding: 40px;
    width: calc(100% - 260px);
    height: 100vh; overflow-y: auto;
    background: linear-gradient(135deg, var(--dark-bg), #1a1f3a);
    display: flex; flex-direction: column; align-items: center;
}
.main::-webkit-scrollbar { width: 8px; }
.main::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

h1 {
    font-size: 32px; font-weight: 700; margin-bottom: 30px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
    display: flex; align-items: center; gap: 15px;
    width: 100%; max-width: 700px;
}
h1 i { font-size: 36px; }

.profile-card {
    background: var(--card-bg); border: 1px solid var(--border);
    border-radius: 12px; padding: 30px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
    max-width: 700px; width: 100%;
}

.profile-header {
    text-align: center; margin-bottom: 30px;
    padding-bottom: 25px; border-bottom: 2px solid var(--border);
}

.profile-pic {
    width: 150px; height: 150px; border-radius: 50%;
    object-fit: cover; border: 5px solid var(--primary);
    box-shadow: 0 8px 20px rgba(255,140,0,0.4);
    margin-bottom: 20px; transition: transform 0.3s ease;
}
.profile-pic:hover { transform: scale(1.08); }

.profile-name { font-size: 24px; color: var(--text-primary); font-weight: 700; margin-bottom: 5px; }
.profile-role { color: var(--text-secondary); font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }

.profile-section {
    background: rgba(255,140,0,0.05); padding: 20px;
    border-radius: 10px; margin-bottom: 20px; border-left: 5px solid var(--primary);
}

.section-title {
    font-size: 14px; color: var(--primary); text-transform: uppercase;
    letter-spacing: 0.5px; font-weight: 700; margin-bottom: 15px;
    display: flex; align-items: center; gap: 8px;
}

.info-row {
    display: flex; justify-content: space-between;
    padding: 10px 0; border-bottom: 1px solid var(--border); align-items: center;
    gap: 10px;
}
.info-row:last-child { border-bottom: none; }
.info-label { color: var(--text-secondary); font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.info-value { color: var(--text-primary); font-weight: 600; text-align: right; word-break: break-all; }

.form-group { margin-bottom: 20px; }
.form-group label {
    display: block; font-size: 13px; color: var(--primary);
    font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;
}
.form-group input, .form-group textarea {
    width: 100%; padding: 12px 15px;
    background: rgba(255,255,255,0.05); border: 1px solid var(--border);
    border-radius: 8px; color: var(--text-primary);
    font-size: 13px; font-family: 'Outfit', sans-serif; transition: all 0.3s ease;
}
.form-group input:focus, .form-group textarea:focus {
    border-color: var(--primary); outline: none;
    background: rgba(255,140,0,0.1); box-shadow: 0 0 10px rgba(255,140,0,0.2);
}
.form-group input[type="file"] { padding: 10px; }

.button-group { display: flex; gap: 12px; justify-content: center; margin-top: 25px; flex-wrap: wrap; }

.btn {
    padding: 12px 30px; border-radius: 8px; border: none;
    font-weight: 700; cursor: pointer; transition: all 0.3s ease;
    font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;
    display: flex; align-items: center; gap: 8px;
}
.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #1a1f3a; box-shadow: 0 4px 15px rgba(255,140,0,0.3);
}
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(255,140,0,0.4); }
.btn-secondary {
    background: rgba(255,140,0,0.1); color: var(--primary); border: 1px solid var(--primary);
}
.btn-secondary:hover { background: rgba(255,140,0,0.2); transform: translateY(-2px); }

.message {
    padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px;
    animation: slideDown 0.3s ease; width: 100%; max-width: 700px;
}
@keyframes slideDown { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }
.message.success { background: rgba(0,208,132,0.15); border: 1px solid var(--success); color: var(--success); }
.message.error   { background: rgba(255,71,87,0.15);  border: 1px solid var(--error);   color: var(--error); }

.modal {
    display: none; position: fixed; top: 0; left: 0;
    width: 100%; height: 100%; background: rgba(0,0,0,0.8);
    justify-content: center; align-items: center;
    z-index: 9999; animation: modalFadeIn 0.3s ease;
}
@keyframes modalFadeIn { from{opacity:0} to{opacity:1} }
.modal.show { display: flex; }
.modal-content {
    background: var(--card-bg); padding: 30px; border-radius: 12px;
    text-align: center; max-width: 400px; width: 90%;
    color: var(--text-primary); box-shadow: 0 15px 40px rgba(0,0,0,0.8);
    border: 1px solid var(--border);
}
.modal-content h3 { font-size: 18px; margin-bottom: 15px; color: var(--primary); }
.modal-content p  { color: var(--text-secondary); margin-bottom: 25px; font-size: 13px; }
.modal-buttons { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
.modal-buttons button {
    padding: 10px 25px; border-radius: 8px; border: none;
    cursor: pointer; font-weight: 700; transition: all 0.3s ease;
    font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;
}
.btn-confirm { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #1a1f3a; }
.btn-confirm:hover { transform: scale(1.05); }
.btn-cancel { background: rgba(255,255,255,0.1); color: var(--text-primary); border: 1px solid var(--border); }
.btn-cancel:hover { background: rgba(255,255,255,0.2); }

/* ── RESPONSIVE ── */
@media (max-width: 1024px) {
    .sidebar { width: 220px; padding: 20px; }
    .main { margin-left: 220px; width: calc(100% - 220px); padding: 30px 20px; }
    h1 { font-size: 24px; }
}

@media (max-width: 768px) {
    .hamburger-btn { display: block; }
    .sidebar { transform: translateX(-100%); }
    .sidebar.active { transform: translateX(0); }
    .sidebar nav { padding-bottom: 20px; }

    .main {
        margin-left: 0; width: 100%;
        padding: 80px 20px 20px 20px;
        align-items: center;
    }

    h1 { font-size: 20px; margin-top: 10px; }

    .profile-card { padding: 20px; }
    .profile-pic  { width: 110px; height: 110px; }
    .profile-name { font-size: 20px; }

    .info-row { flex-direction: column; align-items: flex-start; gap: 4px; }
    .info-value { text-align: left; }

    .button-group { flex-direction: column; }
    .btn { width: 100%; justify-content: center; }

    .modal-content { padding: 20px; }
    .modal-buttons { flex-direction: column; }
    .modal-buttons button { width: 100%; }
}

@media (max-width: 480px) {
    .main { padding: 75px 15px 15px 15px; }
    .profile-section { padding: 15px; }
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
    <h1><i class="fas fa-user-circle"></i> My Profile</h1>

    <?php if (isset($_GET['updated'])): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i>
            <span>Profile updated successfully!</span>
        </div>
    <?php elseif (!empty($message)): ?>
        <div class="message <?= $messageType ?>">
            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
    <?php endif; ?>

    <div class="profile-card">
        <?php 
            $profile_img = $user['profile_pic'] 
                ? "../../uploads/profile_pics/" . htmlspecialchars($user['profile_pic'])
                : "../../assets/img/default_profile.png";
        ?>

        <?php if (!$isEditing): ?>
        <!-- VIEW MODE -->
        <div class="profile-header">
            <img id="previewImage" src="<?= $profile_img ?>" class="profile-pic" alt="Profile Picture">
            <div class="profile-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
            <div class="profile-role">Administrator</div>
        </div>

        <div class="profile-section">
            <div class="section-title"><i class="fas fa-user"></i> Personal Information</div>
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
        </div>

        <div class="button-group">
            <a href="?edit=1" class="btn btn-primary"><i class="fas fa-edit"></i> Edit Profile</a>
        </div>

        <?php else: ?>
        <!-- EDIT MODE -->
        <div class="profile-header">
            <img id="previewImage" src="<?= $profile_img ?>" class="profile-pic" alt="Profile Picture">
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="profile_pic"><i class="fas fa-camera"></i> Profile Picture</label>
                <input type="file" name="profile_pic" id="profileInput" accept="image/*">
            </div>

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
                <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                <input type="email" name="email" id="email" 
                       value="<?= htmlspecialchars($user['email'] ?? '') ?>" 
                       placeholder="Enter email address" required>
            </div>

            <div class="button-group">
                <button type="button" class="btn btn-primary" id="saveBtn"><i class="fas fa-save"></i> Save Changes</button>
                <a href="admin_profile.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
            </div>
        </form>

        <!-- CONFIRMATION MODAL -->
        <div class="modal" id="confirmModal">
            <div class="modal-content">
                <h3><i class="fas fa-question-circle"></i> Confirm Changes</h3>
                <p>Are you sure you want to save these profile updates?</p>
                <div class="modal-buttons">
                    <button class="btn-confirm" id="confirmYes"><i class="fas fa-check"></i> Yes, Save</button>
                    <button class="btn-cancel" id="confirmNo"><i class="fas fa-times"></i> Cancel</button>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>
</main>

<script>
// Hamburger sidebar toggle
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

// Preview image before upload
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

// Confirmation modal
const saveBtn = document.getElementById('saveBtn');
const modal = document.getElementById('confirmModal');
const yesBtn = document.getElementById('confirmYes');
const noBtn = document.getElementById('confirmNo');
const form = document.querySelector('form');

saveBtn?.addEventListener('click', () => {
    modal.classList.add('show');
});

noBtn?.addEventListener('click', () => {
    modal.classList.remove('show');
});

yesBtn?.addEventListener('click', () => {
    form.submit();
});

// Close modal on outside click
modal?.addEventListener('click', (e) => {
    if (e.target === modal) {
        modal.classList.remove('show');
    }
});
</script>

</body>
</html>