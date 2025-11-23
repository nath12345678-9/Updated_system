<?php
session_start();
include 'db/db_connect.php';

// Get filter parameters
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$payment_filter = $_GET['payment_method'] ?? '';
$status_filter = $_GET['payment_status'] ?? '';

// Build the WHERE clause based on filters
$where_conditions = [];
$params = [];
$types = '';

if (!empty($start_date)) {
    $where_conditions[] = "DATE(sale_date) >= ?";
    $params[] = $start_date;
    $types .= 's';
}

if (!empty($end_date)) {
    $where_conditions[] = "DATE(sale_date) <= ?";
    $params[] = $end_date;
    $types .= 's';
}

if (!empty($payment_filter)) {
    $where_conditions[] = "payment_method = ?";
    $params[] = $payment_filter;
    $types .= 's';
}

if (!empty($status_filter)) {
    $where_conditions[] = "payment_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get sales data
$query = "SELECT * FROM sales $where_clause ORDER BY sale_date DESC, id DESC";
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();

// Calculate totals
$total_sales = 0;
$total_cash = 0;
$total_gcash = 0;
$total_card = 0;

$summary_query = "SELECT 
    COUNT(*) as transaction_count,
    SUM(total) as total_amount,
    SUM(CASE WHEN payment_method = 'Cash' THEN total ELSE 0 END) as cash_total,
    SUM(CASE WHEN payment_method = 'GCash' THEN total ELSE 0 END) as gcash_total,
    SUM(CASE WHEN payment_method IN ('Credit Card', 'Debit Card') THEN total ELSE 0 END) as card_total
    FROM sales $where_clause";

$summary_stmt = $conn->prepare($summary_query);
if (!empty($params)) {
    $summary_stmt->bind_param($types, ...$params);
}
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();

// Inventory alerts
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
    <title>Sales History - Pastelaria Portuguesa</title>
    <link rel="stylesheet" href="css/sales-history.css">
    <style>
        .filters-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
            font-size: 0.9em;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
            transition: border-color 0.3s;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #007bff;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #545b62;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            border-radius: 10px;
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .summary-card.cash {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }

        .summary-card.gcash {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        }

        .summary-card.card {
            background: linear-gradient(135deg, #fd7e14 0%, #dc3545 100%);
        }

        .summary-card h3 {
            margin: 0 0 10px 0;
            font-size: 0.9em;
            opacity: 0.9;
            font-weight: normal;
        }

        .summary-card .amount {
            font-size: 1.8em;
            font-weight: bold;
            margin: 0;
        }

        .summary-card .count {
            font-size: 0.85em;
            opacity: 0.8;
            margin-top: 5px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: bold;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .no-sales {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
            font-size: 1.2em;
        }

        .actions {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .actions a {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9em;
            transition: all 0.3s;
        }

        .actions .view-btn {
            background: #007bff;
            color: white;
        }

        .actions .view-btn:hover {
            background: #0056b3;
        }

        .actions .print-btn {
            background: #28a745;
            color: white;
        }

        .actions .print-btn:hover {
            background: #218838;
        }

        @media print {
            .headerbar, .sidebar, .contmenu, .filters-section, .filter-actions, .actions {
                display: none !important;
            }
            
            .history-wrap {
                padding: 20px;
                max-width: 100%;
            }

            table {
                font-size: 12px;
            }

            .summary-cards {
                display: none;
            }
        }

        .print-header {
            display: none;
            text-align: center;
            margin-bottom: 30px;
        }

        @media print {
            .print-header {
                display: block;
            }
        }

        .quick-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .quick-filter-btn {
            padding: 8px 16px;
            border: 2px solid #007bff;
            background: white;
            color: #007bff;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s;
        }

        .quick-filter-btn:hover {
            background: #007bff;
            color: white;
        }

        .quick-filter-btn.active {
            background: #007bff;
            color: white;
        }
    </style>
</head>

<body>

    <div class="headerbar">
        <h1 class="heads">Sales History</h1>
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
            <li><a href="admin-notification.php">Admin Notifications</a></li>
        </ul>
        <div style="position: absolute; bottom: 20px; left: 20px; right: 20px;">
            <form action="login.php" method="POST" onsubmit="return confirm('Are you sure you want to logout?');">
                <button type="submit" class="logout" style="width:100%; padding:10px; background:#f44336; color:#fff; border:none; border-radius:8px; cursor:pointer;">
                    Logout
                </button>
            </form>
        </div>
    </div>

    <div class="history-wrap">
        <!-- Print Header (only visible when printing) -->
        <div class="print-header">
            <h1>Pastelaria Portuguesa</h1>
            <h2>Sales History Report</h2>
            <?php if ($start_date || $end_date): ?>
                <p>Period: 
                    <?= $start_date ? date('M d, Y', strtotime($start_date)) : 'Start' ?> - 
                    <?= $end_date ? date('M d, Y', strtotime($end_date)) : 'Present' ?>
                </p>
            <?php endif; ?>
            <p>Generated: <?= date('F d, Y h:i A') ?></p>
        </div>

        <h2>Sales History</h2>

        <!-- Filters Section -->
        <div class="filters-section">
            <h3 style="margin-top: 0;">📊 Filter & Search</h3>
            
            <!-- Quick Filters -->
           

            <form method="GET" action="sales-history.php">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                    </div>

                    <div class="filter-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                    </div>

                    <div class="filter-group">
                        <label>Payment Method</label>
                        <select name="payment_method">
                            <option value="">All Methods</option>
                            <option value="Cash" <?= $payment_filter === 'Cash' ? 'selected' : '' ?>>Cash</option>
                            <option value="GCash" <?= $payment_filter === 'GCash' ? 'selected' : '' ?>>GCash</option>
                            <option value="Credit Card" <?= $payment_filter === 'Credit Card' ? 'selected' : '' ?>>Credit Card</option>
                            <option value="Debit Card" <?= $payment_filter === 'Debit Card' ? 'selected' : '' ?>>Debit Card</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Payment Status</label>
                        <select name="payment_status">
                            <option value="">All Status</option>
                            <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">🔍 Apply Filters</button>
                    <a href="sales-history.php" class="btn btn-secondary">🔄 Clear Filters</a>
                    <button type="button" onclick="window.print()" class="btn btn-success">🖨️ Print Report</button>
                </div>
            </form>
        </div>

        <!-- Summary Cards -->
        <?php if ($summary['transaction_count'] > 0): ?>
            <div class="summary-cards">
                <div class="summary-card">
                    <h3>Total Sales</h3>
                    <p class="amount">₱<?= number_format($summary['total_amount'], 2) ?></p>
                    <p class="count"><?= $summary['transaction_count'] ?> transactions</p>
                </div>

                <div class="summary-card cash">
                    <h3>Cash Payments</h3>
                    <p class="amount">₱<?= number_format($summary['cash_total'], 2) ?></p>
                </div>

                <div class="summary-card gcash">
                    <h3>GCash Payments</h3>
                    <p class="amount">₱<?= number_format($summary['gcash_total'], 2) ?></p>
                </div>

               
            </div>
        <?php endif; ?>

        <!-- Sales Table -->
        <?php if ($res && $res->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date & Time</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $res->data_seek(0); // Reset pointer
                    while ($row = $res->fetch_assoc()):
                        $id = (int)$row['id'];
                        $datetime = isset($row['sale_date']) ? date('M d, Y h:i A', strtotime($row['sale_date'])) : '-';
                        $payment = isset($row['payment_method']) ? $row['payment_method'] : 'Cash';
                        $status = isset($row['payment_status']) ? $row['payment_status'] : 'completed';
                        $total = isset($row['total']) ? number_format((float)$row['total'], 2) : '-';
                        
                        $status_class = 'status-' . $status;
                        $status_text = ucfirst($status);
                    ?>
                        <tr>
                            <td><?= $id ?></td>
                            <td><?= htmlspecialchars($datetime) ?></td>
                            <td><?= htmlspecialchars($payment) ?></td>
                            <td><span class="status-badge <?= $status_class ?>"><?= $status_text ?></span></td>
                            <td><strong>₱<?= $total ?></strong></td>
                            <td class="actions">
                                <a href="receipt.php?sale_id=<?= $id ?>" target="_blank" class="view-btn">👁️ View</a>
                                <a href="receipt.php?sale_id=<?= $id ?>&print=1" target="_blank" class="print-btn">🖨️ Print</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-sales">
                <p style="font-size: 3em; margin: 0;">📭</p>
                <p>No sales found for the selected filters.</p>
                <p style="font-size: 0.9em;">Try adjusting your search criteria.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function setQuickFilter(period) {
            const today = new Date();
            let startDate = new Date();
            let endDate = new Date();

            switch(period) {
                case 'today':
                    startDate = today;
                    endDate = today;
                    break;
                case 'yesterday':
                    startDate.setDate(today.getDate() - 1);
                    endDate.setDate(today.getDate() - 1);
                    break;
                case 'this_week':
                    const firstDayOfWeek = today.getDate() - today.getDay();
                    startDate.setDate(firstDayOfWeek);
                    endDate = today;
                    break;
                case 'this_month':
                    startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                    endDate = today;
                    break;
                case 'last_month':
                    startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    endDate = new Date(today.getFullYear(), today.getMonth(), 0);
                    break;
            }

            // Format dates as YYYY-MM-DD
            const formatDate = (date) => {
                return date.toISOString().split('T')[0];
            };

            // Set form values and submit
            document.querySelector('input[name="start_date"]').value = formatDate(startDate);
            document.querySelector('input[name="end_date"]').value = formatDate(endDate);
            document.querySelector('form').submit();
        }
    </script>
</body>

</html>