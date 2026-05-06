<?php
session_start();
require 'includes/functions.php';
require 'includes/db_connect.php';

$errors = [];
$success_message = '';

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'account_disabled') {
        $errors[] = "Your account has been disabled. Please contact support for assistance.";
    } elseif ($_GET['error'] === 'account_deleted') {
        $errors[] = "Your account no longer exists. Please contact support.";
    }
}

if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success_message = "You have been logged out successfully.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = trim(strtolower($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";

    if (!empty($email) && !empty($password)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                if (password_verify($password, $user["password_hash"])) {
                    if ($user["is_disabled"] == 1) {
                        $errors[] = "Your account has been disabled. Please contact support for assistance.";
                    } else {
                        if (password_needs_rehash($user["password_hash"], PASSWORD_DEFAULT)) {
                            $newHash = password_hash($password, PASSWORD_DEFAULT);
                            $update  = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                            $update->execute([$newHash, $user["id"]]);
                        }
                        $_SESSION["user_id"]   = $user["id"];
                        $_SESSION["user_name"] = $user["first_name"];
                        $_SESSION["user_role"] = $user["role"];
                        $role = strtolower($user["role"]);
                        if ($role === "admin") {
                            header("Location: dashboards/admin/admin_dashboard.php");
                        } elseif ($role === "mechanic") {
                            header("Location: dashboards/mechanic/mechanic_dashboard.php");
                        } else {
                            header("Location: dashboards/customer/customer_dashboard.php");
                        }
                        exit;
                    }
                } else {
                    $errors[] = "Invalid email or password.";
                }
            } else {
                $errors[] = "Invalid email or password.";
            }
        }
    } else {
        $errors[] = "Please fill in both fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - MotorService</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #1a56db;
    --secondary: #1e40af;
    --card-bg: #ffffff;
    --border: rgba(26, 86, 219, 0.2);
    --text-primary: #1e293b;
    --text-secondary: #475569;
    --error: #dc2626;
    --success: #059669;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
html, body { height: 100%; }

body {
    font-family: 'Outfit', sans-serif;
    background: linear-gradient(135deg, #f0f4ff, #e8eeff);
    color: var(--text-primary);
    min-height: 100vh;
    display: flex; align-items: center; justify-content: center;
    padding: 16px; position: relative; overflow-y: auto;
}

.bg-icons {
    position: fixed; top: 0; left: 0;
    width: 100%; height: 100%;
    overflow: hidden; pointer-events: none; z-index: 0;
}
.bg-icon {
    position: absolute;
    color: rgba(26, 86, 219, 0.12);
    animation: float 20s infinite ease-in-out;
    filter: drop-shadow(0 2px 8px rgba(26, 86, 219, 0.08));
}
.bg-icon:nth-child(1) { top: 10%; left: 10%; animation-delay: 0s;  font-size: 70px; }
.bg-icon:nth-child(2) { top: 20%; right: 15%; animation-delay: 2s; font-size: 50px; }
.bg-icon:nth-child(3) { bottom: 15%; left: 20%; animation-delay: 4s; font-size: 80px; }
.bg-icon:nth-child(4) { bottom: 25%; right: 10%; animation-delay: 6s; font-size: 55px; }
.bg-icon:nth-child(5) { top: 50%; left: 5%; animation-delay: 8s; font-size: 45px; }
.bg-icon:nth-child(6) { top: 60%; right: 5%; animation-delay: 10s; font-size: 65px; }
.bg-icon:nth-child(7) { top: 30%; left: 50%; animation-delay: 3s; font-size: 40px; }
.bg-icon:nth-child(8) { bottom: 40%; right: 40%; animation-delay: 7s; font-size: 60px; }
@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    25%       { transform: translateY(-15px) rotate(5deg); }
    75%       { transform: translateY(15px) rotate(-5deg); }
}

.container {
    position: relative; z-index: 1;
    width: 100%; max-width: 420px;
}

.login-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 38px 36px;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.08);
    position: relative; overflow: hidden;
    transition: box-shadow 0.3s ease;
}
.login-card:hover {
    box-shadow: 0 12px 40px rgba(26, 86, 219, 0.15);
}
.login-card::before {
    content: ''; position: absolute; top: 0; left: 0;
    width: 100%; height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
}

