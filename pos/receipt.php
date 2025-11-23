<?php
session_start();

$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : (int)($_SESSION['last_sale_id'] ?? 0);

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

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Receipt #<?= htmlspecialchars($sale_id_safe) ?></title>
    <link rel="stylesheet" href="css/common.css">
</head>

<body>
    <div class="receipt-wrap">
        <div class="receipt-header">
            <h2>Pastelaria Portuguesa</h2>
            <div>Receipt #: <?= htmlspecialchars($sale_id_safe) ?></div>
            <div>Time: <?= htmlspecialchars($created_at) ?></div>
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

        <div class="receipt-btns">
            <!-- print button -->
            <button class="print-btn" onclick="window.print();">Print Receipt</button>

            <!-- back button -->
            <a href="index.php">
                <button class="print-btn back-btn">Back</button>
            </a>
        </div>
    </div>
</body>

</html>