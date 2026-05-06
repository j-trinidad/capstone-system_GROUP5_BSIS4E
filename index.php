<?php
require 'includes/functions.php';
require 'includes/db_connect.php';
session_start();
$user = isset($_SESSION['user_id']) ? $_SESSION : null;
$user_name = $_SESSION['user_name'] ?? null;
$user_role = $_SESSION['user_role'] ?? null;

$dashboard_link = '#';
if ($user_role === 'admin') {
    $dashboard_link = 'dashboards/admin/admin_dashboard.php';
} elseif ($user_role === 'mechanic') {
    $dashboard_link = 'dashboards/mechanic/mechanic_dashboard.php';
} elseif ($user_role === 'customer') {
    $dashboard_link = 'dashboards/customer/customer_dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MotorService - Professional Auto Repair</title>
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

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Outfit', sans-serif;
    background: linear-gradient(135deg, #f0f4ff, #e8eeff);
    color: var(--text-primary);
    min-height: 100vh;
    overflow-x: hidden;
    position: relative;
}

a { text-decoration: none; color: inherit; }

/* ── Background floating icons ── */
.bg-icons {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    overflow: hidden;
    pointer-events: none;
    z-index: 0;
}
.bg-icon {
    position: absolute;
    font-size: 52px;
    color: rgba(26, 86, 219, 0.12);
    animation: float 20s infinite ease-in-out;
    filter: drop-shadow(0 2px 8px rgba(26, 86, 219, 0.08));
}
.bg-icon:nth-child(1) { top: 10%; left: 10%; animation-delay: 0s; font-size: 70px; }
.bg-icon:nth-child(2) { top: 20%; right: 15%; animation-delay: 2s; font-size: 50px; }
.bg-icon:nth-child(3) { bottom: 15%; left: 20%; animation-delay: 4s; font-size: 80px; }
.bg-icon:nth-child(4) { bottom: 25%; right: 10%; animation-delay: 6s; font-size: 55px; }
.bg-icon:nth-child(5) { top: 50%; left: 5%; animation-delay: 8s; font-size: 45px; }
.bg-icon:nth-child(6) { top: 60%; right: 5%; animation-delay: 10s; font-size: 65px; }
.bg-icon:nth-child(7) { top: 30%; left: 50%; animation-delay: 3s; font-size: 40px; }
.bg-icon:nth-child(8) { bottom: 40%; right: 40%; animation-delay: 7s; font-size: 60px; }
@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    25%       { transform: translateY(-20px) rotate(5deg); }
    50%       { transform: translateY(0) rotate(0deg); }
    75%       { transform: translateY(20px) rotate(-5deg); }
}

/* ── Navbar ── */
.navbar {
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 40px;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
    box-shadow: 0 2px 15px rgba(26, 86, 219, 0.08);
}

.nav-logo {
    font-size: 1.4rem;
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    display: flex;
    align-items: center;
    gap: 10px;
    white-space: nowrap;
    flex-shrink: 0;
}

.nav-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.nav-btn {
    padding: 9px 20px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    border: none;
    font-family: 'Outfit', sans-serif;
    white-space: nowrap;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.nav-btn-outline {
    background: rgba(26, 86, 219, 0.08);
    color: var(--primary);
    border: 1px solid var(--border);
}
.nav-btn-outline:hover {
    background: rgba(26, 86, 219, 0.15);
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.15);
}

.nav-btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.2);
}
.nav-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(26, 86, 219, 0.35);
}

.nav-user {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-secondary);
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
}
.nav-user .uname {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 700;
}

.btn-text { display: inline; }

/* ── Hero ── */
.hero {
    position: relative;
    z-index: 1;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 130px 20px 70px;
}

.hero-content {
    max-width: 680px;
    animation: fadeUp 0.8s ease both;
}

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(30px); }
    to   { opacity: 1; transform: translateY(0); }
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    background: rgba(26, 86, 219, 0.08);
    border: 1px solid var(--border);
    border-radius: 50px;
    font-size: 13px;
    color: var(--primary);
    font-weight: 700;
    margin-bottom: 28px;
    animation: fadeUp 0.8s ease 0.1s both;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.hero h1 {
    font-size: clamp(2.2rem, 6vw, 3.8rem);
    font-weight: 700;
    line-height: 1.15;
    margin-bottom: 20px;
    color: var(--text-primary);
    animation: fadeUp 0.8s ease 0.2s both;
}

