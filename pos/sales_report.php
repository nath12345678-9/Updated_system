<?php
session_start();
include 'db/db_connect.php';

// Get filters from URL parameters
$search = $_GET['search'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$sort = $_GET['sort'] ?? 'date_desc';

// Build query
$where = [];
if (!empty($search)) {
    $search_safe = $conn->real_escape_string($search);
    $where[] = "(p.name LIKE '%$search_safe%' OR s.id LIKE '%$search_safe%' OR s.payment_method LIKE '%$search_safe%')";
}
if (!empty($start_date)) {
    $start_safe = $conn->real_escape_string($start_date);
    $where[] = "DATE(s.sale_date) >= '$start_safe'";
}
if (!empty($end_date)) {
    $end_safe = $conn->real_escape_string($end_date);
    $where[] = "DATE(s.sale_date) <= '$end_safe'";
}
$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Sorting
$order_by = match ($sort) {
    'date_asc' => 's.sale_date ASC',
    'date_desc' => 's.sale_date DESC',
    'total_asc' => 's.total ASC',
    'total_desc' => 's.total DESC',
    default => 's.sale_date DESC'
};

$sql = "SELECT s.*, SUM(si.price) as total_amount, COUNT(si.id) as items_count
        FROM sales s
        LEFT JOIN sale_items si ON s.id = si.sale_id
        LEFT JOIN products p ON si.product_id = p.id
        $where_clause
        GROUP BY s.id
        ORDER BY $order_by";
$sales = $conn->query($sql);

// Calculate statistics
$total_sales = 0;
$total_revenue = 0;
$total_items = 0;
if ($sales) {
    foreach ($sales as $sale) {
        $total_sales++;
        $total_revenue += $sale['total_amount'];
        $total_items += $sale['items_count'];
    }
}

$low_stock_result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE quantity <= reorder_level");
$low_stock_count = $low_stock_result ? $low_stock_result->fetch_assoc()['count'] : 0;
$expired_result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE expiration_date < CURDATE()");
$expired_count = $expired_result ? $expired_result->fetch_assoc()['count'] : 0;
$expiring_result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
$expiring_count = $expiring_result ? $expiring_result->fetch_assoc()['count'] : 0;
$show_alerts = ($low_stock_count > 0 || $expired_count > 0 || $expiring_count > 0);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sales Report - Pastelaria Portuguesa</title>
    <link rel="stylesheet" href="css/sales-report.css">
</head>

<body>

    <div class="headerbar">
        <h1 class="heads">Pastelaria Portuguesa - Sales Report</h1>
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
            <li>
                <a href="inventory.php" class="inventory-badge">
                    Inventory
                    <?php if ($show_alerts): ?>
                        <span class="badge-dot"><?= $low_stock_count + $expired_count + $expiring_count ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li><a href="sales_report.php">Sales Report</a></li>
            <li><a href="sales-history.php">Sales History</a></li>
        </ul>
        <div style="position: absolute; bottom: 80px; left: 20px; right: 20px;">
            <form action="login.php" method="POST" onsubmit="return confirm('Are you sure you want to logout?');">
                <button type="submit" class="logout" style="width:100%; padding:10px; background:#f44336; color:#fff; border:none; border-radius:8px; cursor:pointer;">
                    Logout
                </button>
            </form>
        </div>
    </div>
    <!-- SIDEBAR MENU -->

    <div class="report-container">
        <div class="report-header">
            <h1>📈 Sales Report</h1>
            <p>Pastelaria Portuguesa</p>
            <p>Generated on: <?= date('F d, Y g:i A') ?></p>
        </div>
        <div class="actions">
            <button onclick="window.print()" class="btn">🖨️ Print Report</button>
            <a href="sales-history.php" class="btn btn-secondary">← Back to Sales History</a>
        </div>
        <form method="GET" style="margin-bottom: 20px;">
            <input type="text" name="search" placeholder="Search sale ID, product, payment..." value="<?= htmlspecialchars($search) ?>">
            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
            <select name="sort">
                <option value="date_desc" <?= $sort == 'date_desc' ? 'selected' : '' ?>>Newest First</option>
                <option value="date_asc" <?= $sort == 'date_asc' ? 'selected' : '' ?>>Oldest First</option>
                <option value="total_desc" <?= $sort == 'total_desc' ? 'selected' : '' ?>>Total High-Low</option>
                <option value="total_asc" <?= $sort == 'total_asc' ? 'selected' : '' ?>>Total Low-High</option>
            </select>
            <button type="submit" class="btn">Apply Filters</button>
            <a href="sales_report.php" class="btn btn-secondary">Clear</a>
        </form>
        <div class="statistics">
            <div class="stat-card">
                <h3>Total Sales</h3>
                <div class="value"><?= $total_sales ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="value">₱<?= number_format($total_revenue, 2) ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Items Sold</h3>
                <div class="value"><?= $total_items ?></div>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Sale ID</th>
                    <th>Date</th>
                    <th>Payment Method</th>
                    <th>Items</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($sales && $sales->num_rows > 0): ?>
                    <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td><?= (int)$sale['id'] ?></td>
                            <td><?= htmlspecialchars(
                                    date('M d, Y g:i A', strtotime($sale['sale_date']))
                                ) ?></td>
                            <td><?= htmlspecialchars($sale['payment_method'] ?? 'Cash') ?></td>
                            <td><?= (int)$sale['items_count'] ?></td>
                            <td>₱<?= number_format($sale['total_amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center;">No sales found for the selected filters.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>

</html>