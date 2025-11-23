<?php
session_start();
include 'db/db_connect.php';

// Check if sale_id is provided
if (!isset($_GET['sale_id'])) {
    header("Location: index.php");
    exit;
}

$sale_id = intval($_GET['sale_id']);

// Fetch sale details
$stmt = $conn->prepare("SELECT * FROM sales WHERE id = ?");
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$result = $stmt->get_result();
$sale = $result->fetch_assoc();
$stmt->close();

if (!$sale) {
    $_SESSION['error_message'] = "Sale not found";
    header("Location: index.php");
    exit;
}

// Fetch sale items
$stmt = $conn->prepare("SELECT si.*, p.name FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?");
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$items_result = $stmt->get_result();
$sale_items = [];
while ($row = $items_result->fetch_assoc()) {
    $sale_items[] = $row;
}
$stmt->close();

// Handle payment confirmation with simplified form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $reference_number = trim($_POST['reference_number']);
    
    // Optional fields - can be left empty if user just wants to mark as paid
    $sender_name = !empty($_POST['sender_name']) ? trim($_POST['sender_name']) : 'Not Provided';
    $sender_number = !empty($_POST['sender_number']) ? trim($_POST['sender_number']) : 'Not Provided';
    
    if (!empty($reference_number)) {
        // Update sale with payment details
        $stmt = $conn->prepare("UPDATE sales SET payment_status = 'pending', gcash_reference = ?, gcash_sender_name = ?, gcash_sender_number = ?, payment_submitted_at = NOW() WHERE id = ?");
        $stmt->bind_param("sssi", $reference_number, $sender_name, $sender_number, $sale_id);
        
        if ($stmt->execute()) {
            // Insert admin notification
            $notification_message = "New GCash payment received for Sale #$sale_id - Amount: ₱" . number_format($sale['total'], 2) . " - Sender: $sender_name ($sender_number) - Ref: $reference_number";
            $notification_stmt = $conn->prepare("INSERT INTO admin_notifications (sale_id, message, type, created_at) VALUES (?, ?, 'gcash_payment', NOW())");
            $notification_stmt->bind_param("is", $sale_id, $notification_message);
            $notification_stmt->execute();
            $notification_stmt->close();
            
            $_SESSION['success_message'] = "Payment details submitted successfully! Please wait for admin confirmation.";
            header("Location: payment-status.php?sale_id=" . $sale_id);
            exit;
        }
        $stmt->close();
    } else {
        $error_message = "Please enter the GCash reference number.";
    }
}

// Quick confirm - mark as paid immediately (optional feature)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_confirm'])) {
    $stmt = $conn->prepare("UPDATE sales SET payment_status = 'pending', gcash_reference = 'QR-AUTO', payment_submitted_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $sale_id);
    
    if ($stmt->execute()) {
        $notification_message = "Payment confirmed via QR for Sale #$sale_id - Amount: ₱" . number_format($sale['total'], 2);
        $notification_stmt = $conn->prepare("INSERT INTO admin_notifications (sale_id, message, type, created_at) VALUES (?, ?, 'gcash_payment', NOW())");
        $notification_stmt->bind_param("is", $sale_id, $notification_message);
        $notification_stmt->execute();
        $notification_stmt->close();
        
        $_SESSION['success_message'] = "Payment confirmed! Waiting for admin verification.";
        header("Location: payment-status.php?sale_id=" . $sale_id);
        exit;
    }
    $stmt->close();
}

