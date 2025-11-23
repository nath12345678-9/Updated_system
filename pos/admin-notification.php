<?php
session_start();
include 'db/db_connect.php';

// Handle payment confirmation/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_payment'])) {
        $sale_id = intval($_POST['sale_id']);
        
        // Check which columns exist in sales table
        $columns_check = $conn->query("SHOW COLUMNS FROM sales");
        $existing_columns = [];
        while ($col = $columns_check->fetch_assoc()) {
            $existing_columns[] = $col['Field'];
        }
        
        // Build UPDATE query based on existing columns
        $update_parts = ["payment_status = 'confirmed'"];
        
        if (in_array('status', $existing_columns)) {
            $update_parts[] = "status = 'successful'";
        }
        if (in_array('payment_confirmed_at', $existing_columns)) {
            $update_parts[] = "payment_confirmed_at = NOW()";
        }
        
        $update_query = "UPDATE sales SET " . implode(', ', $update_parts) . " WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        
        if (!$stmt) {
            $_SESSION['error_message'] = "Database error: " . $conn->error;
            header("Location: admin-notifications.php");
            exit;
        }
        
        $stmt->bind_param("i", $sale_id);
        
        if ($stmt->execute()) {
            // Mark notification as read
            $notif_stmt = $conn->prepare("UPDATE admin_notifications SET is_read = 1 WHERE sale_id = ?");
            $notif_stmt->bind_param("i", $sale_id);
            $notif_stmt->execute();
            $notif_stmt->close();
            
            // Create a confirmation notification
            $confirm_message = "Payment confirmed for Sale #$sale_id. Sale marked as successful.";
            $insert_notif = $conn->prepare("INSERT INTO admin_notifications (sale_id, message, created_at) VALUES (?, ?, NOW())");
            $insert_notif->bind_param("is", $sale_id, $confirm_message);
            $insert_notif->execute();
            $insert_notif->close();
            
            $_SESSION['success_message'] = "Payment confirmed successfully! Sale #$sale_id is now completed.";
            
            // Redirect to receipt with return parameter
            header("Location: receipt.php?sale_id=$sale_id&from=admin");
            exit;
        } else {
            $_SESSION['error_message'] = "Error confirming payment: " . $conn->error;
        }
        $stmt->close();
        
        header("Location: admin-notifications.php");
        exit;
        
    } elseif (isset($_POST['reject_payment'])) {
        $sale_id = intval($_POST['sale_id']);
        $rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : 'No reason provided';
        
        // Check which columns exist in sales table
        $columns_check = $conn->query("SHOW COLUMNS FROM sales");
        $existing_columns = [];
        while ($col = $columns_check->fetch_assoc()) {
            $existing_columns[] = $col['Field'];
        }
        
        // Build UPDATE query based on existing columns
        $update_parts = ["payment_status = 'rejected'"];
        
        if (in_array('status', $existing_columns)) {
            $update_parts[] = "status = 'cancelled'";
        }
        if (in_array('payment_confirmed_at', $existing_columns)) {
            $update_parts[] = "payment_confirmed_at = NOW()";
        }
        
        $update_query = "UPDATE sales SET " . implode(', ', $update_parts) . " WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        
        if (!$stmt) {
            $_SESSION['error_message'] = "Database error: " . $conn->error;
            header("Location: admin-notifications.php");
            exit;
        }
        
        $stmt->bind_param("i", $sale_id);
        
        if ($stmt->execute()) {
            // Mark notification as read
            $notif_stmt = $conn->prepare("UPDATE admin_notifications SET is_read = 1 WHERE sale_id = ?");
            $notif_stmt->bind_param("i", $sale_id);
            $notif_stmt->execute();
            $notif_stmt->close();
            
            // Create a rejection notification
            $reject_message = "Payment rejected for Sale #$sale_id. Reason: $rejection_reason";
            $insert_notif = $conn->prepare("INSERT INTO admin_notifications (sale_id, message, created_at) VALUES (?, ?, NOW())");
            $insert_notif->bind_param("is", $sale_id, $reject_message);
            $insert_notif->execute();
            $insert_notif->close();
            
            $_SESSION['success_message'] = "Payment rejected. Sale #$sale_id has been cancelled.";
        } else {
            $_SESSION['error_message'] = "Error rejecting payment: " . $conn->error;
        }
        $stmt->close();
        
        header("Location: admin-notifications.php");
        exit;
    }
}