.hero h1 span {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero p {
    font-size: 1.05rem;
    color: var(--text-secondary);
    line-height: 1.7;
    margin-bottom: 40px;
    animation: fadeUp 0.8s ease 0.3s both;
}

.hero-buttons {
    display: flex;
    gap: 14px;
    justify-content: center;
    flex-wrap: wrap;
    animation: fadeUp 0.8s ease 0.4s both;
}

.hero-btn {
    padding: 14px 32px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    font-family: 'Outfit', sans-serif;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.hero-btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.2);
}
.hero-btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(26, 86, 219, 0.35);
}

.hero-btn-outline {
    background: rgba(26, 86, 219, 0.08);
    color: var(--primary);
    border: 1px solid var(--border);
}
.hero-btn-outline:hover {
    background: rgba(26, 86, 219, 0.15);
    transform: translateY(-3px);
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.12);
}



/* ── Section shared ── */
.section {
    position: relative;
    z-index: 1;
    padding: 60px 20px 80px;
    text-align: center;
}

.section-label {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--primary);
    margin-bottom: 12px;
    background: rgba(26, 86, 219, 0.08);
    border: 1px solid var(--border);
    padding: 5px 14px;
    border-radius: 50px;
}

.section-title {
    font-size: clamp(1.6rem, 4vw, 2.2rem);
    font-weight: 700;
    margin-bottom: 12px;
    color: var(--text-primary);
}
.section-title span {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.section-sub {
    color: var(--text-secondary);
    font-size: 14px;
    margin-bottom: 45px;
}

/* ── Services grid ── */
.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    max-width: 1000px;
    margin: 0 auto;
}

.service-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 28px 20px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.06);
    position: relative;
    overflow: hidden;
    text-align: left;
}
.service-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    transform: scaleX(0);
    transition: transform 0.3s ease;
}
.service-card:hover::before { transform: scaleX(1); }
.service-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary);
    box-shadow: 0 12px 35px rgba(26, 86, 219, 0.18);
}

.service-icon {
    width: 52px; height: 52px;
    background: rgba(26, 86, 219, 0.08);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 16px;
    font-size: 22px;
    color: var(--primary);
    transition: all 0.3s ease;
    border: 1px solid var(--border);
}
.service-card:hover .service-icon {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff;
    border-color: transparent;
}
.service-card h3 {
    font-size: 15px;
    font-weight: 700;
    margin-bottom: 8px;
    color: var(--text-primary);
}
.service-card p {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.6;
}

/* ── Why us ── */
.why-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    max-width: 900px;
    margin: 0 auto;
}

.why-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 28px 22px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.06);
    text-align: left;
    display: flex;
    gap: 16px;
    align-items: flex-start;
}
.why-card:hover {
    transform: translateY(-4px);
    border-color: var(--primary);
    box-shadow: 0 10px 30px rgba(26, 86, 219, 0.15);
}
.why-icon {
    width: 44px; height: 44px;
    background: rgba(26, 86, 219, 0.08);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: var(--primary);
    font-size: 18px;
    flex-shrink: 0;
    border: 1px solid var(--border);
}
.why-card h4 {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 6px;
}
.why-card p {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.55;
}

/* ── Location ── */
.location-card {
    max-width: 700px;
    margin: 30px auto 0;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.08);
    transition: all 0.3s ease;
}
.location-card:hover {
    border-color: var(--primary);
    box-shadow: 0 12px 35px rgba(26, 86, 219, 0.18);
    transform: translateY(-3px);
}
.location-card::before {
    content: '';
    display: block;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
}

.location-header { padding: 22px 26px 12px; text-align: left; }
.location-header h2 { font-size: 1.2rem; font-weight: 700; color: var(--text-primary); }
.location-header h2 span {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.location-details {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 0 26px 20px;
    text-align: left;
}
.location-details i { color: var(--primary); font-size: 16px; margin-top: 3px; flex-shrink: 0; }
.location-details p { color: var(--text-secondary); font-size: 13px; line-height: 1.65; }
.location-details p strong { color: var(--text-primary); display: block; font-size: 14px; margin-bottom: 3px; font-weight: 700; }

.map-frame { width: 100%; height: 240px; border: none; display: block; }

.map-link {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px;
    background: rgba(26, 86, 219, 0.05);
    border-top: 1px solid var(--border);
    color: var(--primary);
    font-weight: 700;
    font-size: 13px;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.map-link:hover {
    background: rgba(26, 86, 219, 0.12);
    color: var(--secondary);
}

/* ── CTA banner ── */
.cta-banner {
    position: relative;
    z-index: 1;
    margin: 0 20px 70px;
    max-width: 860px;
    margin-left: auto;
    margin-right: auto;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 16px;
    padding: 50px 40px;
    text-align: center;
    box-shadow: 0 12px 40px rgba(26, 86, 219, 0.3);
    overflow: hidden;
}
.cta-banner::before {
    content: '\f7d9';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    right: -20px; top: -20px;
    font-size: 180px;
    color: rgba(255,255,255,0.05);
    pointer-events: none;
}
.cta-banner h2 {
    font-size: clamp(1.4rem, 4vw, 2rem);
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 12px;
}
.cta-banner p {
    color: rgba(255,255,255,0.8);
    font-size: 14px;
    margin-bottom: 28px;
    line-height: 1.6;
}
.cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 32px;
    background: #ffffff;
    color: var(--primary);
    border-radius: 10px;
    font-weight: 700;
    font-size: 14px;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    border: none;
    cursor: pointer;
    font-family: 'Outfit', sans-serif;
}
.cta-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    background: #f0f4ff;
}