.logo-section { text-align: center; margin-bottom: 26px; }
.logo {
    font-size: 1.6rem; font-weight: 700;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 10px;
}
.welcome-text p { color: var(--text-secondary); font-size: 13px; }

.alert-success {
    background: rgba(5, 150, 105, 0.08);
    color: var(--success);
    padding: 11px 14px; border-radius: 10px; font-size: 13px;
    margin-bottom: 16px; border-left: 4px solid var(--success);
    display: flex; align-items: center; gap: 10px;
    font-weight: 600; animation: slideInDown 0.3s ease;
}
.error-msg {
    background: rgba(220, 38, 38, 0.08);
    color: var(--error);
    padding: 11px 14px; border-radius: 10px; font-size: 13px;
    margin-bottom: 16px; border-left: 4px solid var(--error);
    display: flex; align-items: center; gap: 10px;
    font-weight: 600; animation: slideInDown 0.3s ease;
}
@keyframes slideInDown {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0); }
}

.form-group { margin-bottom: 15px; }
.form-group label {
    display: flex; align-items: center; gap: 6px;
    font-size: 12px; color: var(--primary);
    font-weight: 700; margin-bottom: 7px;
    text-transform: uppercase; letter-spacing: 0.4px;
}
.label-row {
    display: flex; align-items: center;
    justify-content: space-between; margin-bottom: 7px;
}
.label-row label { margin-bottom: 0; }
.forgot-link {
    font-size: 12px; color: var(--text-secondary);
    font-weight: 600; transition: color 0.2s ease; text-decoration: none;
}
.forgot-link:hover { color: var(--primary); }

.input-wrapper { position: relative; }
.input-icon {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    color: var(--text-secondary); font-size: 13px;
    transition: color 0.3s ease; pointer-events: none;
}
.form-group input {
    width: 100%; padding: 12px 14px 12px 40px;
    background: #ffffff; border: 1px solid var(--border);
    border-radius: 10px; color: var(--text-primary);
    font-size: 13px; font-family: 'Outfit', sans-serif;
    transition: all 0.3s ease;
}
.form-group input:focus {
    border-color: var(--primary); outline: none;
    box-shadow: 0 0 15px rgba(26, 86, 219, 0.15);
    background: rgba(26, 86, 219, 0.04);
}
.form-group input:focus ~ .input-icon { color: var(--primary); }
.form-group input::placeholder { color: #94a3b8; }

.btn-login {
    width: 100%; padding: 13px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border: none; border-radius: 10px;
    color: #ffffff; font-size: 14px; font-weight: 700;
    cursor: pointer; text-transform: uppercase; letter-spacing: 0.5px;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.2);
    transition: all 0.3s ease;
    display: flex; align-items: center; justify-content: center; gap: 10px;
    font-family: 'Outfit', sans-serif; margin-top: 6px;
}
.btn-login:hover  { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26, 86, 219, 0.35); }
.btn-login:active { transform: translateY(0); }
.btn-login:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }

.divider {
    display: flex; align-items: center; margin: 18px 0;
    color: var(--text-secondary); font-size: 12px;
    font-weight: 700; text-transform: uppercase; letter-spacing: 1px;
}
.divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }
.divider::before { margin-right: 14px; }
.divider::after  { margin-left: 14px; }

.register-section {
    text-align: center; padding: 16px;
    background: rgba(26, 86, 219, 0.04);
    border-radius: 10px; border: 1px solid var(--border);
}
.register-section p { color: var(--text-secondary); font-size: 13px; margin-bottom: 11px; }
.btn-register {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 11px 28px;
    background: rgba(26, 86, 219, 0.08);
    border: 1px solid var(--border); border-radius: 10px;
    color: var(--primary); font-weight: 700; font-size: 13px;
    transition: all 0.3s ease; text-decoration: none;
    text-transform: uppercase; letter-spacing: 0.4px;
}
.btn-register:hover {
    background: rgba(26, 86, 219, 0.15);
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.15);
}

