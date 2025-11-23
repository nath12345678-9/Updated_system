<?php
session_start();
include 'db/db_connect.php';

// Fetch categories from the categories table (same as add.php)
$categories_query = "SELECT DISTINCT name FROM categories ORDER BY name ASC";
$categories_result = $conn->query($categories_query);
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row['name'];
}

// Fetch products grouped by category
$products_by_category = [];
$products_query = "SELECT * FROM products ORDER BY category, name";
$products_result = $conn->query($products_query);

while ($product = $products_result->fetch_assoc()) {
    $category = $product['category'];
    if (!isset($products_by_category[$category])) {
        $products_by_category[$category] = [];
    }
    $products_by_category[$category][] = $product;
}

// Handle form submission for recording sales
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_sale'])) {
    $items = $_POST['items'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $payment_method = $_POST['payment_method'] ?? 'Cash';
    
    if (!empty($items) && !empty($quantities)) {
        $total = 0;
        $sale_items = [];
        
        // Calculate total and prepare sale items
        foreach ($items as $index => $product_id) {
            $qty = intval($quantities[$index]);
            if ($qty > 0) {
                // Get product details
                $stmt = $conn->prepare("SELECT name, price FROM products WHERE id = ?");
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $product = $result->fetch_assoc();
                
                if ($product) {
                    $subtotal = $product['price'] * $qty;
                    $total += $subtotal;
                    $sale_items[] = [
                        'product_id' => $product_id,
                        'name' => $product['name'],
                        'quantity' => $qty,
                        'price' => $product['price'],
                        'subtotal' => $subtotal
                    ];
                }
                $stmt->close();
            }
        }
        
        // Insert sale record
        if (!empty($sale_items)) {
            // Check which columns exist in the sales table
            $check_payment_method = $conn->query("SHOW COLUMNS FROM sales LIKE 'payment_method'");
            $check_payment_status = $conn->query("SHOW COLUMNS FROM sales LIKE 'payment_status'");
            
            $has_payment_method = ($check_payment_method->num_rows > 0);
            $has_payment_status = ($check_payment_status->num_rows > 0);
            
            // Build the appropriate SQL query based on available columns
            if ($has_payment_method && $has_payment_status) {
                // Both columns exist (full GCash support)
                $payment_status = ($payment_method === 'GCash') ? 'pending' : 'completed';
                $stmt = $conn->prepare("INSERT INTO sales (total, payment_method, payment_status, sale_date) VALUES (?, ?, ?, NOW())");
                if (!$stmt) {
                    die("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("dss", $total, $payment_method, $payment_status);
            } elseif ($has_payment_method) {
                // Only payment_method exists
                $stmt = $conn->prepare("INSERT INTO sales (total, payment_method, sale_date) VALUES (?, ?, NOW())");
                if (!$stmt) {
                    die("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("ds", $total, $payment_method);
            } else {
                // Original structure (no payment columns)
                $stmt = $conn->prepare("INSERT INTO sales (total, sale_date) VALUES (?, NOW())");
                if (!$stmt) {
                    die("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("d", $total);
            }
            
            if ($stmt->execute()) {
                $sale_id = $conn->insert_id;
                
                // Insert sale items
                $item_stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
                foreach ($sale_items as $item) {
                    $item_stmt->bind_param("iiidd", $sale_id, $item['product_id'], $item['quantity'], $item['price'], $item['subtotal']);
                    $item_stmt->execute();
                }
                $item_stmt->close();
                
                // Redirect based on payment method
                if ($has_payment_method && $has_payment_status && $payment_method === 'GCash') {
                    $_SESSION['success_message'] = "Order created! Please complete your GCash payment.";
                    header("Location: gcash-payment.php?sale_id=" . $sale_id);
                } else {
                    $_SESSION['success_message'] = "Sale recorded successfully! Total: ₱" . number_format($total, 2);
                    header("Location: receipt.php?sale_id=" . $sale_id);
                }
                exit;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sales Transaction - Pastelaria Portuguesa</title>
    <link rel="stylesheet" href="css/indeex.css">
</head>
<body>
    <div class="headerbar">
        <h1 class="heads">Sales Transaction</h1>
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
            <li><a href="sales_report.php">Sales Report</a></li>
            <li><a href="admin-notification.php">Admin Notifications</a></li>
        </ul>
    </div>

    <div class="main-container">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-message">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <div class="content-wrapper">
            <!-- Products Section -->
            <div class="products-section">
                <h2>📦 Select Products</h2>
                
                <!-- Category Tabs -->
                <div class="category-tabs">
                    <button class="tab-btn active" onclick="showCategory('all')">All Products</button>
                    <?php foreach ($categories as $category): ?>
                        <button class="tab-btn" onclick="showCategory('<?= htmlspecialchars($category) ?>')">
                            <?= htmlspecialchars($category) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Products Grid -->
                <div class="products-grid" id="productsGrid">
                    <?php foreach ($products_by_category as $category => $products): ?>
                        <?php foreach ($products as $product): ?>
                            <div class="product-card" data-category="<?= htmlspecialchars($category) ?>">
                                <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                                <div class="product-info">
                                    <h3><?= htmlspecialchars($product['name']) ?></h3>
                                    <p class="category-badge"><?= htmlspecialchars($category) ?></p>
                                    <p class="price">₱<?= number_format($product['price'], 2) ?></p>
                                    <button class="add-btn" onclick="addToCart(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>', <?= $product['price'] ?>)">
                                        + Add to Cart
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Cart Section -->
            <div class="cart-section">
                <h2>🛒 Shopping Cart</h2>
                
                <form method="POST" id="saleForm">
                    <div id="cartItems" class="cart-items">
                        <p class="empty-cart">Cart is empty. Add products to start a transaction.</p>
                    </div>

                    <div class="cart-summary">
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span id="subtotal">₱0.00</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span id="total">₱0.00</span>
                        </div>
                    </div>

                    <div class="payment-section">
                        <label>Payment Method:</label>
                        <select name="payment_method" id="paymentMethod" required>
                            <option value="Cash">Cash</option>
                            <option value="GCash">GCash</option>
                            
                        </select>
                        <p id="gcashNote" style="display: none; font-size: 0.9em; color: #0066ff; margin-top: 10px;">
                            💳 You will be redirected to complete your GCash payment
                        </p>
                    </div>

                    <div class="cart-actions">
                        <button type="button" class="btn-clear" onclick="clearCart()">Clear Cart</button>
                        <button type="submit" name="record_sale" class="btn-checkout" id="checkoutBtn" disabled>
                            Complete Transaction
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let cart = [];

        // Show GCash note when GCash is selected
        document.getElementById('paymentMethod').addEventListener('change', function() {
            const gcashNote = document.getElementById('gcashNote');
            gcashNote.style.display = this.value === 'GCash' ? 'block' : 'none';
        });

        function showCategory(category) {
            const products = document.querySelectorAll('.product-card');
            const tabs = document.querySelectorAll('.tab-btn');
            
            // Update active tab
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            // Show/hide products
            products.forEach(product => {
                if (category === 'all' || product.dataset.category === category) {
                    product.style.display = 'block';
                } else {
                    product.style.display = 'none';
                }
            });
        }

        function addToCart(id, name, price) {
            const existingItem = cart.find(item => item.id === id);
            
            if (existingItem) {
                existingItem.quantity++;
            } else {
                cart.push({ id, name, price, quantity: 1 });
            }
            
            updateCart();
        }

        function removeFromCart(id) {
            cart = cart.filter(item => item.id !== id);
            updateCart();
        }

        function updateQuantity(id, quantity) {
            const item = cart.find(item => item.id === id);
            if (item) {
                item.quantity = Math.max(1, parseInt(quantity));
                updateCart();
            }
        }

        function updateCart() {
            const cartItemsDiv = document.getElementById('cartItems');
            const checkoutBtn = document.getElementById('checkoutBtn');
            
            if (cart.length === 0) {
                cartItemsDiv.innerHTML = '<p class="empty-cart">Cart is empty. Add products to start a transaction.</p>';
                checkoutBtn.disabled = true;
            } else {
                let html = '';
                let total = 0;
                
                cart.forEach(item => {
                    const subtotal = item.price * item.quantity;
                    total += subtotal;
                    
                    html += `
                        <div class="cart-item">
                            <input type="hidden" name="items[]" value="${item.id}">
                            <div class="item-details">
                                <h4>${item.name}</h4>
                                <p class="item-price">₱${item.price.toFixed(2)} each</p>
                            </div>
                            <div class="item-controls">
                                <input type="number" name="quantities[]" value="${item.quantity}" min="1" 
                                       onchange="updateQuantity(${item.id}, this.value)" class="qty-input">
                                <button type="button" class="btn-remove" onclick="removeFromCart(${item.id})">×</button>
                            </div>
                            <div class="item-subtotal">₱${subtotal.toFixed(2)}</div>
                        </div>
                    `;
                });
                
                cartItemsDiv.innerHTML = html;
                document.getElementById('subtotal').textContent = '₱' + total.toFixed(2);
                document.getElementById('total').textContent = '₱' + total.toFixed(2);
                checkoutBtn.disabled = false;
            }
        }

        function clearCart() {
            if (confirm('Are you sure you want to clear the cart?')) {
                cart = [];
                updateCart();
            }
        }
    </script>
</body>
</html>