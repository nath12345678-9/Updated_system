<?php
session_start();
include 'db/db_connect.php';

// Get filters from URL parameters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$stock_filter = $_GET['stock'] ?? '';
$expiry_filter = $_GET['expiry'] ?? '';
$expiry_days = isset($_GET['expiry_days']) ? intval($_GET['expiry_days']) : 0;
$sort = $_GET['sort'] ?? 'id_desc';

// Build query
$where = [];
if (!empty($search)) {
    $search_safe = $conn->real_escape_string($search);
    $where[] = "(item_name LIKE '%$search_safe%' OR supplier LIKE '%$search_safe%' OR batch_number LIKE '%$search_safe%' OR category LIKE '%$search_safe%')";
}
if (!empty($category_filter)) {
    $cat_safe = $conn->real_escape_string($category_filter);
    $where[] = "category = '$cat_safe'";
}
if ($stock_filter === 'low') {
    $where[] = "quantity <= reorder_level";
}
if ($stock_filter === 'out') {
    $where[] = "quantity = 0";
}
if ($expiry_filter === 'expired') {
    $where[] = "expiration_date < CURDATE()";
}
if ($expiry_filter === 'custom' && $expiry_days > 0) {
    $where[] = "expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $expiry_days DAY)";
}
if (!empty($start_date)) {
    $start_safe = $conn->real_escape_string($start_date);
    $where[] = "expiration_date >= '$start_safe'";
}
if (!empty($end_date)) {
    $end_safe = $conn->real_escape_string($end_date);
    $where[] = "expiration_date <= '$end_safe'";
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Sorting
$order_by = match($sort) {
    'name_asc' => 'item_name ASC',
    'name_desc' => 'item_name DESC',
    'quantity_asc' => 'quantity ASC',
    'quantity_desc' => 'quantity DESC',
    'expiry_asc' => 'expiration_date ASC',
    'expiry_desc' => 'expiration_date DESC',
    'id_asc' => 'id ASC',
    default => 'id DESC'
};

$sql = "SELECT * FROM inventory $where_clause ORDER BY $order_by";
$inventory = $conn->query($sql);

// Calculate statistics
$total_items = $inventory->num_rows;
$total_value = 0;
$low_stock_count = 0;
$out_of_stock_count = 0;
$expired_count = 0;
$expiring_count = 0;

$inventory->data_seek(0); // Reset pointer
while ($item = $inventory->fetch_assoc()) {
    $total_value += $item['cost_price'] * $item['quantity'];
    
    if ($item['quantity'] == 0) {
        $out_of_stock_count++;
    } elseif ($item['quantity'] <= $item['reorder_level']) {
        $low_stock_count++;
    }
    
    if (!empty($item['expiration_date'])) {
        $exp_timestamp = strtotime($item['expiration_date']);
        if ($exp_timestamp < time()) {
            $expired_count++;
        } elseif ($exp_timestamp >= time() && $exp_timestamp <= strtotime('+30 days')) {
            $expiring_count++;
        }
    }
}

$inventory->data_seek(0); // Reset pointer again for display
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Inventory Report - Pastelaria Portuguesa</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }

        .report-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .report-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
        }

        .report-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .report-header p {
            color: #666;
            font-size: 14px;
        }

        .statistics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-card h3 {
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .stat-card .value {
            font-size: 24px;
            font-weight: bold;
        }

        .stat-card.warning {
            background: linear-gradient(135deg, #ff9800 0%, #ff5722 100%);
        }

        .stat-card.danger {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
        }

        .stat-card.success {
            background: linear-gradient(135deg, #4caf50 0%, #388e3c 100%);
        }

        .filters-applied {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .filters-applied h3 {
            font-size: 14px;
            margin-bottom: 10px;
            color: #333;
        }

        .filters-applied p {
            font-size: 13px;
            color: #666;
            margin: 5px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        thead {
            background: #667eea;
            color: white;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            font-size: 13px;
        }

        th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        tbody tr:hover {
            background: #f9f9f9;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-ok {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-low {
            background: #fff3e0;
            color: #f57c00;
        }

        .status-out {
            background: #ffebee;
            color: #c62828;
        }

        .status-expired {
            background: #ffcdd2;
            color: #b71c1c;
        }

        .status-expiring {
            background: #ffe0b2;
            color: #e65100;
        }

        .actions {
            margin-bottom: 20px;
            text-align: right;
        }

        .btn {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-left: 10px;
            font-size: 14px;
        }

        .btn:hover {
            background: #5568d3;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        @media print {
            .actions {
                display: none;
            }

            body {
                background: white;
            }

            .report-container {
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="report-header">
            <h1>📊 Inventory Report</h1>
            <p>Pastelaria Portuguesa</p>
            <p>Generated on: <?= date('F d, Y g:i A') ?></p>
        </div>

        <div class="actions">
            <button onclick="window.print()" class="btn">🖨️ Print Report</button>
            <a href="inventory.php" class="btn btn-secondary">← Back to Inventory</a>
        </div>

        <div class="statistics">
            <div class="stat-card success">
                <h3>Total Items</h3>
                <div class="value"><?= $total_items ?></div>
            </div>
            <div class="stat-card success">
                <h3>Total Value</h3>
                <div class="value">₱<?= number_format($total_value, 2) ?></div>
            </div>
            <div class="stat-card warning">
                <h3>Low Stock</h3>
                <div class="value"><?= $low_stock_count ?></div>
            </div>
            <div class="stat-card danger">
                <h3>Out of Stock</h3>
                <div class="value"><?= $out_of_stock_count ?></div>
            </div>
            <div class="stat-card danger">
                <h3>Expired</h3>
                <div class="value"><?= $expired_count ?></div>
            </div>
            <div class="stat-card warning">
                <h3>Expiring Soon</h3>
                <div class="value"><?= $expiring_count ?></div>
            </div>
        </div>

        <?php if (!empty($search) || !empty($category_filter) || !empty($stock_filter) || !empty($expiry_filter)): ?>
        <div class="filters-applied">
            <h3>📋 Filters Applied:</h3>
            <?php if (!empty($search)): ?>
                <p><strong>Search:</strong> <?= htmlspecialchars($search) ?></p>
            <?php endif; ?>
            <?php if (!empty($category_filter)): ?>
                <p><strong>Category:</strong> <?= htmlspecialchars($category_filter) ?></p>
            <?php endif; ?>
            <?php if (!empty($stock_filter)): ?>
                <p><strong>Stock Level:</strong> <?= ucfirst($stock_filter) ?></p>
            <?php endif; ?>
            <?php if ($expiry_filter === 'expired'): ?>
                <p><strong>Expiry Status:</strong> Expired</p>
            <?php endif; ?>
            <?php if ($expiry_filter === 'custom' && $expiry_days > 0): ?>
                <p><strong>Expiry Status:</strong> Expiring in <?= $expiry_days ?> day<?= $expiry_days != 1 ? 's' : '' ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Reorder Level</th>
                    <th>Supplier</th>
                    <th>Cost Price</th>
                    <th>Total Value</th>
                    <th>Expiration</th>
                    <th>Batch</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($inventory && $inventory->num_rows > 0): ?>
                    <?php while ($item = $inventory->fetch_assoc()): 
                        $is_low_stock = $item['quantity'] <= $item['reorder_level'];
                        $is_out_of_stock = $item['quantity'] == 0;
                        $is_expired = !empty($item['expiration_date']) && strtotime($item['expiration_date']) < time();
                        $is_expiring = !empty($item['expiration_date']) && 
                                       strtotime($item['expiration_date']) >= time() && 
                                       strtotime($item['expiration_date']) <= strtotime('+30 days');
                        
                        $status_class = 'status-ok';
                        $status_text = 'OK';
                        if ($is_out_of_stock) {
                            $status_class = 'status-out';
                            $status_text = 'OUT OF STOCK';
                        } elseif ($is_expired) {
                            $status_class = 'status-expired';
                            $status_text = 'EXPIRED';
                        } elseif ($is_expiring) {
                            $status_class = 'status-expiring';
                            $status_text = 'EXPIRING SOON';
                        } elseif ($is_low_stock) {
                            $status_class = 'status-low';
                            $status_text = 'LOW STOCK';
                        }
                        
                        $item_total_value = $item['cost_price'] * $item['quantity'];
                    ?>
                        <tr>
                            <td><?= (int)$item['id'] ?></td>
                            <td><?= htmlspecialchars($item['item_name']) ?></td>
                            <td><?= htmlspecialchars($item['category']) ?></td>
                            <td><?= (int)$item['quantity'] ?></td>
                            <td><?= htmlspecialchars($item['unit']) ?></td>
                            <td><?= (int)$item['reorder_level'] ?></td>
                            <td><?= htmlspecialchars($item['supplier']) ?></td>
                            <td>₱<?= number_format($item['cost_price'], 2) ?></td>
                            <td>₱<?= number_format($item_total_value, 2) ?></td>
                            <td><?= !empty($item['expiration_date']) ? date('M d, Y', strtotime($item['expiration_date'])) : '-' ?></td>
                            <td><?= htmlspecialchars($item['batch_number']) ?></td>
                            <td><span class="status-badge <?= $status_class ?>"><?= $status_text ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="12" style="text-align: center;">No items found matching the selected filters.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>