// Fetch pending notifications
$notifications_query = "SELECT n.*, s.total, s.payment_method, s.gcash_reference, s.gcash_sender_name, s.gcash_sender_number, s.payment_submitted_at 
                       FROM admin_notifications n 
                       JOIN sales s ON n.sale_id = s.id 
                       WHERE s.payment_status = 'pending'
                       ORDER BY n.created_at DESC";
$notifications_result = $conn->query($notifications_query);

// Handle query error
if (!$notifications_result) {
    echo "<div style='padding: 20px; background: #f8d7da; color: #721c24; margin: 20px;'>";
    echo "<h3>Database Error:</h3>";
    echo "<p><strong>Error:</strong> " . $conn->error . "</p>";
    echo "</div>";
    exit;
}

// Fetch recent notifications (all)
$all_notifications_query = "SELECT n.*, s.total, s.payment_status 
                            FROM admin_notifications n 
                            JOIN sales s ON n.sale_id = s.id 
                            ORDER BY n.created_at DESC 
                            LIMIT 50";
$all_notifications_result = $conn->query($all_notifications_query);

if (!$all_notifications_result) {
    $all_notifications_result = false;
}

// Get counts safely
$pending_count = $notifications_result ? $notifications_result->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin - GCash Payments - Pastelaria Portuguesa</title>
    <link rel="stylesheet" href="css/indeex.css">
    <style>
        .notifications-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .notification-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 5px solid #ffc107;
        }
        
        .notification-card.confirmed {
            border-left-color: #28a745;
            opacity: 0.8;
        }
        
        .notification-card.rejected {
            border-left-color: #dc3545;
            opacity: 0.8;
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .notification-title {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
        }
        
        .notification-time {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .notification-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-confirm {
            flex: 1;
            padding: 12px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
        }
        
        .btn-confirm:hover {
            background: #218838;
        }
        
        .btn-reject {
            flex: 1;
            padding: 12px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
        }
        
        .btn-reject:hover {
            background: #c82333;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-successful {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .tab-btn {
            padding: 12px 24px;
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }
        
        .rejection-form {
            margin-top: 10px;
            display: none;
        }
        
        .rejection-form.active {
            display: block;
        }
        
        .rejection-form textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 6px;
            margin-bottom: 10px;
            resize: vertical;
            min-height: 60px;
            box-sizing: border-box;
        }
        
        .rejection-form .btn-group {
            display: flex;
            gap: 10px;
        }
        
        .btn-cancel-reject {
            flex: 1;
            padding: 10px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .sale-status-info {
            background: #e7f3ff;
            padding: 10px 15px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 0.95em;
        }
    </style>
    <meta http-equiv="refresh" content="60">
</head>
<body>
    <div class="headerbar">
        <h1 class="heads">Admin Notifications</h1>
    </div>

    <!-- SIDEBAR MENU -->
    <input type="checkbox" id="menu-toggle" hidden>
    <label class="contmenu" for="menu-toggle">
        <div class="bar1"></div>
        <div class="bar2"></div>
        <div class="bar3"></div>
    </label>

    <div class="sidebar" id="sidebar">
        <h3>Menu</h3>
        <ul>
            <li><a href="index.php">Sales Transactions</a></li>
            <li><a href="add.php">Add Products</a></li>
            <li><a href="product.php">Products</a></li>
            <li><a href="inventory.php">Inventory</a></li>
            <li><a href="sales-history.php">Sales History</a></li>
            <li><a href="admin-notifications.php">Admin Notifications</a></li>
        </ul>
    </div>

    <div class="notifications-container">
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="error-message">
                ✗ <?= htmlspecialchars($_SESSION['error_message']) ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-message">
                ✓ <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('pending')">
                🔔 Pending Payments (<?= $pending_count ?>)
            </button>
            <button class="tab-btn" onclick="showTab('all')">📋 All Notifications</button>
        </div>

        <!-- Pending Payments Tab -->
        <div id="pending-tab">
            <h2>🔔 Pending GCash Payments</h2>
            
            <?php if ($pending_count > 0): ?>
                <?php while ($notif = $notifications_result->fetch_assoc()): ?>
                    <div class="notification-card">
                        <div class="notification-header">
                            <div>
                                <div class="notification-title">
                                    💳 Sale #<?= $notif['sale_id'] ?> - GCash Payment
                                </div>
                                <span class="status-badge status-pending">Pending Verification</span>
                            </div>
                            <div class="notification-time">
                                <?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?>
                            </div>
                        </div>
                        
                        <div class="notification-details">
                            <div class="detail-item">
                                <strong>Amount:</strong>
                                <span style="color: #28a745; font-size: 1.2em; font-weight: bold;">₱<?= number_format($notif['total'], 2) ?></span>
                            </div>
                            <?php if (!empty($notif['gcash_reference'])): ?>
                            <div class="detail-item">
                                <strong>Reference Number:</strong>
                                <span style="font-family: monospace; font-weight: bold;"><?= htmlspecialchars($notif['gcash_reference']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($notif['gcash_sender_name'])): ?>
                            <div class="detail-item">
                                <strong>Sender Name:</strong>
                                <span><?= htmlspecialchars($notif['gcash_sender_name']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($notif['gcash_sender_number'])): ?>
                            <div class="detail-item">
                                <strong>Sender Number:</strong>
                                <span><?= htmlspecialchars($notif['gcash_sender_number']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($notif['payment_submitted_at'])): ?>
                            <div class="detail-item">
                                <strong>Submitted At:</strong>
                                <span><?= date('M d, Y h:i A', strtotime($notif['payment_submitted_at'])) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="sale-status-info">
                            ℹ️ <strong>Note:</strong> Confirming this payment will mark Sale #<?= $notif['sale_id'] ?> as <strong>SUCCESSFUL</strong> and complete the transaction.
                        </div>
                        
                        <div class="action-buttons">
                            <form method="POST" style="flex: 1;">
                                <input type="hidden" name="sale_id" value="<?= $notif['sale_id'] ?>">
                                <button type="submit" name="confirm_payment" class="btn-confirm">
                                    ✓ Confirm Payment & Complete Sale
                                </button>
                            </form>
                            <button type="button" class="btn-reject" onclick="toggleRejectForm(<?= $notif['sale_id'] ?>)">
                                ✗ Reject Payment
                            </button>
                        </div>
                        
                        <!-- Rejection Form (Hidden by default) -->
                        <div id="reject-form-<?= $notif['sale_id'] ?>" class="rejection-form">
                            <form method="POST">
                                <input type="hidden" name="sale_id" value="<?= $notif['sale_id'] ?>">
                                <textarea name="rejection_reason" placeholder="Enter reason for rejection (optional)"></textarea>
                                <div class="btn-group">
                                    <button type="button" class="btn-cancel-reject" onclick="toggleRejectForm(<?= $notif['sale_id'] ?>)">
                                        Cancel
                                    </button>
                                    <button type="submit" name="reject_payment" class="btn-reject"
                                            onclick="return confirm('✗ Are you sure you want to reject this payment?\n\nThis will:\n• Mark payment as REJECTED\n• Cancel the sale\n• Customer will need to pay again')">
                                        ✗ Confirm Rejection
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">✅</div>
                    <h3>No Pending Payments</h3>
                    <p>All payments have been processed</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- All Notifications Tab -->
        <div id="all-tab" style="display: none;">
            <h2>📋 All Notifications</h2>
            
            <?php if ($all_notifications_result && $all_notifications_result->num_rows > 0): ?>
                <?php while ($notif = $all_notifications_result->fetch_assoc()): ?>
                    <div class="notification-card <?= $notif['payment_status'] ?>">
                        <div class="notification-header">
                            <div>
                                <div class="notification-title">
                                    📝 Sale #<?= $notif['sale_id'] ?>
                                </div>
                                <span class="status-badge status-<?= $notif['payment_status'] ?>">
                                    Payment: <?= ucfirst($notif['payment_status']) ?>
                                </span>
                            </div>
                            <div class="notification-time">
                                <?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?>
                            </div>
                        </div>
                        <p><?= htmlspecialchars($notif['message']) ?></p>
                        <p style="font-size: 0.9em; color: #666; margin-top: 5px;">
                            Amount: <strong>₱<?= number_format($notif['total'], 2) ?></strong>
                        </p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>No Notifications</h3>
                    <p>No notifications found</p>
                </div>
            <?php endif; ?>
        </div>
        
        <p style="text-align: center; color: #6c757d; margin-top: 30px;">
            ⟳ This page auto-refreshes every 60 seconds
        </p>
    </div>

    <script>
        function showTab(tab) {
            const tabs = document.querySelectorAll('.tab-btn');
            tabs.forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');
            
            document.getElementById('pending-tab').style.display = tab === 'pending' ? 'block' : 'none';
            document.getElementById('all-tab').style.display = tab === 'all' ? 'block' : 'none';
        }
        
        function toggleRejectForm(saleId) {
            const form = document.getElementById('reject-form-' + saleId);
            form.classList.toggle('active');
        }
    </script>
</body>
</html>