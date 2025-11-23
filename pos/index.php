<?php
session_start();
include 'db/db_connect.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (isset($_GET['remove'])) {
    $productId = $_GET['remove'];
    // Remove ALL occurrences of this product from cart
    foreach ($_SESSION['cart'] as $index => $item) {
        if ($item['id'] == $productId) {
            unset($_SESSION['cart'][$index]);
        }
    }
    $_SESSION['cart'] = array_values($_SESSION['cart']);
    header('Location: index.php');
    exit;
}

if (isset($_GET['decrease'])) {
    $productId = $_GET['decrease'];
    // Find first occurrence of this product and remove it
    foreach ($_SESSION['cart'] as $index => $item) {
        if ($item['id'] == $productId) {
            unset($_SESSION['cart'][$index]);
            $_SESSION['cart'] = array_values($_SESSION['cart']);
            break;
        }
    }
    header('Location: index.php');
    exit;
}

if (isset($_GET['increase'])) {
    $productId = $_GET['increase'];
    // Find first occurrence of this product and duplicate it
    foreach ($_SESSION['cart'] as $item) {
        if ($item['id'] == $productId) {
            $_SESSION['cart'][] = $item;
            break;
        }
    }
    header('Location: index.php');
    exit;
}

// Group cart items by product ID and calculate quantities
$grouped_cart = [];
foreach ($_SESSION['cart'] as $index => $item) {
    $key = $item['id'];
    if (!isset($grouped_cart[$key])) {
        $grouped_cart[$key] = [
            'id' => $item['id'],
            'name' => $item['name'],
            'price' => $item['price'],
            'quantity' => 0,
            'indexes' => []
        ];
    }
    $grouped_cart[$key]['quantity']++;
    $grouped_cart[$key]['indexes'][] = $index;
}

$subtotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += $item['price'];
}
$total = $subtotal;

// Check inventory alerts
$low_stock_result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE quantity <= reorder_level");
$low_stock_count = $low_stock_result ? $low_stock_result->fetch_assoc()['count'] : 0;

$expired_result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE expiration_date < CURDATE()");
$expired_count = $expired_result ? $expired_result->fetch_assoc()['count'] : 0;

$expiring_result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
$expiring_count = $expiring_result ? $expiring_result->fetch_assoc()['count'] : 0;

$show_alerts = ($low_stock_count > 0 || $expired_count > 0 || $expiring_count > 0);

// Check if alert has been shown in this session
if (!isset($_SESSION['alert_shown'])) {
    $_SESSION['alert_shown'] = false;
}

// Only show alert if it hasn't been shown yet in this session
$display_alert = $show_alerts && !$_SESSION['alert_shown'];