/* ── Footer ── */
footer {
    position: relative;
    z-index: 1;
    text-align: center;
    padding: 28px 20px;
    border-top: 1px solid var(--border);
    color: var(--text-secondary);
    font-size: 13px;
    background: rgba(255,255,255,0.6);
}
footer span {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 700;
}

/* ── Mobile ── */
@media (max-width: 768px) {
    .navbar { padding: 12px 16px; }
    .nav-logo { font-size: 1.1rem; }
    .btn-text { display: none; }
    .nav-btn { padding: 9px 12px; gap: 0; font-size: 15px; }
    .nav-btn i { margin: 0; }
    .nav-user .user-hi { display: none; }
    .hero { padding: 100px 16px 40px; }
    .services-grid { grid-template-columns: repeat(2, 1fr); }
    .why-grid { grid-template-columns: 1fr; }
    .cta-banner { padding: 36px 24px; margin: 0 16px 50px; }
    .stats-strip { gap: 12px; }
    .stat-pill { min-width: 110px; padding: 14px 16px; }
    .location-header { padding: 18px 18px 10px; }
    .location-details { padding: 0 18px 16px; }
}

@media (max-width: 480px) {
    .hero-buttons { flex-direction: column; align-items: center; }
    .hero-btn { width: 100%; max-width: 300px; justify-content: center; }
    .services-grid { grid-template-columns: 1fr; }
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

<!-- NAVBAR -->
<nav class="navbar">
    <a href="index.php" class="nav-logo">
        <i class="fas fa-tools"></i>
        MotorService
    </a>
    <div class="nav-actions">
        <?php if ($user_name): ?>
            <div class="nav-user">
                <i class="fas fa-user-circle" style="color:var(--primary);font-size:18px;"></i>
                <span class="user-hi">Hi, </span><span class="uname"><?= htmlspecialchars($user_name) ?></span>
            </div>
            <a href="<?= $dashboard_link ?>" class="nav-btn nav-btn-outline">
                <i class="fas fa-tachometer-alt"></i>
                <span class="btn-text">Dashboard</span>
            </a>
            <a href="logout.php" class="nav-btn nav-btn-primary">
                <i class="fas fa-sign-out-alt"></i>
                <span class="btn-text">Logout</span>
            </a>
        <?php else: ?>
            <a href="login.php" class="nav-btn nav-btn-outline">
                <i class="fas fa-sign-in-alt"></i>
                <span class="btn-text">Login</span>
            </a>
            <a href="register.php" class="nav-btn nav-btn-primary">
                <i class="fas fa-user-plus"></i>
                <span class="btn-text">Register</span>
            </a>
        <?php endif; ?>
    </div>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="hero-content">
        <div class="hero-badge">
            <i class="fas fa-star"></i> Trusted Motorcycle Service
        </div>

        <?php if ($user_name): ?>
            <h1>Welcome back, <span><?= htmlspecialchars($user_name) ?>!</span></h1>
            <p>You're logged in as <strong style="color:var(--primary);font-weight:700;"><?= ucfirst(htmlspecialchars($user_role)) ?></strong>. Head to your dashboard to manage your bookings and services.</p>
            <div class="hero-buttons">
                <a href="<?= $dashboard_link ?>" class="hero-btn hero-btn-primary">
                    <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                </a>
                <a href="logout.php" class="hero-btn hero-btn-outline">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        <?php else: ?>
            <h1>Your Motorcycle Deserves <span>Expert Care</span></h1>
            <p>Professional motorcycle repair and maintenance services at your fingertips. Book a service, track your repair, and get back on the road — fast.</p>
            <div class="hero-buttons">
                <a href="login.php" class="hero-btn hero-btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Log In
                </a>
                <a href="register.php" class="hero-btn hero-btn-outline">
                    <i class="fas fa-user-plus"></i> Create Account
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>



<!-- SERVICES -->
<section class="section">
    <div class="section-label">What We Offer</div>
    <h2 class="section-title">Our <span>Services</span></h2>
    <p class="section-sub">From routine maintenance to complex repairs — we've got you covered.</p>
    <div class="services-grid">
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-oil-can"></i></div>
            <h3>Oil Change</h3>
            <p>Keep your engine running smooth with regular oil and filter changes.</p>
        </div>
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-circle-notch"></i></div>
            <h3>Tire Change</h3>
            <p>Fast and safe tire replacement for all motorcycle types and sizes.</p>
        </div>
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-car-battery"></i></div>
            <h3>Battery Service</h3>
            <p>Battery check, replacement, and jump-start services on the spot.</p>
        </div>
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-tools"></i></div>
            <h3>General Repair</h3>
            <p>Comprehensive diagnostics and repair for any mechanical issue.</p>
        </div>
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-home"></i></div>
            <h3>Home Service</h3>
            <p>We come to you — repairs done right at your preferred location.</p>
        </div>
        <div class="service-card">
            <div class="service-icon"><i class="fas fa-calendar-check"></i></div>
            <h3>Easy Booking</h3>
            <p>Book your service online in minutes, any time of the day.</p>
        </div>
    </div>
</section>

<!-- WHY CHOOSE US -->
<section class="section" style="padding-top: 0;">
    <div class="section-label">Why MotorService</div>
    <h2 class="section-title">Why <span>Choose Us</span></h2>
    <p class="section-sub">We make motorcycle servicing convenient, transparent, and reliable.</p>
    <div class="why-grid">
        <div class="why-card">
            <div class="why-icon"><i class="fas fa-user-check"></i></div>
            <div>
                <h4>Certified Mechanics</h4>
                <p>Our team consists of trained and experienced motorcycle technicians.</p>
            </div>
        </div>
        <div class="why-card">
            <div class="why-icon"><i class="fas fa-clock"></i></div>
            <div>
                <h4>On-Time Service</h4>
                <p>We respect your time — scheduled slots run on time, every time.</p>
            </div>
        </div>
        <div class="why-card">
            <div class="why-icon"><i class="fas fa-map-marker-alt"></i></div>
            <div>
                <h4>Home or Shop</h4>
                <p>Choose between visiting our shop or having us come to you.</p>
            </div>
        </div>
        <div class="why-card">
            <div class="why-icon"><i class="fas fa-receipt"></i></div>
            <div>
                <h4>Transparent Pricing</h4>
                <p>No hidden fees. See the full cost before you confirm any booking.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA BANNER -->
<?php if (!$user_name): ?>
<div class="cta-banner" style="margin-bottom:70px;">
    <h2><i class="fas fa-calendar-plus"></i> Ready to Book Your Service?</h2>
    <p>Create a free account and schedule your first motorcycle service in minutes.</p>
    <a href="register.php" class="cta-btn">
        <i class="fas fa-user-plus"></i> Get Started — It's Free
    </a>
</div>
<?php endif; ?>

<!-- LOCATION -->
<section class="section" style="padding-top: 0; padding-bottom: 80px;">
    <div class="section-label">Find Us</div>
    <h2 class="section-title">Our <span>Location</span></h2>
    <p class="section-sub">Visit us at the shop or book a home service — we're always nearby.</p>

    <div class="location-card">
        <div class="location-header">
            <h2>🔧 <span>KAWASAKI</span> Malolos Moto-shop</h2>
        </div>
        <div class="location-details">
            <i class="fas fa-map-marker-alt"></i>
            <p>
                <strong>Near Caingin Elementary School, Malolos, Bulacan</strong>
                Open for walk-ins and scheduled bookings. Visit us for all your motorcycle repair and maintenance needs.
            </p>
        </div>
        <iframe
            class="map-frame"
            src="https://www.openstreetmap.org/export/embed.html?bbox=120.78800%2C14.84200%2C120.80200%2C14.85400&layer=mapnik&marker=14.84800%2C120.79500"
            allowfullscreen
            loading="lazy"
            title="Shop Location - Kawasaki Malolos">
        </iframe>
        <a href="https://www.google.com/maps/search/Caingin+Elementary+School+Malolos+Bulacan" target="_blank" class="map-link">
            <i class="fas fa-directions"></i> Get Directions on Google Maps
        </a>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <p>&copy; <?= date('Y') ?> <span>MotorService</span>. All rights reserved. — Ride safe, ride smart! 🏍️</p>
</footer>

</body>
</html>