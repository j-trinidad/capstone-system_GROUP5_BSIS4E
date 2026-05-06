<?php
session_start();
require 'includes/db_connect.php';

$errors = [];
$success_message = '';
$valid_token = false;
$reset = null;
$token = trim($_GET['token'] ?? '');

if (empty($token)) {
    $errors[] = "Invalid or missing reset token.";
} else {
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    if ($reset) {
        $valid_token = true;
    } else {
        $errors[] = "This reset link is invalid or has already expired. Please request a new one.";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $valid_token) {
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $errors[] = "Please fill in both password fields.";
    } elseif (strlen($new_password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?")->execute([$hashed, $reset['email']]);
        $pdo->prepare("DELETE FROM password_resets WHERE token = ?")->execute([$token]);
        $success_message = "Password changed successfully! You can now login.";
        $valid_token = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password - MotorService</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #ff8c00; --secondary: #e52e71;
    --dark-bg: #0a0e27; --card-bg: #1a1f3a;
    --border: rgba(255,140,0,0.2);
    --text-primary: #fff; --text-secondary: #b0b8d4; --error: #ff4757;
}
* { margin:0; padding:0; box-sizing:border-box; }
html,body { height:100%; }
body {
    font-family:'Outfit',sans-serif;
    background:linear-gradient(135deg,var(--dark-bg),#1a1f3a);
    color:var(--text-primary); min-height:100vh;
    display:flex; align-items:center; justify-content:center;
    padding:16px; position:relative; overflow-y:auto;
}
body::before {
    content:''; position:fixed; top:0; left:0; width:100%; height:100%;
    background-image: radial-gradient(circle at 20% 30%,rgba(255,140,0,0.04) 0%,transparent 50%), radial-gradient(circle at 80% 70%,rgba(229,46,113,0.04) 0%,transparent 50%);
    pointer-events:none; z-index:0;
}
.bg-icons { position:fixed; top:0; left:0; width:100%; height:100%; overflow:hidden; pointer-events:none; z-index:0; }
.bg-icon { position:absolute; font-size:36px; color:rgba(255,140,0,0.05); animation:float 20s infinite ease-in-out; }
.bg-icon:nth-child(1){top:10%;left:10%;animation-delay:0s}
.bg-icon:nth-child(2){top:20%;right:15%;animation-delay:2s}
.bg-icon:nth-child(3){bottom:15%;left:20%;animation-delay:4s}
.bg-icon:nth-child(4){bottom:25%;right:10%;animation-delay:6s}
.bg-icon:nth-child(5){top:50%;left:5%;animation-delay:8s}
.bg-icon:nth-child(6){top:60%;right:5%;animation-delay:10s}
.bg-icon:nth-child(7){top:30%;left:50%;animation-delay:3s}
.bg-icon:nth-child(8){bottom:40%;right:40%;animation-delay:7s}
@keyframes float { 0%,100%{transform:translateY(0) rotate(0deg)} 25%{transform:translateY(-15px) rotate(5deg)} 75%{transform:translateY(15px) rotate(-5deg)} }
.container { position:relative; z-index:1; width:100%; max-width:420px; }
.card {
    background:var(--card-bg); border:1px solid var(--border); border-radius:20px;
    padding:38px 36px; box-shadow:0 8px 32px rgba(0,0,0,0.3); position:relative; overflow:hidden;
}
.card::before { content:''; position:absolute; top:0; left:0; width:100%; height:4px; background:linear-gradient(90deg,var(--primary),var(--secondary)); }
.logo-section { text-align:center; margin-bottom:26px; }
.logo {
    font-size:1.6rem; font-weight:700;
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
    display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:10px;
}
.logo i { background:linear-gradient(135deg,var(--primary),var(--secondary)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
.title-text h2 { font-size:22px; margin-bottom:5px; font-weight:700; }
.title-text p { color:var(--text-secondary); font-size:13px; }
.alert-success {
    background:rgba(0,208,132,0.2); color:#00d084; padding:11px 14px; border-radius:10px;
    font-size:13px; margin-bottom:16px; border-left:4px solid #00d084;
    display:flex; align-items:center; gap:10px; animation:slideInDown 0.3s ease;
}
.error-msg {
    background:rgba(255,71,87,0.2); color:var(--error); padding:11px 14px; border-radius:10px;
    font-size:13px; margin-bottom:16px; border-left:4px solid var(--error);
    display:flex; align-items:center; gap:10px; animation:slideInDown 0.3s ease;
}
@keyframes slideInDown { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }
.form-group { margin-bottom:15px; }
.form-group label { display:flex; align-items:center; gap:6px; font-size:13px; color:var(--primary); font-weight:600; margin-bottom:7px; }
.input-wrapper { position:relative; }
.input-icon { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--text-secondary); font-size:14px; transition:color 0.3s ease; pointer-events:none; }
.toggle-pass { position:absolute; right:14px; top:50%; transform:translateY(-50%); color:var(--text-secondary); font-size:14px; cursor:pointer; transition:color 0.3s ease; }
.toggle-pass:hover { color:var(--primary); }
.form-group input {
    width:100%; padding:12px 42px 12px 42px;
    background:rgba(255,255,255,0.08); border:2px solid var(--border); border-radius:10px;
    color:var(--text-primary); font-size:14px; font-family:'Outfit',sans-serif; transition:all 0.3s ease;
}
.form-group input:focus { border-color:var(--primary); outline:none; box-shadow:0 0 15px rgba(255,140,0,0.2); background:rgba(255,255,255,0.12); }
.form-group input:focus ~ .input-icon { color:var(--primary); }
.form-group input::placeholder { color:rgba(255,255,255,0.3); }
.strength-bar { margin-top:8px; height:4px; border-radius:4px; background:rgba(255,255,255,0.1); overflow:hidden; }
.strength-fill { height:100%; border-radius:4px; transition:width 0.3s ease,background 0.3s ease; width:0%; }
.strength-label { font-size:11px; color:var(--text-secondary); margin-top:4px; }
.btn-submit {
    width:100%; padding:13px; background:linear-gradient(135deg,var(--primary),var(--secondary));
    border:none; border-radius:10px; color:#1a1f3a; font-size:15px; font-weight:700;
    cursor:pointer; text-transform:uppercase; letter-spacing:0.5px;
    box-shadow:0 4px 15px rgba(255,140,0,0.3); transition:all 0.3s ease;
    display:flex; align-items:center; justify-content:center; gap:10px;
    font-family:'Outfit',sans-serif; margin-top:6px;
}
.btn-submit:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(255,140,0,0.5); }
.btn-submit:active { transform:translateY(0); }
.back-link { text-align:center; margin-top:20px; }
.back-link a { color:var(--primary); text-decoration:none; font-size:13px; font-weight:600; display:inline-flex; align-items:center; gap:6px; transition:opacity 0.2s; }
.back-link a:hover { opacity:0.8; }
@media (max-height:700px) { .card{padding:26px 32px;} .logo-section{margin-bottom:18px;} .form-group{margin-bottom:11px;} }
@media (max-width:480px) { body{align-items:flex-start;padding-top:24px;padding-bottom:24px;} .card{padding:28px 20px;border-radius:16px;} .logo{font-size:1.4rem;} .title-text h2{font-size:20px;} }
</style>
</head>
<body>
<div class="bg-icons">
    <i class="fas fa-wrench bg-icon"></i><i class="fas fa-cog bg-icon"></i>
    <i class="fas fa-tools bg-icon"></i><i class="fas fa-car bg-icon"></i>
    <i class="fas fa-oil-can bg-icon"></i><i class="fas fa-screwdriver bg-icon"></i>
    <i class="fas fa-hammer bg-icon"></i><i class="fas fa-car-battery bg-icon"></i>
</div>
<div class="container">
    <div class="card">
        <div class="logo-section">
            <div class="logo"><i class="fas fa-wrench"></i><span>MotorService</span></div>
            <div class="title-text">
                <h2>Reset Password</h2>
                <p>Enter your new password below</p>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert-success"><i class="fas fa-check-circle"></i><span><?= htmlspecialchars($success_message) ?></span></div>
        <?php endif; ?>
        <?php foreach ($errors as $e): ?>
            <div class="error-msg"><i class="fas fa-exclamation-circle"></i><span><?= htmlspecialchars($e) ?></span></div>
        <?php endforeach; ?>

        <?php if ($valid_token): ?>
        <form method="POST">
            <div class="form-group">
                <label for="new_password"><i class="fas fa-lock"></i> New Password</label>
                <div class="input-wrapper">
                    <input type="password" name="new_password" id="new_password" placeholder="At least 8 characters" required>
                    <i class="fas fa-lock input-icon"></i>
                    <i class="fas fa-eye toggle-pass" onclick="togglePassword('new_password', this)"></i>
                </div>
                <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
                <div class="strength-label" id="strength-label"></div>
            </div>

            <div class="form-group">
                <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password</label>
                <div class="input-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-enter your password" required>
                    <i class="fas fa-lock input-icon"></i>
                    <i class="fas fa-eye toggle-pass" onclick="togglePassword('confirm_password', this)"></i>
                </div>
            </div>

            <button type="submit" class="btn-submit"><i class="fas fa-key"></i> Change Password</button>
        </form>
        <?php endif; ?>

        <div class="back-link">
            <?php if (!empty($success_message)): ?>
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> Go to Login</a>
            <?php else: ?>
                <a href="forgot_password.php"><i class="fas fa-arrow-left"></i> Request New Link</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function togglePassword(fieldId, icon) {
    const input = document.getElementById(fieldId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

document.getElementById('new_password')?.addEventListener('input', function () {
    const val = this.value;
    const fill = document.getElementById('strength-fill');
    const label = document.getElementById('strength-label');
    let strength = 0;
    if (val.length >= 8) strength++;
    if (/[A-Z]/.test(val)) strength++;
    if (/[0-9]/.test(val)) strength++;
    if (/[^A-Za-z0-9]/.test(val)) strength++;
    const levels = [
        { width:'0%',   color:'transparent', text:'' },
        { width:'25%',  color:'#ff4757',     text:'Weak' },
        { width:'50%',  color:'#f39c12',     text:'Fair' },
        { width:'75%',  color:'#3498db',     text:'Good' },
        { width:'100%', color:'#00d084',     text:'Strong' },
    ];
    fill.style.width = levels[strength].width;
    fill.style.background = levels[strength].color;
    label.textContent = levels[strength].text;
    label.style.color = levels[strength].color;
});
</script>
</body>
</html>