// Mark alert as shown when displayed
if ($display_alert) {
    $_SESSION['alert_shown'] = true;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Transaction - Pastelaria Portuguesa</title>
    <link rel="stylesheet" href="css/index.css">
    <style>
        /* Inventory Alert Styles */
        .inventory-alerts {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            width: 320px;
            max-height: 400px;
            overflow-y: auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .alert-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .alert-header h3 {
            margin: 0;
            font-size: 1.1em;
        }

        .alert-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            line-height: 1;
        }

        .alert-close:hover {
            opacity: 0.8;
        }

        .alert-body {
            padding: 15px 20px;
        }

        .alert-item {
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 8px;
            font-size: 0.95em;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .alert-item.critical {
            background: #ffebee;
            border-left: 4px solid #f44336;
        }

        .alert-item.warning {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
        }

        .alert-item.info {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
        }

        .alert-count {
            font-weight: bold;
            font-size: 1.2em;
        }

        .alert-link {
            display: block;
            text-align: center;
            padding: 12px;
            background: #f5f5f5;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            border-radius: 0 0 12px 12px;
            transition: background 0.3s ease;
        }

        .alert-link:hover {
            background: #e0e0e0;
        }

        .inventory-badge {
            position: relative;
            display: inline-block;
        }

        .inventory-badge .badge-dot {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 20px;
            height: 20px;
            background: #f44336;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: white;
            font-weight: bold;
        }

        /* Quantity Styles */
        .cart-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .item-quantity {
            background: #667eea;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: bold;
            min-width: 30px;
            text-align: center;
        }

        .item-details {
            flex: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .qty-btn {
            background: #667eea;
            color: white;
            border: none;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s ease;
            text-decoration: none;
            line-height: 1;
        }

        .qty-btn:hover {
            background: #5568d3;
        }

        .qty-btn.decrease {
            background: #ff9800;
        }

        .qty-btn.decrease:hover {
            background: #f57c00;
        }

        .remove {
            background: #f44336;
            color: white;
            border: none;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: background 0.3s ease;
        }

        .remove:hover {
            background: #d32f2f;
        }

        @media (max-width: 768px) {
            .inventory-alerts {
                width: calc(100% - 40px);
                left: 20px;
                right: 20px;
            }
        }
    </style>
</head>

<body>
    <!-- Inventory Alerts -->
    <?php if ($display_alert): ?>
        <div class="inventory-alerts" id="inventoryAlerts">
            <div class="alert-header">
                <h3>⚠️ Inventory Alerts</h3>
                <button class="alert-close" onclick="document.getElementById('inventoryAlerts').style.display='none'">&times;</button>
            </div>
            <div class="alert-body">
                <?php if ($expired_count > 0): ?>
                    <div class="alert-item critical">
                        <span>❌ Expired Items</span>
                        <span class="alert-count"><?= $expired_count ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($expiring_count > 0): ?>
                    <div class="alert-item warning">
                        <span>⏰ Expiring This Week</span>
                        <span class="alert-count"><?= $expiring_count ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($low_stock_count > 0): ?>
                    <div class="alert-item info">
                        <span>📦 Low Stock Items</span>
                        <span class="alert-count"><?= $low_stock_count ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <a href="inventory.php" class="alert-link">View Inventory Details →</a>
        </div>
    <?php endif; ?>

    <div class="container">
        <!-- CART -->
        <div class="cart">
            <div class="cart-header">
                <input type="checkbox" id="menu-toggle" hidden>
                <!-- SIDEBAR MENU -->
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
                        <li>
                            <a href="inventory.php" class="inventory-badge">
                                Inventory
                                <?php if ($show_alerts): ?>
                                    <span class="badge-dot"><?= $low_stock_count + $expired_count + $expiring_count ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li><a href="sales-history.php">Sales History</a></li>
                    </ul>
                </div>
                <!-- SIDEBAR MENU -->

                <h2>Sales Transaction</h2>

            </div>

            <div class="cart-items">
                <?php if (!empty($grouped_cart)): ?>
                    <?php foreach ($grouped_cart as $item): ?>
                        <div class="cart-item">
                            <span class="item-quantity"><?= $item['quantity'] ?>x</span>
                            <div class="item-details">
                                <span><?= htmlspecialchars($item['name']) ?></span>
                                <span>₱<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                            </div>
                            <div class="quantity-controls">
                                <a href="?decrease=<?= $item['id'] ?>" class="qty-btn decrease">−</a>
                                <a href="?increase=<?= $item['id'] ?>" class="qty-btn">+</a>
                                <a href="?remove=<?= $item['id'] ?>" class="remove" onclick="return confirm('Remove all <?= htmlspecialchars($item['name']) ?> from cart?')">✖</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="empty">No items in cart</p>
                <?php endif; ?>
            </div>

            <div class="cart-summary">
                <div class="summary-row">
                    <span>Items:</span>
                    <span><?= count($_SESSION['cart']) ?></span>
                </div>

                <div class="summary-row total">
                    <span>Total:</span>
                    <span>₱<?= number_format($total, 2) ?></span>
                </div>

                <div class="payment-buttons">
                    <form action="cart.php" method="POST">
                        <button name="clear" class="all-payments">Clear Cart</button>
                    </form>
                    <form action="cash.php" method="POST">
                        <button type="submit" name="cash" class="cash">Cash</button>
                    </form>
                    <?php if (!empty($_SESSION['last_sale_id'])): ?>
                        <form action="receipt.php" method="POST">
                            <input type="hidden" name="sale_id" value="<?= (int)$_SESSION['last_sale_id'] ?>">
                            <button type="submit" class="receipt">Generate Receipt</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>


        <!-- MENU -->
        <div class="menu">
            <?php
            $search = trim($_GET['search'] ?? '');
            $selected_category = $_GET['category'] ?? 'All';

            $sql = "SELECT * FROM products WHERE 1";

            if ($selected_category !== 'All') {
                $category_safe = $conn->real_escape_string($selected_category);
                $sql .= " AND category = '$category_safe'";
            }

            if (!empty($search)) {
                $search_safe = $conn->real_escape_string($search);
                $sql .= " AND (name LIKE '%$search_safe%' OR price LIKE '%$search_safe%')";
            }

            $result = $conn->query($sql);
            ?>
            <form method="GET" class="search-filter-bar">
                <div class="menu-header">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search product name or price...">
                    <button type="submit" class="btn-search">Search</button>
                    <a href="index.php" class="btn-reset">Reset</a>
                </div>
            </form>

            <!-- ============================================================================================================== -->
            <?php
            // get selected category
            $selected_category = isset($_GET['category']) ? $_GET['category'] : 'All';
            ?>

            <div class="categories">
                <a href="?category=All" class="category <?= $selected_category == 'All' ? 'active' : '' ?>">All</a>
                <a href="?category=Pastries" class="category <?= $selected_category == 'Pastries' ? 'active' : '' ?>">Pastries</a>
                <a href="?category=Breads" class="category <?= $selected_category == 'Breads' ? 'active' : '' ?>">Breads</a>
                <a href="?category=Savory Items" class="category <?= $selected_category == 'Savory Items' ? 'active' : '' ?>">Savory Items</a>
                <a href="?category=Beverages" class="category <?= $selected_category == 'Beverages' ? 'active' : '' ?>">Beverages</a>
            </div>

            <!-- product display -->
            <div class="products">
                <?php
                if ($result->num_rows > 0) {
                    while ($product = $result->fetch_assoc()) {
                ?>
                        <form action="cart.php" method="POST" class="product">
                            <input type="hidden" name="id" value="<?= $product['id'] ?>">
                            <input type="hidden" name="name" value="<?= htmlspecialchars($product['name']) ?>">
                            <input type="hidden" name="price" value="<?= htmlspecialchars($product['price']) ?>">
                            <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                            <p><?= htmlspecialchars($product['name']) ?></p>
                            <span>₱<?= number_format($product['price'], 2) ?></span>
                            <button type="submit" class="add-btn">Add</button>
                        </form>

                <?php
                    }
                } else {
                    echo "<p class='no-results'>No products found for your search/filter.</p>";
                }

                $conn->close();
                ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide alerts after 10 seconds
        setTimeout(function() {
            const alerts = document.getElementById('inventoryAlerts');
            if (alerts) {
                alerts.style.transition = 'opacity 0.5s ease-out';
                alerts.style.opacity = '0';
                setTimeout(function() {
                    alerts.style.display = 'none';
                }, 500);
            }
        }, 10000);
    </script>
</body>

</html>