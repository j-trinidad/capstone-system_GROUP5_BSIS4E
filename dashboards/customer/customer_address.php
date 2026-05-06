<?php
require '../../includes/session_check.php';
checkRole('customer');
require '../../includes/db_connect.php';

// Path to locations.json (Bulacan cities & barangays)
$locationsFile = __DIR__ . '/../../assets/data/locations.json';
$locations = [];
if (file_exists($locationsFile)) {
    $json = file_get_contents($locationsFile);
    $locations = json_decode($json, true) ?: [];
} else {
    die("Error: locations.json not found.");
}

// Handle AJAX request for barangays
if (isset($_GET['action']) && $_GET['action'] === 'barangays') {
    $city = $_GET['city'] ?? '';
    $result = [];
    if ($city && isset($locations[$city])) {
        $result = $locations[$city];
    }
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get counts for badges
$notifStmt = $pdo->prepare("SELECT COUNT(*) FROM customer_notifications WHERE customer_id = ? AND is_read = 0");
$notifStmt->execute([$user_id]);
$unreadNotifications = $notifStmt->fetchColumn();

$msgStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$msgStmt->execute([$user_id]);
$messages = $msgStmt->fetchColumn();

// Get saved addresses
$stmt = $pdo->prepare("SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address  = trim($_POST['address']  ?? '');
    $city     = trim($_POST['city']     ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $lat      = trim($_POST['lat']      ?? '');
    $lng      = trim($_POST['lng']      ?? '');
    $id       = intval($_POST['id']     ?? 0);

    try {
        if (isset($_POST['add_address']) && $address) {
            $stmt = $pdo->prepare("INSERT INTO customer_addresses (customer_id, address, city, barangay, lat, lng, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $address, $city, $barangay, $lat, $lng]);
        } elseif (isset($_POST['edit_address']) && $id && $address) {
            $stmt = $pdo->prepare("UPDATE customer_addresses SET address = ?, city = ?, barangay = ?, lat = ?, lng = ? WHERE id = ? AND customer_id = ?");
            $stmt->execute([$address, $city, $barangay, $lat, $lng, $id, $user_id]);
        } elseif (isset($_POST['delete_address']) && $id) {
            $stmt = $pdo->prepare("DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?");
            $stmt->execute([$id, $user_id]);
        }
        header("Location: customer_address.php");
        exit;
    } catch (Exception $e) {
        die("Error saving address: " . $e->getMessage());
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Addresses — MotorService</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
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
    --warning:        #d97706;
}
* { margin:0; padding:0; box-sizing:border-box; }
html, body {
    height:100%; font-family:'Outfit',sans-serif;
    background:linear-gradient(135deg,#f0f4ff,#e8eeff);
    color:var(--text-primary); overflow:hidden;
}
a { color:inherit; text-decoration:none; }

/* ── Hamburger ── */
.hamburger-btn {
    display:none; position:fixed; top:20px; left:20px; z-index:1001;
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    border:none; width:50px; height:50px; border-radius:12px;
    cursor:pointer; box-shadow:0 4px 15px rgba(26,86,219,0.4); transition:all .3s;
}
.hamburger-btn:hover { transform:scale(1.05); box-shadow:0 6px 20px rgba(26,86,219,0.6); }
.hamburger-btn span {
    display:block; width:25px; height:3px; background:#ffffff;
    margin:5px auto; border-radius:2px; transition:all .3s;
}
.hamburger-btn.active span:nth-child(1){transform:rotate(45deg) translate(8px,8px)}
.hamburger-btn.active span:nth-child(2){opacity:0}
.hamburger-btn.active span:nth-child(3){transform:rotate(-45deg) translate(7px,-7px)}

/* ── Notification Bell ── */
.notification-bell {
    position:fixed; top:20px; right:20px; z-index:1001;
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    border:none; width:50px; height:50px; border-radius:50%;
    cursor:pointer; box-shadow:0 4px 15px rgba(26,86,219,0.4); transition:all .3s;
    display:flex; align-items:center; justify-content:center;
    font-size:20px; color:#ffffff;
}
.notification-bell:hover { transform:scale(1.1); box-shadow:0 6px 20px rgba(26,86,219,0.6); }
.notification-bell .bell-badge {
    position:absolute; top:-5px; right:-5px;
    background:#ff0000; color:#fff; width:22px; height:22px; border-radius:50%;
    font-size:11px; font-weight:700;
    display:flex; align-items:center; justify-content:center;
    box-shadow:0 0 10px rgba(255,0,0,0.8); animation:pulse 2s infinite;
}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.1)}}

/* ── Sidebar Overlay ── */
.sidebar-overlay {
    display:none; position:fixed; inset:0;
    background:rgba(26,86,219,0.1); z-index:999; opacity:0; transition:opacity .3s;
}
.sidebar-overlay.active{display:block;opacity:1}

/* ── Sidebar ── */
.sidebar {
    position:fixed; top:0; left:0; width:260px; height:100vh; overflow-y:auto;
    background:linear-gradient(180deg,#1e3a8a,#1a56db);
    border-right:1px solid rgba(255,255,255,0.1);
    display:flex; flex-direction:column; padding:25px 20px;
    z-index:1000; transition:transform .3s; max-height:100vh;
}
.sidebar::-webkit-scrollbar{width:6px}
.sidebar::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.2);border-radius:3px}
.logo {
    font-size:1.6rem; font-weight:700; color:#ffffff;
    margin-bottom:35px; display:flex; align-items:center; gap:10px; letter-spacing:.5px;
}
.sidebar nav { display:flex; flex-direction:column; gap:8px; flex:1; }
.sidebar nav a {
    display:flex; align-items:center; gap:12px; padding:12px 16px; border-radius:10px;
    color:rgba(255,255,255,0.75); font-weight:600; transition:all .3s; font-size:14px;
}
.sidebar nav a:hover, .sidebar nav a.active {
    background:rgba(255,255,255,0.2); color:#ffffff; transform:translateX(5px);
}
.sidebar nav a i { width:20px; text-align:center; }
.notification-badge {
    display:inline-flex; align-items:center; justify-content:center;
    background:#ff0000; color:#fff; width:18px; height:18px; min-width:18px;
    font-size:10px; font-weight:700; border-radius:50%; margin-left:auto;
    box-shadow:0 0 8px rgba(255,0,0,0.7);
}
.logout-btn {
    background:linear-gradient(135deg,#ff4444,#c82333)!important;
    color:var(--text-primary)!important; margin-top:auto!important; justify-content:center!important;
}
.sidebar .footer {
    margin-top:auto; font-size:12px; color:rgba(255,255,255,0.5);
    text-align:center; padding-top:20px; border-top:1px solid rgba(255,255,255,0.15);
}

/* ── Main ── */
.main {
    margin-left:260px; padding:40px; width:calc(100% - 260px);
    height:100vh; overflow-y:auto;
    background:linear-gradient(135deg,#f0f4ff,#e8eeff);
}
.main::-webkit-scrollbar{width:8px}
.main::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}

/* ── Page Header ── */
.page-header {
    font-size:26px; font-weight:700; margin-bottom:8px;
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
    display:flex; align-items:center; gap:12px;
}
.page-sub { color:var(--text-secondary); font-size:14px; margin-bottom:30px; }

/* ── Add Button ── */
.action-bar { margin-bottom:24px; }
.btn-add {
    display:inline-flex; align-items:center; gap:8px;
    padding:11px 22px; border-radius:10px; border:none;
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    color:#ffffff; font-weight:700; font-size:13px; cursor:pointer;
    box-shadow:0 4px 12px rgba(26,86,219,0.25); transition:all .3s;
    text-transform:uppercase; letter-spacing:.5px;
}
.btn-add:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(26,86,219,0.35); }

/* ── Panel ── */
.panel {
    background:var(--card-bg); border:1px solid var(--border); border-radius:14px;
    padding:25px; box-shadow:0 4px 15px rgba(26,86,219,0.08);
}

/* ── Address Item ── */
.address-item {
    background:rgba(26,86,219,0.04); border:1px solid var(--border);
    border-radius:12px; padding:20px;
    display:flex; justify-content:space-between; align-items:center;
    margin-bottom:14px; transition:all .3s; position:relative; overflow:hidden;
}
.address-item::before {
    content:''; position:absolute; left:0; top:0; bottom:0; width:4px;
    background:linear-gradient(180deg,var(--primary),var(--secondary));
    border-radius:4px 0 0 4px;
}
.address-item:last-child { margin-bottom:0; }
.address-item:hover {
    transform:translateY(-3px); border-color:var(--primary);
    box-shadow:0 8px 25px rgba(26,86,219,0.15);
}
.address-info { padding-left:8px; }
.address-text {
    font-weight:700; font-size:15px;
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
}
.address-sub { color:var(--text-secondary); margin-top:6px; font-size:13px; font-weight:500; }

/* ── Address Action Buttons ── */
.address-actions { display:flex; gap:10px; flex-shrink:0; }
.btn-small {
    display:inline-flex; align-items:center; gap:6px;
    padding:8px 14px; border-radius:8px; border:none;
    background:rgba(26,86,219,0.08); color:var(--primary);
    font-weight:700; font-size:12px; cursor:pointer; transition:all .3s;
    text-transform:uppercase; letter-spacing:.4px;
}
.btn-small:hover { background:rgba(26,86,219,0.18); transform:scale(1.04); }
.btn-delete { background:rgba(220,38,38,0.08)!important; color:#dc2626!important; }
.btn-delete:hover { background:rgba(220,38,38,0.18)!important; }

/* ── Empty State ── */
.empty-state {
    text-align:center; padding:70px 20px; color:var(--text-secondary);
}
.empty-state i {
    font-size:55px; opacity:.35; margin-bottom:18px; display:block;
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
}
.empty-state h3 { font-size:20px; margin-bottom:10px; color:var(--text-primary); }
.empty-state p  { font-size:14px; margin-bottom:20px; }

/* ── Modal ── */
.modal {
    display:none; position:fixed; inset:0;
    background:rgba(15,23,42,0.7); z-index:9999;
    justify-content:center; align-items:center; padding:20px;
    animation:modalFadeIn .3s ease;
}
.modal.show { display:flex; }
@keyframes modalFadeIn{from{opacity:0}to{opacity:1}}

.modal-content {
    background:var(--card-bg); border:1px solid var(--border);
    border-radius:16px; padding:32px; width:100%; max-width:650px;
    position:relative; max-height:90vh; overflow-y:auto;
    box-shadow:0 20px 60px rgba(26,86,219,0.2);
    animation:modalSlideIn .35s ease;
}
@keyframes modalSlideIn{
    from{transform:scale(0.93) translateY(-16px);opacity:0}
    to{transform:scale(1) translateY(0);opacity:1}
}
.modal-content::-webkit-scrollbar{width:6px}
.modal-content::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}

.modal-header {
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:24px;
}
.modal-header h3 {
    font-size:20px; font-weight:700;
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
}
.close-btn {
    width:36px; height:36px; border-radius:50%; border:none;
    background:rgba(26,86,219,0.08); color:var(--text-secondary);
    font-size:20px; cursor:pointer; display:flex; align-items:center;
    justify-content:center; transition:all .3s; flex-shrink:0;
}
.close-btn:hover { background:rgba(220,38,38,0.1); color:#dc2626; }

/* ── Form Elements ── */
.form-label {
    display:block; margin-top:16px; margin-bottom:6px;
    color:var(--primary); font-weight:700; font-size:11px;
    text-transform:uppercase; letter-spacing:.6px;
}
input[type="text"], select {
    width:100%; padding:11px 14px; border-radius:9px;
    border:1px solid var(--border); background:#f8faff;
    color:var(--text-primary); font-size:13px; transition:all .3s;
    font-family:'Outfit',sans-serif;
}
input[type="text"]:focus, select:focus {
    border-color:var(--primary); outline:none;
    box-shadow:0 0 0 3px rgba(26,86,219,0.12); background:#ffffff;
}
select option { background:#ffffff; color:var(--text-primary); }
input[type="hidden"] { display:none; }

.form-grid {
    display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:4px;
}

#address-map {
    height:250px; border-radius:10px; margin-top:16px;
    border:1px solid var(--border); display:none;
    opacity:0; transition:opacity .3s ease-in-out;
}
.modal.show #address-map { opacity:1; display:block; }

.form-buttons {
    display:flex; gap:10px; justify-content:flex-end; margin-top:24px;
}
.btn-modal-cancel {
    display:inline-flex; align-items:center; gap:7px;
    padding:10px 20px; border-radius:10px; border:1px solid var(--border);
    background:transparent; color:var(--text-secondary);
    font-weight:700; font-size:13px; cursor:pointer; transition:all .3s;
}
.btn-modal-cancel:hover { background:rgba(26,86,219,0.06); color:var(--text-primary); }
.btn-modal-save {
    display:inline-flex; align-items:center; gap:7px;
    padding:10px 22px; border-radius:10px; border:none;
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    color:#ffffff; font-weight:700; font-size:13px; cursor:pointer;
    box-shadow:0 4px 12px rgba(26,86,219,0.25); transition:all .3s;
}
.btn-modal-save:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(26,86,219,0.35); }

/* ── Responsive ── */
@media(max-width:768px){
    .hamburger-btn{display:block}
    .sidebar{transform:translateX(-100%)}
    .sidebar.active{transform:translateX(0)}
    .main{margin-left:0;width:100%;padding:80px 20px 20px}
    .form-grid{grid-template-columns:1fr}
    .address-item{flex-direction:column;align-items:flex-start;gap:14px}
    .address-actions{width:100%;justify-content:flex-end}
    .modal-content{padding:22px}
    .btn-add{width:100%;justify-content:center}
}
</style>
</head>
<body>

<button class="hamburger-btn" id="hamburger-btn"><span></span><span></span><span></span></button>

<button class="notification-bell" id="notification-bell">
    <i class="fas fa-bell"></i>
    <?php if($unreadNotifications > 0): ?>
        <span class="bell-badge"><?= $unreadNotifications ?></span>
    <?php endif; ?>
</button>

<div class="sidebar-overlay" id="sidebar-overlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="logo"><i class="fas fa-tools"></i><span>MotorService</span></div>
    <nav>
        <a href="customer_dashboard.php"       <?= $currentPage==='customer_dashboard.php'      ?'class="active"':'' ?>><i class="fas fa-home"></i> Home</a>
        <a href="customer_my_bookings.php"      <?= $currentPage==='customer_my_bookings.php'     ?'class="active"':'' ?>><i class="fas fa-calendar-alt"></i> My Bookings</a>
        <a href="customer_history.php"          <?= $currentPage==='customer_history.php'         ?'class="active"':'' ?>><i class="fas fa-history"></i> History</a>
        <a href="customer_messages.php"         <?= $currentPage==='customer_messages.php'        ?'class="active"':'' ?>>
            <i class="fas fa-envelope"></i> Messages
            <?php if($messages > 0): ?><span class="notification-badge"><?= $messages ?></span><?php endif; ?>
        </a>
        <a href="customer_my_receipts.php"      <?= $currentPage==='customer_my_receipts.php'     ?'class="active"':'' ?>><i class="fas fa-receipt"></i> My Receipts</a>
        <a href="customer_address.php"          <?= $currentPage==='customer_address.php'         ?'class="active"':'' ?>><i class="fas fa-map-marker-alt"></i> Address</a>
        <a href="customer_profile.php"          <?= $currentPage==='customer_profile.php'         ?'class="active"':'' ?>><i class="fas fa-user-circle"></i> Profile</a>
        <a href="customer_change_password.php"  <?= $currentPage==='customer_change_password.php' ?'class="active"':'' ?>><i class="fas fa-lock"></i> Change Password</a>
        <a href="../../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
    <div class="footer">v1.0 • <?= date('Y') ?></div>
</aside>

<main class="main">
    <div class="page-header"><i class="fas fa-map-marker-alt"></i> Manage Addresses</div>
    <p class="page-sub">Save your service locations for faster booking.</p>

    <div class="action-bar">
        <button class="btn-add" onclick="openModal()">
            <i class="fas fa-plus"></i> Add Address
        </button>
    </div>

    <div class="panel">
        <?php if (!$addresses): ?>
            <div class="empty-state">
                <i class="fas fa-map-marked-alt"></i>
                <h3>No saved addresses yet</h3>
                <p>Add your service location to get started.</p>
                <button class="btn-add" onclick="openModal()">
                    <i class="fas fa-plus"></i> Add Your First Address
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($addresses as $a): ?>
            <div class="address-item">
                <div class="address-info">
                    <div class="address-text">
                        <i class="fas fa-map-marker-alt" style="font-size:13px;margin-right:6px;"></i>
                        <?= htmlspecialchars($a['address']) ?>
                    </div>
                    <div class="address-sub">
                        <i class="fas fa-city" style="margin-right:5px;"></i>
                        <?= htmlspecialchars($a['city']) ?>, <?= htmlspecialchars($a['barangay']) ?>
                    </div>
                </div>
                <div class="address-actions">
                    <button type="button" class="btn-small edit-btn"
                        data-id="<?= $a['id'] ?>"
                        data-address="<?= htmlspecialchars($a['address'], ENT_QUOTES) ?>"
                        data-city="<?= htmlspecialchars($a['city'], ENT_QUOTES) ?>"
                        data-barangay="<?= htmlspecialchars($a['barangay'], ENT_QUOTES) ?>"
                        data-lat="<?= $a['lat'] ?>"
                        data-lng="<?= $a['lng'] ?>">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <form method="POST" onsubmit="return confirm('Delete this address?');" style="display:inline;">
                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                        <button type="submit" name="delete_address" class="btn-small btn-delete">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Modal -->
<div class="modal" id="address-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title">Add New Address</h3>
            <button class="close-btn" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>

        <form method="POST" id="address-form">
            <input type="hidden" id="edit-id"  name="id">
            <input type="hidden" id="lat"       name="lat">
            <input type="hidden" id="lng"       name="lng">

            <label class="form-label">Full Address</label>
            <input type="text" id="address" name="address" required placeholder="House #, Street, Purok, etc.">

            <div class="form-grid">
                <div>
                    <label class="form-label">City / Municipality</label>
                    <select id="city-select" name="city" required>
                        <option value="">— Select City —</option>
                        <?php foreach ($locations as $city => $barangays): ?>
                            <option value="<?= htmlspecialchars($city) ?>"><?= htmlspecialchars($city) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Barangay</label>
                    <select id="barangay-select" name="barangay" required>
                        <option value="">— Select Barangay —</option>
                    </select>
                </div>
            </div>

            <div id="address-map"></div>

            <div class="form-buttons">
                <button type="button" class="btn-modal-cancel" onclick="closeModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" id="submit-btn" name="add_address" class="btn-modal-save">
                    <i class="fas fa-save"></i> Save Address
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
// Hamburger
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

document.getElementById('notification-bell').addEventListener('click', () => {
    window.location.href = 'customer_notifications.php';
});

document.addEventListener('DOMContentLoaded', function () {
    let map, marker;
    const modal          = document.getElementById('address-modal');
    const citySelect     = document.getElementById('city-select');
    const barangaySelect = document.getElementById('barangay-select');
    const latInput       = document.getElementById('lat');
    const lngInput       = document.getElementById('lng');
    const submitBtn      = document.getElementById('submit-btn');
    const addressInput   = document.getElementById('address');

    function initMap() {
        if (!map) {
            try {
                map = L.map('address-map', { minZoom:9, maxZoom:17 }).setView([14.8, 120.9], 10);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);
            } catch(e) { console.error('Map init error:', e); }
        }
        document.getElementById('address-map').style.display = 'block';
    }

    function addMarker(lat, lng, barangay = '', city = '') {
        if (marker) marker.remove();
        marker = L.marker([lat, lng], { draggable:true })
            .addTo(map)
            .bindPopup(`<b>${barangay}</b><br>${city}`)
            .openPopup();
        map.setView([lat, lng], 15);
        marker.on('dragend', function(e) {
            const pos = e.target.getLatLng();
            latInput.value = pos.lat;
            lngInput.value = pos.lng;
        });
        latInput.value = lat;
        lngInput.value = lng;
    }

    function updateMarker() {
        const city     = citySelect.value;
        const barangay = barangaySelect.value;
        if (!city || !barangay || !map) return;
        const fullLocation = `${barangay}, ${city}, Bulacan, Philippines`;
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(fullLocation)}`)
            .then(res => res.json())
            .then(data => {
                if (data.length > 0) {
                    addMarker(parseFloat(data[0].lat), parseFloat(data[0].lon), barangay, city);
                }
            })
            .catch(err => console.error('Geocode error:', err));
    }

    window.openModal = function () {
        document.getElementById('modal-title').textContent = 'Add New Address';
        submitBtn.name = 'add_address';
        citySelect.value = '';
        barangaySelect.innerHTML = '<option value="">— Select Barangay —</option>';
        document.getElementById('edit-id').value = '';
        addressInput.value = '';
        latInput.value = '';
        lngInput.value = '';
        modal.classList.add('show');
        initMap();
        setTimeout(() => { if (map) map.invalidateSize(); }, 300);
    };

    window.closeModal = function () { modal.classList.remove('show'); };

    function loadBarangays(city, callback) {
        barangaySelect.innerHTML = '<option value="">Loading...</option>';
        barangaySelect.value = '';
        if (!city) {
            barangaySelect.innerHTML = '<option value="">— Select Barangay —</option>';
            if (typeof callback === 'function') callback();
            return;
        }
        fetch(`?action=barangays&city=${encodeURIComponent(city)}`)
            .then(res => res.json())
            .then(list => {
                barangaySelect.innerHTML = '<option value="">— Select Barangay —</option>';
                list.forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b; opt.textContent = b;
                    barangaySelect.appendChild(opt);
                });
                if (typeof callback === 'function') callback();
            })
            .catch(err => {
                console.error('Barangays load error:', err);
                barangaySelect.innerHTML = '<option value="">Error loading barangays</option>';
            });
    }

    citySelect.addEventListener('change', function () {
        barangaySelect.value = '';
        loadBarangays(this.value);
    });

    barangaySelect.addEventListener('change', function () { updateMarker(); });

    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('modal-title').textContent = 'Edit Address';
            submitBtn.name = 'edit_address';
            document.getElementById('edit-id').value = btn.dataset.id;
            addressInput.value  = btn.dataset.address;
            latInput.value      = btn.dataset.lat;
            lngInput.value      = btn.dataset.lng;

            const selectedCity     = btn.dataset.city;
            const selectedBarangay = btn.dataset.barangay;
            citySelect.value = selectedCity;

            loadBarangays(selectedCity, () => {
                barangaySelect.value = selectedBarangay;
                modal.classList.add('show');
                initMap();
                setTimeout(() => {
                    if (map) map.invalidateSize();
                    if (btn.dataset.lat && btn.dataset.lng) {
                        addMarker(parseFloat(btn.dataset.lat), parseFloat(btn.dataset.lng), selectedBarangay, selectedCity);
                    }
                }, 300);
            });
        });
    });
});
</script>

</body>
</html>