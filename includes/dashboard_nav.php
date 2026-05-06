<?php
// includes/dashboard_nav.php
// Usage: include '../../includes/dashboard_nav.php'; then call renderSidebar('admin') and renderTopbar()
function renderSidebar($role) {
    $logo = '/motor_service/assets/img/logo.png';
    $html = '<div class="sidebar"><div class="logo-area"><img src="'.$logo.'" alt="logo"><h3>Motor Service</h3></div><nav>';
    if ($role === 'admin') {
        $html .= navLink('admin_dashboard.php', '🏠 Dashboard', true);
        $html .= navLink('../manage_users.php', '👥 Manage Users');
        $html .= navLink('../manage_services.php', '🧾 Services');
        $html .= navLink('../reports.php', '📊 Reports');
    } elseif ($role === 'mechanic') {
        $html .= navLink('mechanic_dashboard.php', '🏠 Dashboard', true);
        $html .= navLink('assigned_jobs.php', '🛠 Assigned Jobs');
        $html .= navLink('service_history.php', '📋 Job History');
    } else {
        $html .= navLink('customer_dashboard.php', '🏠 Dashboard', true);
        $html .= navLink('request_service.php', '📅 Book Service');
        $html .= navLink('track_request.php', '📍 Track Request');
        $html .= navLink('invoices.php', '🧾 Invoices');
    }
    $html .= '</nav><a class="logout" href="/motor_service/logout.php">🚪 Logout</a></div>';
    echo $html;
}

function navLink($href, $label, $active = false) {
    $cls = $active ? 'class="nav-item active"' : 'class="nav-item"';
    return "<a $cls href=\"/motor_service/dashboards/{$href}\">{$label}</a>";
}

function renderTopbar() {
    $user = $_SESSION['user_name'] ?? 'User';
    $role = $_SESSION['user_role'] ?? 'customer';
    echo '<div class="topbar">
            <div class="left"><h2 class="page-title">Dashboard</h2></div>
            <div class="right">
              <div class="user-info">
                <span class="role-badge">'.htmlspecialchars($role).'</span>
                <span class="user-name">'.htmlspecialchars($user).'</span>
                <a href="/motor_service/logout.php" class="icon-logout">Logout</a>
              </div>
            </div>
          </div>';
}
