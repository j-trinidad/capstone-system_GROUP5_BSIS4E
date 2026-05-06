<?php
session_start();
require '../../includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    exit("Unauthorized");
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';

$messages = [];
$users = [];
$target_id = 0;

// ✅ MECHANIC ↔ ADMIN CHAT
if (isset($_GET['admin_chat'])) {
    // Find admin automatically
    $adminStmt = $pdo->query("SELECT id, display_name, profile_pic FROM users WHERE role = 'admin' LIMIT 1");
    $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
    if (!$admin) {
        exit('<div class="no-messages">No admin account found.</div>');
    }
    $target_id = $admin['id'];

    $stmt = $pdo->prepare("
        SELECT * FROM messages
        WHERE (sender_id = :mech1 AND receiver_id = :admin1)
           OR (sender_id = :admin2 AND receiver_id = :mech2)
        ORDER BY created_at ASC
    ");
    $stmt->execute([
        'mech1' => $user_id,
        'admin1' => $target_id,
        'admin2' => $target_id,
        'mech2' => $user_id
    ]);
}

// ✅ MECHANIC ↔ CUSTOMER CHAT
elseif (isset($_GET['customer_id'])) {
    $customer_id = (int)$_GET['customer_id'];
    if ($customer_id === 0) {
        exit('<div class="no-messages">No customer selected.</div>');
    }

    $target_id = $customer_id;
    
    // Mark messages from customer as read
    $mark_read_stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    $mark_read_stmt->execute([$customer_id, $user_id]);
    
    $stmt = $pdo->prepare("
        SELECT * FROM messages
        WHERE (sender_id = :mech1 AND receiver_id = :cust1)
           OR (sender_id = :cust2 AND receiver_id = :mech2)
        ORDER BY created_at ASC
    ");
    $stmt->execute([
        'mech1' => $user_id,
        'cust1' => $target_id,
        'cust2' => $target_id,
        'mech2' => $user_id
    ]);
}

// ❌ NO VALID MODE
else {
    exit('<div class="no-messages">No conversation selected.</div>');
}

// ✅ Fetch messages
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Fetch user info (mechanic + target)
$usersStmt = $pdo->prepare("SELECT id, display_name, profile_pic FROM users WHERE id IN (?, ?)");
$usersStmt->execute([$user_id, $target_id]);
while ($row = $usersStmt->fetch(PDO::FETCH_ASSOC)) {
    $users[$row['id']] = $row;
}

// ✅ Output as JSON for AJAX
$messageData = [];
foreach ($messages as $msg) {
    $messageData[] = [
        'sender_id' => $msg['sender_id'],  // 🆕 Add this for reliable checking
        'name' => $users[$msg['sender_id']]['display_name'] ?? 'Unknown',
        'profile_pic' => '../../uploads/profile_pics/' . ($users[$msg['sender_id']]['profile_pic'] ?? 'default.jpg'),
        'message_text' => $msg['message_text'] ?? '',
        'created_at' => $msg['created_at']
    ];
}

echo json_encode($messageData);
?>