<?php
session_start();

$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : (int)($_SESSION['last_sale_id'] ?? 0);
$from_admin = isset($_GET['from']) && $_GET['from'] === 'admin';

if (!$sale_id) {
    echo "<p>No sale specified. Go back and generate a receipt after checkout.</p>";
    exit;
}

include 'db/db_connect.php';

$sale_id_safe = (int)$sale_id;
$saleRes = $conn->query("SELECT * FROM sales WHERE id = $sale_id_safe LIMIT 1");
if (!$saleRes || $saleRes->num_rows === 0) {
    echo "<p>Sale not found.</p>";
    exit;
}
$sale = $saleRes->fetch_assoc();

$itemsSql = "SELECT p.name, si.price, COUNT(*) AS qty
    FROM sale_items si
    LEFT JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = $sale_id_safe
    GROUP BY si.product_id, si.price
    ORDER BY p.name";
$itemsRes = $conn->query($itemsSql);

$created_at = $sale['created_at'] ?? date('Y-m-d H:i:s');

// Get customer name from GCash details or default
$customer_name = !empty($sale['gcash_sender_name']) ? $sale['gcash_sender_name'] : 'Walk-in Customer';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Receipt #<?= htmlspecialchars($sale_id_safe) ?></title>
    <link rel="stylesheet" href="css/common.css">
    <style>
        /* Additional styles for new features */
        .payment-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .status-confirmed {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-paid {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .payment-info {
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #007bff;
        }
        
        .payment-info-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 14px;
        }
        
        .payment-info-row strong {
            color: #495057;
        }
        
        @media print {
            .receipt-btns {
                display: none;
            }
            
            .payment-status {
                border: 1px solid #000;
            }
        }
    </style>
</head>

<body>
    <div class="receipt-wrap">
        <div class="receipt-header">
            <h2>Pastelaria Portuguesa</h2>
            <div>Receipt #: <?= htmlspecialchars($sale_id_safe) ?>
                <?php if (!empty($sale['payment_status'])): ?>
                    <span class="payment-status status-<?= htmlspecialchars($sale['payment_status']) ?>">
                        <?= strtoupper(htmlspecialchars($sale['payment_status'])) ?>
                    </span>
                <?php endif; ?>
            </div>
            <div>Time: <?= htmlspecialchars($created_at) ?></div>
            <div>Customer: <?= htmlspecialchars($customer_name) ?></div>
        </div>

        <div class="receipt-items">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $calc_sub = 0.0;
                    if ($itemsRes && $itemsRes->num_rows > 0) {
                        while ($row = $itemsRes->fetch_assoc()) {
                            $name = $row['name'] ?? 'N/A';
                            $qty = (int)$row['qty'];
                            $price = (float)$row['price'];
                            $line = $price * $qty;
                            $calc_sub += $line;
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($name) . '</td>';
                            echo '<td>' . $qty . '</td>';
                            echo '<td>₱' . number_format($price, 2) . '</td>';
                            echo '<td>₱' . number_format($line, 2) . '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="4">No items found for this sale.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="receipt-summary">
            <div class="receipt-sum2"><strong>Total:</strong><span>₱<?= number_format($sale['total'], 2) ?></span></div>
        </div>

        <!-- Payment Information Section -->
        <?php if (!empty($sale['payment_method'])): ?>
        <div class="payment-info">
            <div class="payment-info-row">
                <strong>Payment Method:</strong>
                <span><?= strtoupper(htmlspecialchars($sale['payment_method'])) ?></span>
            </div>
            
            <?php if ($sale['payment_method'] == 'gcash'): ?>
                <?php if (!empty($sale['gcash_reference'])): ?>
                <div class="payment-info-row">
                    <strong>GCash Reference:</strong>
                    <span><?= htmlspecialchars($sale['gcash_reference']) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($sale['gcash_sender_number'])): ?>
                <div class="payment-info-row">
                    <strong>Sender Number:</strong>
                    <span><?= htmlspecialchars($sale['gcash_sender_number']) ?></span>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if (!empty($sale['payment_confirmed_at'])): ?>
            <div class="payment-info-row">
                <strong>Confirmed At:</strong>
                <span><?= date('M d, Y h:i A', strtotime($sale['payment_confirmed_at'])) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="receipt-btns">
            <!-- print button -->
            <button class="print-btn" onclick="window.print();">Print Receipt</button>

            <?php if ($from_admin): ?>
            <!-- Back to Admin Notifications button -->
            <a href="admin-notification.php">
                <button class="print-btn back-btn">Back to Admin Notifications</button>
            </a>
            <?php else: ?>
            <!-- back button -->
            <a href="index.php">
                <button class="print-btn back-btn">Back</button>
            </a>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>