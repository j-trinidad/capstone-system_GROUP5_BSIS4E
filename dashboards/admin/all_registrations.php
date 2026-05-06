<?php
require '../../includes/session_check.php';
require '../../includes/db_connect.php';

checkRole('admin');

// Search logic
$search = strtolower($_GET['search'] ?? "");
$sort = $_GET['sort'] ?? "";

// Build query
$query = "SELECT id, first_name, last_name, email, created_at FROM users WHERE role = 'customer' ORDER BY ";

if ($sort === "newest") {
    $query .= "created_at DESC";
} elseif ($sort === "oldest") {
    $query .= "created_at ASC";
} else {
    $query .= "created_at DESC";
}

$stmt = $pdo->prepare($query);
$stmt->execute();
$allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter by search term
if (!empty($search)) {
    $allUsers = array_filter($allUsers, function($u) use ($search) {
        return str_contains(strtolower($u['first_name']), $search)
            || str_contains(strtolower($u['last_name']), $search)
            || str_contains(strtolower($u['email']), $search);
    });
}

$totalCustomers = count($allUsers);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>All Customer Registrations - MotorService</title>
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

body {
    font-family: 'Outfit', sans-serif;
    background: linear-gradient(135deg, var(--dark-bg), #1a1f3a);
    color: var(--text-primary);
    min-height: 100vh;
    padding: 40px 20px;
}

a {
    color: inherit;
    text-decoration: none;
}

.container {
    max-width: 1100px;
    margin: 0 auto;
    background: var(--card-bg);
    padding: 40px;
    border-radius: 20px;
    border: 1px solid var(--border);
    box-shadow: 0 10px 35px rgba(0, 0, 0, 0.3);
}

h1 {
    margin: 0 0 10px 0;
    text-align: center;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-size: 2.2rem;
    font-weight: 700;
}

.header-info {
    text-align: center;
    color: var(--text-secondary);
    font-size: 14px;
    margin-bottom: 30px;
}

.toolbar {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    justify-content: center;
    margin-bottom: 25px;
    background: rgba(255, 140, 0, 0.05);
    padding: 20px;
    border-radius: 12px;
    border: 1px solid var(--border);
}

.toolbar input,
.toolbar select {
    padding: 12px 16px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    font-size: 14px;
    font-family: 'Outfit', sans-serif;
    transition: all 0.3s ease;
    flex: 1;
    min-width: 200px;
}

.toolbar input:focus,
.toolbar select:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(255, 140, 0, 0.1);
    box-shadow: 0 0 10px rgba(255, 140, 0, 0.2);
}

.toolbar input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.toolbar button {
    padding: 12px 30px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border: none;
    color: #1a1f3a;
    font-weight: 700;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 15px rgba(255, 140, 0, 0.3);
}

.toolbar button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 140, 0, 0.4);
}

.table-wrapper {
    overflow-x: auto;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

table {
    width: 100%;
    border-collapse: collapse;
    background: var(--card-bg);
}

thead {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
}

th {
    color: #1a1f3a;
    padding: 16px;
    text-align: left;
    font-weight: 700;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

td {
    padding: 16px;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
}

tbody tr {
    transition: all 0.2s ease;
}

tbody tr:hover {
    background: rgba(255, 140, 0, 0.05);
}

tbody tr:last-child td {
    border-bottom: none;
}

.customer-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #1a1f3a;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
}

.customer-info {
    display: flex;
    flex-direction: column;
}

.customer-name {
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.customer-email {
    font-size: 12px;
    color: var(--text-secondary);
    margin: 3px 0 0 0;
}

.date-cell {
    color: var(--text-secondary);
    font-size: 13px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.3;
}

.empty-state p {
    font-size: 16px;
    margin: 10px 0;
}

.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 140, 0, 0.1);
    color: var(--primary);
    padding: 10px 20px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    margin-bottom: 20px;
    border: 1px solid var(--primary);
    font-size: 13px;
}

.back-btn:hover {
    background: rgba(255, 140, 0, 0.2);
    transform: translateX(-3px);
}

@media (max-width: 768px) {
    .container {
        padding: 20px;
    }

    h1 {
        font-size: 1.8rem;
    }

    .toolbar {
        flex-direction: column;
    }

    .toolbar input,
    .toolbar select {
        min-width: auto;
    }

    th, td {
        padding: 12px;
        font-size: 12px;
    }

    .avatar {
        width: 38px;
        height: 38px;
        font-size: 12px;
    }

    .customer-name {
        font-size: 13px;
    }

    .customer-email {
        font-size: 11px;
    }
}
</style>
</head>

<body>

<div class="container">
    <a class="back-btn" href="admin_dashboard.php">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <h1><i class="fas fa-users"></i> All Customer Registrations</h1>
    <div class="header-info">
        <i class="fas fa-users-circle"></i> Total: <strong><?= number_format($totalCustomers) ?></strong> customer<?= $totalCustomers !== 1 ? 's' : '' ?>
    </div>

    <!-- Search + Sort -->
    <form method="GET" class="toolbar">
        <input type="text" name="search" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>">

        <select name="sort">
            <option value="">Sort by date (newest first)</option>
            <option value="newest" <?= $sort == "newest" ? "selected" : "" ?>>Newest first</option>
            <option value="oldest" <?= $sort == "oldest" ? "selected" : "" ?>>Oldest first</option>
        </select>

        <button type="submit">
            <i class="fas fa-search"></i> Apply Filter
        </button>
    </form>

    <!-- Table -->
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th style="width: 40%;"><i class="fas fa-user"></i> Customer</th>
                    <th style="width: 30%;"><i class="fas fa-envelope"></i> Email</th>
                    <th style="width: 30%;"><i class="fas fa-calendar"></i> Date Registered</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allUsers)): ?>
                    <tr>
                        <td colspan="3">
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No customers found</p>
                                <p style="font-size: 13px; color: var(--text-secondary);">Try adjusting your search criteria</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($allUsers as $u): 
                        $initials = strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1));
                    ?>
                    <tr>
                        <td>
                            <div class="customer-cell">
                                <div class="avatar"><?= $initials ?></div>
                                <div class="customer-info">
                                    <p class="customer-name"><?= htmlspecialchars($u['first_name'] . " " . $u['last_name']) ?></p>
                                    <p class="customer-email"><?= htmlspecialchars($u['email']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td class="date-cell"><?= date("M d, Y • h:i A", strtotime($u['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>