/* Loading overlay */
#loading-overlay {
    display: none; position: fixed; top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(240, 244, 255, 0.88);
    backdrop-filter: blur(6px);
    z-index: 9999; align-items: center;
    justify-content: center; flex-direction: column;
}
.loading-box {
    display: flex; flex-direction: column;
    align-items: center; gap: 20px;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 16px; padding: 36px 44px;
    box-shadow: 0 8px 30px rgba(26, 86, 219, 0.12);
}
.loading-box p {
    color: var(--text-secondary); font-size: 14px;
    font-weight: 700; letter-spacing: 0.4px;
    text-transform: uppercase;
}
.loading-ring {
    display: inline-block; position: relative;
    width: 60px; height: 60px;
}
.loading-ring div {
    box-sizing: border-box; display: block; position: absolute;
    width: 48px; height: 48px; margin: 6px;
    border: 5px solid transparent;
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: ring-spin 1s cubic-bezier(0.5, 0, 0.5, 1) infinite;
}
.loading-ring div:nth-child(1) { animation-delay: -0.3s; border-top-color: var(--primary); }
.loading-ring div:nth-child(2) { animation-delay: -0.2s; border-top-color: var(--secondary); }
.loading-ring div:nth-child(3) { animation-delay: -0.1s; border-top-color: var(--primary); }
.loading-ring div:nth-child(4) { animation-delay:    0s; border-top-color: var(--secondary); }
@keyframes ring-spin {
    0%   { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.spinner {
    display: inline-block; width: 14px; height: 14px;
    border: 2px solid rgba(255,255,255,0.4);
    border-top-color: #ffffff;
    border-radius: 50%;
    animation: ring-spin 0.7s linear infinite;
    vertical-align: middle;
}

@media (max-height: 700px) {
    .login-card { padding: 26px 32px; }
    .logo-section { margin-bottom: 18px; }
    .form-group { margin-bottom: 11px; }
    .divider { margin: 14px 0; }
    .register-section { padding: 13px; }
}
@media (max-width: 480px) {
    body { align-items: flex-start; padding-top: 24px; padding-bottom: 24px; }
    .login-card { padding: 28px 20px; border-radius: 14px; }
    .logo { font-size: 1.4rem; }
    .btn-register { width: 100%; }
}
</style>
</head>
<body>

<div class="bg-icons">
    <i class="fas fa-wrench bg-icon"></i>
    <i class="fas fa-cog bg-icon"></i>
    <i class="fas fa-tools bg-icon"></i>
    <i class="fas fa-motorcycle bg-icon"></i>
    <i class="fas fa-oil-can bg-icon"></i>
    <i class="fas fa-screwdriver bg-icon"></i>
    <i class="fas fa-hammer bg-icon"></i>
    <i class="fas fa-car-battery bg-icon"></i>
</div>

<div id="loading-overlay">
    <div class="loading-box">
        <div class="loading-ring">
            <div></div><div></div><div></div><div></div>
        </div>
        <p>Logging you in...</p>
    </div>
</div>

<div class="container">
    <div class="login-card">
        <div class="logo-section">
            <div class="logo">
                <i class="fas fa-tools"></i>
                <span>MotorService</span>
            </div>
            <div class="welcome-text">
                <p>Login to access your dashboard</p>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?= htmlspecialchars($success_message) ?></span>
            </div>
        <?php endif; ?>

        <?php foreach ($errors as $e): ?>
            <div class="error-msg">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($e) ?></span>
            </div>
        <?php endforeach; ?>

        <form method="POST">
            <div class="form-group">
                <label for="email">
                    <i class="fas fa-envelope"></i> Email Address
                </label>
                <div class="input-wrapper">
                    <input type="email" name="email" id="email"
                           placeholder="your.email@example.com" required
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                    <i class="fas fa-envelope input-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <div class="label-row">
                    <label for="password">
                        <i class="fas fa-lock"></i> Password
                    </label>
                </div>
                <div class="input-wrapper">
                    <input type="password" name="password" id="password"
                           placeholder="Enter your password" required>
                    <i class="fas fa-lock input-icon"></i>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>

            <div style="text-align:right; margin-top:10px;">
                <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
            </div>
        </form>

        <div class="divider">OR</div>

        <div class="register-section">
            <p>Don't have an account yet?</p>
            <a href="register.php" class="btn-register">
                <i class="fas fa-user-plus"></i> Create New Account
            </a>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('input').forEach(input => {
    input.addEventListener('focus', function() { this.parentElement.classList.add('focused'); });
    input.addEventListener('blur',  function() { this.parentElement.classList.remove('focused'); });
});

document.querySelector('form').addEventListener('submit', function() {
    const btn = document.querySelector('.btn-login');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Logging in...';
    document.getElementById('loading-overlay').style.display = 'flex';
});
</script>

</body>
</html>