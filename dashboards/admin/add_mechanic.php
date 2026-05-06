<?php
require '../../includes/session_check.php';
checkRole('admin');
require '../../includes/db_connect.php';
require '../../includes/activity_logger.php';

// Get admin info for logging
$adminName = $_SESSION['user_name'] ?? 'Admin';
$adminId = $_SESSION['user_id'];

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    // Validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        // Check if email already exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$email]);
        
        if ($checkStmt->fetch()) {
            $error = 'Email address already exists';
        } else {
            // Create mechanic account
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $insertStmt = $pdo->prepare("
                INSERT INTO users (first_name, last_name, email, password_hash, role, created_at) 
                VALUES (?, ?, ?, ?, 'mechanic', NOW())
            ");
            
            if ($insertStmt->execute([$firstName, $lastName, $email, $hashedPassword])) {
                $success = 'Mechanic account created successfully!';
                
                // Log the activity
                logActivity($pdo, $adminId, $adminName, 'mechanic_create', 
                    "Created mechanic account: $firstName $lastName (Email: $email)");
                
                // Clear form
                $firstName = $lastName = $email = '';
            } else {
                $error = 'Failed to create mechanic account';
            }
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
<title>Add Mechanic - MotorService</title>
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

.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: transparent;
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
    background: linear-gradient(135deg, var(--dark-bg), #1a1f3a);
    display: flex;
    flex-direction: column;
    align-items: center;
}

.main::-webkit-scrollbar {
    width: 8px;
}

.main::-webkit-scrollbar-track {
    background: transparent;
}

.main::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 4px;
}

h1 {
    font-size: 32px;
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
    max-width: 700px;
}

h1 i { font-size: 36px; }

.card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    max-width: 700px;
    width: 100%;
}

.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-success {
    background: rgba(0, 208, 132, 0.1);
    border: 1px solid var(--success);
    color: var(--success);
}

.alert-error {
    background: rgba(255, 71, 87, 0.1);
    border: 1px solid var(--error);
    color: var(--error);
}

.alert i {
    font-size: 20px;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 8px;
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.required {
    color: var(--error);
}

input, select {
    width: 100%;
    padding: 12px 16px;
    background: rgba(255, 140, 0, 0.05);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text-primary);
    font-size: 14px;
    font-family: 'Outfit', sans-serif;
    transition: all 0.3s ease;
}

input:focus, select:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(255, 140, 0, 0.1);
    box-shadow: 0 0 0 3px rgba(255, 140, 0, 0.1);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #1a1f3a;
    box-shadow: 0 4px 15px rgba(255, 140, 0, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 140, 0, 0.4);
}

.btn-secondary {
    background: rgba(255, 140, 0, 0.1);
    color: var(--primary);
    border: 2px solid var(--primary);
}

.btn-secondary:hover {
    background: rgba(255, 140, 0, 0.2);
    transform: translateY(-2px);
}

.btn i {
    font-size: 16px;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 30px;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    justify-content: center;
    align-items: center;
    z-index: 9999;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal.show {
    display: flex;
}

.modal-content {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 30px;
    max-width: 450px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.8);
    animation: slideUp 0.4s ease;
    text-align: center;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    color: #fff;
}

.modal-content h3 {
    font-size: 24px;
    margin-bottom: 10px;
    color: var(--text-primary);
}

.modal-content p {
    color: var(--text-secondary);
    margin-bottom: 25px;
    font-size: 14px;
    line-height: 1.6;
}

.modal-details {
    background: rgba(255, 140, 0, 0.05);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 25px;
    text-align: left;
}

.modal-details .detail-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid var(--border);
}

.modal-details .detail-row:last-child {
    border-bottom: none;
}

.modal-details .detail-label {
    color: var(--text-secondary);
    font-size: 13px;
    font-weight: 600;
}

.modal-details .detail-value {
    color: var(--text-primary);
    font-weight: 600;
    font-size: 13px;
    word-break: break-all;
}

.modal-buttons {
    display: flex;
    gap: 12px;
}

.modal-buttons button {
    flex: 1;
}

.btn-confirm {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #1a1f3a;
}

.btn-confirm:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 140, 0, 0.4);
}

