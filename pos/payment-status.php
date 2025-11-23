<?php
session_start();
include 'db/db_connect.php';

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
    header("Location: index.php");
    exit;
}

$status_info = [
    'pending' => [
        'icon' => '⏳',
        'color' => '#ffc107',
        'title' => 'Payment Pending',
        'message' => 'Your payment is being verified by our admin. You will be notified once confirmed.'
    ],
    'confirmed' => [
        'icon' => '✅',
        'color' => '#28a745',
        'title' => 'Payment Confirmed',
        'message' => 'Your payment has been confirmed. Thank you for your purchase!'
    ],
    'rejected' => [
        'icon' => '❌',
        'color' => '#dc3545',
        'title' => 'Payment Rejected',
        'message' => 'Your payment could not be verified. Please contact support.'
    ]
];

$current_status = $sale['payment_status'] ?? 'pending';
$status = $status_info[$current_status];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Payment Status - Pastelaria Portuguesa</title>
    <link rel="stylesheet" href="css/indeex.css">
    <style>
        .status-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 40px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .status-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        .status-title {
            font-size: 2em;
            color: <?= $status['color'] ?>;
            margin-bottom: 15px;
        }
        
        .status-message {
            font-size: 1.1em;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .payment-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 30px 0;
            text-align: left;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .btn-home {
            display: inline-block;
            padding: 15px 40px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            margin-top: 20px;
        }
        
        .btn-home:hover {
            background: #0056b3;
        }
        
        .refresh-note {
            margin-top: 20px;
            font-size: 0.9em;
            color: #6c757d;
        }
    </style>
    <meta http-equiv="refresh" content="30">
</head>
<body>
    <div class="headerbar">
        <h1 class="heads">Payment Status</h1>
    </div>

    <div class="status-container">
        <div class="status-icon"><?= $status['icon'] ?></div>
        <h1 class="status-title"><?= $status['title'] ?></h1>
        <p class="status-message"><?= $status['message'] ?></p>
        
        <div class="payment-info">
            <div class="info-row">
                <strong>Sale ID:</strong>
                <span>#<?= $sale['id'] ?></span>
            </div>
            <div class="info-row">
                <strong>Total Amount:</strong>
                <span>₱<?= number_format($sale['total'], 2) ?></span>
            </div>
            <div class="info-row">
                <strong>Payment Method:</strong>
                <span><?= htmlspecialchars($sale['payment_method']) ?></span>
            </div>
            <?php if ($sale['gcash_reference']): ?>
            <div class="info-row">
                <strong>Reference Number:</strong>
                <span><?= htmlspecialchars($sale['gcash_reference']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <strong>Status:</strong>
                <span style="color: <?= $status['color'] ?>; font-weight: bold;">
                    <?= ucfirst($current_status) ?>
                </span>
            </div>
        </div>
        
        <?php if ($current_status === 'confirmed'): ?>
            <a href="receipt.php?sale_id=<?= $sale_id ?>" class="btn-home">View Receipt</a>
        <?php endif; ?>
        
        <a href="index.php" class="btn-home">Return to Home</a>
        
        <?php if ($current_status === 'pending'): ?>
            <p class="refresh-note">⟳ This page will auto-refresh every 30 seconds</p>
        <?php endif; ?>
    </div>
</body>
</html>