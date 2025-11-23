<?php
session_start();
include 'db/db_connect.php';

// get all muna mga bago
$res = $conn->query("SELECT * FROM sales ORDER BY id DESC");

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sales History - Pastelaria Portuguesa</title>
    <link rel="stylesheet" href="css/sales-history.css">
</head>

<body>

    <div class="headerbar">
        <h1 class="heads">History</h1>
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
            <li><a href="sales-history.php">Sales History</a></li>
        </ul>
    </div>
    <!-- SIDEBAR MENU -->

    <div class="history-wrap">
        <h2>Sales History</h2>

        <?php if ($res && $res->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Payment Method</th>
                        <th>Total</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $res->fetch_assoc()):
                        $id = (int)$row['id'];
                        $date = isset($row['sale_date']) ? date('Y-m-d', strtotime($row['sale_date'])) : '-';

                        $payment = isset($row['payment_method']) ? $row['payment_method'] : (isset($row['method']) ? $row['method'] : 'Cash');

                        $total = isset($row['total']) ? number_format((float)$row['total'], 2) : '-';
                    ?>
                        <tr>
                            <td><?= $id ?></td>
                            <td><?= htmlspecialchars($date) ?></td>
                            <td><?= htmlspecialchars($payment) ?></td>
                            <td>₱<?= $total ?></td>
                            <td class="actions"><a href="receipt.php?sale_id=<?= $id ?>" target="_blank">View Receipt</a></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-sales">No sales found.</div>
        <?php endif; ?>
    </div>
</body>

</html>