.btn-cancel {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
    border: 1px solid var(--border);
}

.btn-cancel:hover {
    background: rgba(255, 255, 255, 0.2);
}

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

    .main { margin-left: 0; width: 100%; padding: 80px 20px 20px 20px; }
    h1 { font-size: 20px; margin-top: 10px; }

    .form-row { grid-template-columns: 1fr; }
    .card { padding: 20px; }
    .form-actions { flex-direction: column; }
    .form-actions .btn { width: 100%; }
    .modal-buttons { flex-direction: column; }
}

@media (max-width: 480px) {
    .main { padding: 75px 15px 15px 15px; }
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
    <h1><i class="fas fa-user-plus"></i> Add Mechanic Account</h1>

    <div class="card">
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label>First Name <span class="required">*</span></label>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($firstName ?? '') ?>" placeholder="Enter first name" required>
                </div>

                <div class="form-group">
                    <label>Last Name <span class="required">*</span></label>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($lastName ?? '') ?>" placeholder="Enter last name" required>
                </div>
            </div>

            <div class="form-group">
                <label>Email Address <span class="required">*</span></label>
                <input type="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" placeholder="mechanic@example.com" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Password <span class="required">*</span></label>
                    <input type="password" name="password" minlength="6" placeholder="At least 6 characters" required>
                </div>

                <div class="form-group">
                    <label>Confirm Password <span class="required">*</span></label>
                    <input type="password" name="confirm_password" minlength="6" placeholder="Re-enter password" required>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-primary" id="showConfirmBtn">
                    <i class="fas fa-user-plus"></i> Create Mechanic Account
                </button>
                <a href="admin_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </form>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal" id="confirmModal">
        <div class="modal-content">
            <div class="modal-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <h3>Confirm Mechanic Account</h3>
            <p>Are you sure you want to create this mechanic account?</p>
            
            <div class="modal-details" id="modalDetails">
                <div class="detail-row">
                    <span class="detail-label">First Name:</span>
                    <span class="detail-value" id="confirmFirstName"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Last Name:</span>
                    <span class="detail-value" id="confirmLastName"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email Address:</span>
                    <span class="detail-value" id="confirmEmail"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Role:</span>
                    <span class="detail-value">Mechanic</span>
                </div>
            </div>

            <div class="modal-buttons">
                <button type="button" class="btn btn-confirm" id="confirmYes">
                    <i class="fas fa-check"></i> Yes, Create Account
                </button>
                <button type="button" class="btn btn-cancel" id="confirmNo">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>
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

const form = document.querySelector('form');
const modal = document.getElementById('confirmModal');
const showConfirmBtn = document.getElementById('showConfirmBtn');
const confirmYes = document.getElementById('confirmYes');
const confirmNo = document.getElementById('confirmNo');

// Show confirmation modal
showConfirmBtn.addEventListener('click', function() {
    // Validate form first
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    // Get form values
    const firstName = document.querySelector('input[name="first_name"]').value;
    const lastName = document.querySelector('input[name="last_name"]').value;
    const email = document.querySelector('input[name="email"]').value;
    const password = document.querySelector('input[name="password"]').value;
    const confirmPassword = document.querySelector('input[name="confirm_password"]').value;

    // Check if passwords match
    if (password !== confirmPassword) {
        alert('Passwords do not match!');
        return;
    }

    // Check password length
    if (password.length < 6) {
        alert('Password must be at least 6 characters!');
        return;
    }

    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        alert('Please enter a valid email address!');
        return;
    }

    // Populate modal with form data
    document.getElementById('confirmFirstName').textContent = firstName;
    document.getElementById('confirmLastName').textContent = lastName;
    document.getElementById('confirmEmail').textContent = email;

    // Show modal
    modal.classList.add('show');
});

// Confirm - submit form
confirmYes.addEventListener('click', function() {
    form.submit();
});

// Cancel - hide modal
confirmNo.addEventListener('click', function() {
    modal.classList.remove('show');
});

// Close modal on outside click
modal.addEventListener('click', function(e) {
    if (e.target === modal) {
        modal.classList.remove('show');
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modal.classList.contains('show')) {
        modal.classList.remove('show');
    }
});
</script>

</body>
</html>