// Admin GCash details
$admin_gcash_name = " Pastelaria Portuguesa";
$admin_gcash_number = "09937766195";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>GCash Payment - Pastelaria Portuguesa</title>
    <link rel="stylesheet" href="css/indeex.css">
    <style>
        .payment-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .payment-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #0066ff;
        }
        
        .payment-header h1 {
            color: #0066ff;
            margin-bottom: 10px;
        }
        
        .gcash-logo {
            font-size: 48px;
            color: #0066ff;
        }
        
        .payment-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .detail-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.2em;
            color: #0066ff;
        }
        
        .qr-section {
            text-align: center;
            background: white;
            padding: 30px;
            border: 2px dashed #0066ff;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .qr-code {
            width: 250px;
            height: 250px;
            margin: 20px auto;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #ddd;
            border-radius: 10px;
        }
        
        .gcash-info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .gcash-info p {
            margin: 5px 0;
            font-size: 1.1em;
        }
        
        .gcash-number {
            font-weight: bold;
            font-size: 1.3em;
            color: #0066ff;
            letter-spacing: 1px;
        }
        
        .payment-form {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .quick-confirm-section {
            background: #d4edda;
            border: 2px solid #28a745;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .quick-confirm-section h3 {
            color: #155724;
            margin-top: 0;
        }
        
        .quick-confirm-section p {
            color: #155724;
            margin: 10px 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #0066ff;
        }
        
        .form-group small {
            color: #666;
            font-size: 0.9em;
        }
        
        .instructions {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 4px;
        }
        
        .instructions h3 {
            margin-top: 0;
            color: #856404;
        }
        
        .instructions ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .instructions li {
            margin: 8px 0;
        }
        
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: #0066ff;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-submit:hover {
            background: #0052cc;
        }
        
        .btn-quick-confirm {
            width: 100%;
            padding: 15px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-quick-confirm:hover {
            background: #218838;
        }
        
        .btn-cancel {
            width: 100%;
            padding: 15px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .items-list {
            margin-top: 15px;
        }
        
        .item-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }
        
        .divider span {
            background: white;
            padding: 0 10px;
            color: #666;
            position: relative;
            z-index: 1;
        }
        
        .divider:before {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            top: 50%;
            height: 1px;
            background: #ddd;
        }
    </style>
</head>
<body>
    <div class="headerbar">
        <h1 class="heads">GCash Payment</h1>
    </div>

    <div class="payment-container">
        <div class="payment-header">
            <div class="gcash-logo">💳</div>
            <h1>GCash Payment</h1>
            <p>Sale #<?= $sale_id ?></p>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="instructions">
            <h3>📋 Quick Payment Steps</h3>
            <ol>
                <li>Open your GCash app</li>
                <li>Scan the QR code OR send to: <strong><?= htmlspecialchars($admin_gcash_number) ?></strong></li>
                <li>Amount: <strong>₱<?= number_format($sale['total'], 2) ?></strong></li>
                <li>Complete the payment</li>
                <li>Click "I've Paid" button below</li>
            </ol>
        </div>

        <div class="payment-details">
            <h3>Order Summary</h3>
            <div class="items-list">
                <?php foreach ($sale_items as $item): ?>
                    <div class="item-row">
                        <span><?= htmlspecialchars($item['name']) ?> (x<?= $item['quantity'] ?>)</span>
                        <span>₱<?= number_format($item['subtotal'], 2) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="detail-row">
                <span>Total Amount:</span>
                <span>₱<?= number_format($sale['total'], 2) ?></span>
            </div>
        </div>

        <div class="qr-section">
            <h3>Scan QR Code to Pay</h3>
            <div class="qr-code">
                <!-- Choose ONE of these options: -->
                
                <!-- OPTION 1: Static QR Code Image (Simplest) -->
                <!-- Save your GCash QR as 'images/gcash-qr.png' -->
                <img src="/img/gcash-qr.jpg" alt="GCash QR Code" style="width: 100%; height: 100%; object-fit: contain; padding: 10px;">
                
                <!-- OPTION 2: Dynamic QR Code (if you set up generate_qr.php) -->
                <!-- <img src="generate_qr.php?number=<?= urlencode($admin_gcash_number) ?>&amount=<?= $sale['total'] ?>" 
                     alt="GCash QR Code" style="width: 100%; height: 100%; object-fit: contain; padding: 10px;"> -->
                
                <!-- OPTION 3: Google Charts QR (No library needed, but requires internet) -->
                <!-- <img src="https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=<?= urlencode($admin_gcash_number) ?>&choe=UTF-8" 
                     alt="GCash QR Code" style="width: 100%; height: 100%; object-fit: contain;"> -->
            </div>
            <div class="gcash-info">
                <p><strong>Send to:</strong></p>
                <p><?= htmlspecialchars($admin_gcash_name) ?></p>
                <p class="gcash-number"><?= htmlspecialchars($admin_gcash_number) ?></p>
                <p style="font-size: 0.9em; color: #666; margin-top: 10px;">Amount: <strong>₱<?= number_format($sale['total'], 2) ?></strong></p>
            </div>
        </div>

        <!-- Quick Confirm Option -->
        <div class="quick-confirm-section">
            <h3>✓ Already Paid?</h3>
            <p>If you've completed the GCash payment, click below to confirm.</p>
            <p style="font-size: 0.9em;">Admin will verify your payment from their GCash transaction history.</p>
            <form method="POST" onsubmit="return confirm('Have you completed the GCash payment?');">
                <button type="submit" name="quick_confirm" class="btn-quick-confirm">
                    ✓ I've Paid via GCash
                </button>
            </form>
        </div>

        <div class="divider">
            <span>OR provide details manually</span>
        </div>

        <!-- Manual Entry Form (Optional) -->
        <div class="payment-form">
            <h3>Submit Payment Details (Optional)</h3>
            <p style="color: #666; font-size: 0.95em; margin-bottom: 20px;">
                Providing your reference number helps speed up verification.
            </p>
            <form method="POST">
                <div class="form-group">
                    <label>GCash Reference Number *</label>
                    <input type="text" name="reference_number" required 
                           placeholder="Enter 13-digit reference number">
                    <small>Found in your GCash transaction receipt</small>
                </div>

                <div class="form-group">
                    <label>Your Name (Optional)</label>
                    <input type="text" name="sender_name" 
                           placeholder="Name as shown in GCash">
                </div>

                <div class="form-group">
                    <label>Your GCash Number (Optional)</label>
                    <input type="text" name="sender_number" 
                           placeholder="09XXXXXXXXX" pattern="[0-9]{11}">
                </div>

                <button type="submit" name="confirm_payment" class="btn-submit">
                    Submit Payment Details
                </button>
            </form>
        </div>

        <a href="index.php" class="btn-cancel">Cancel Payment</a>
    </div>
</body>
</html>