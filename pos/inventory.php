<?php
session_start();
include 'db/db_connect.php';

// Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $category = $conn->real_escape_string(trim($_POST['new_category'] ?? ''));
    if (!empty($category)) {
        $sql = "INSERT INTO inventory_categories (category_name) VALUES ('$category')";
        if ($conn->query($sql)) {
            $_SESSION['inventory_message'] = "Category '$category' added successfully.";
        } else {
            $_SESSION['inventory_message'] = 'Failed to add category: ' . $conn->error;
        }
    }
    header('Location: inventory.php');
    exit;
}

// Delete Selected Items
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_selected'])) {
    $ids = $_POST['ids'] ?? [];
    $ids = array_map('intval', $ids);

    if (!empty($ids)) {
        $in = implode(',', $ids);

        // Delete associated images
        $res = $conn->query("SELECT image FROM inventory WHERE id IN ($in)");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                if (!empty($r['image'])) {
                    $imgPath = __DIR__ . DIRECTORY_SEPARATOR . $r['image'];
                    if (file_exists($imgPath) && is_file($imgPath)) {
                        @unlink($imgPath);
                    }
                }
            }
        }

        $conn->query("DELETE FROM inventory WHERE id IN ($in)");
        $_SESSION['inventory_message'] = count($ids) . ' item(s) deleted.';
    } else {
        $_SESSION['inventory_message'] = 'No items selected.';
    }

    header('Location: inventory.php');
    exit;
}

// Update Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item'])) {
    $id = intval($_POST['id']);
    $item_name = $conn->real_escape_string(trim($_POST['item_name'] ?? ''));
    $category = $conn->real_escape_string(trim($_POST['category'] ?? ''));
    $quantity = intval($_POST['quantity'] ?? 0);
    $unit = $conn->real_escape_string(trim($_POST['unit'] ?? 'pcs'));
    $reorder_level = intval($_POST['reorder_level'] ?? 10);
    $supplier = $conn->real_escape_string(trim($_POST['supplier'] ?? ''));
    $cost_price = floatval($_POST['cost_price'] ?? 0);
    $expiration_date = !empty($_POST['expiration_date']) ? "'" . $conn->real_escape_string($_POST['expiration_date']) . "'" : "NULL";
    $batch_number = $conn->real_escape_string(trim($_POST['batch_number'] ?? ''));
    $notes = $conn->real_escape_string(trim($_POST['notes'] ?? ''));

    $newImagePath = '';
    if (isset($_FILES['image']) && isset($_FILES['image']['tmp_name']) && $_FILES['image']['tmp_name'] !== '') {
        $targetDir = __DIR__ . '/uploads/inventory/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $imageName = basename($_FILES['image']['name']);
        $targetFile = $targetDir . $imageName;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $newImagePath = 'uploads/inventory/' . $imageName;

            // Delete old image
            $res = $conn->query("SELECT image FROM inventory WHERE id = $id LIMIT 1");
            if ($res && $row = $res->fetch_assoc()) {
                if (!empty($row['image'])) {
                    $old = __DIR__ . DIRECTORY_SEPARATOR . $row['image'];
                    if (file_exists($old) && is_file($old)) {
                        @unlink($old);
                    }
                }
            }
        }
    }

    if ($newImagePath !== '') {
        $sql = "UPDATE inventory SET item_name='$item_name', category='$category', quantity=$quantity, 
                unit='$unit', reorder_level=$reorder_level, supplier='$supplier', cost_price=$cost_price, 
                expiration_date=$expiration_date, batch_number='$batch_number', notes='$notes', 
                image='$newImagePath' WHERE id=$id";
    } else {
        $sql = "UPDATE inventory SET item_name='$item_name', category='$category', quantity=$quantity, 
                unit='$unit', reorder_level=$reorder_level, supplier='$supplier', cost_price=$cost_price, 
                expiration_date=$expiration_date, batch_number='$batch_number', notes='$notes' WHERE id=$id";
    }

    if ($conn->query($sql)) {
        $_SESSION['inventory_message'] = 'Item updated successfully.';
    } else {
        $_SESSION['inventory_message'] = 'Failed to update item: ' . $conn->error;
    }

    header('Location: inventory.php');
    exit;
}

// Add New Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $item_name = $conn->real_escape_string(trim($_POST['item_name'] ?? ''));
    $category = $conn->real_escape_string(trim($_POST['category'] ?? ''));
    $quantity = intval($_POST['quantity'] ?? 0);
    $unit = $conn->real_escape_string(trim($_POST['unit'] ?? 'pcs'));
    $reorder_level = intval($_POST['reorder_level'] ?? 10);
    $supplier = $conn->real_escape_string(trim($_POST['supplier'] ?? ''));
    $cost_price = floatval($_POST['cost_price'] ?? 0);
    $expiration_date = !empty($_POST['expiration_date']) ? "'" . $conn->real_escape_string($_POST['expiration_date']) . "'" : "NULL";
    $batch_number = $conn->real_escape_string(trim($_POST['batch_number'] ?? ''));
    $notes = $conn->real_escape_string(trim($_POST['notes'] ?? ''));

    $imagePath = '';
    if (isset($_FILES['image']) && isset($_FILES['image']['tmp_name']) && $_FILES['image']['tmp_name'] !== '') {
        $targetDir = __DIR__ . '/uploads/inventory/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $imageName = basename($_FILES['image']['name']);
        $targetFile = $targetDir . $imageName;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $imagePath = 'uploads/inventory/' . $imageName;
        }
    }

    $sql = "INSERT INTO inventory (item_name, category, quantity, unit, reorder_level, supplier, cost_price, 
            expiration_date, batch_number, notes, image) VALUES 
            ('$item_name', '$category', $quantity, '$unit', $reorder_level, '$supplier', $cost_price, 
            $expiration_date, '$batch_number', '$notes', '$imagePath')";

    if ($conn->query($sql)) {
        $_SESSION['inventory_message'] = 'Item added successfully.';
    } else {
        $_SESSION['inventory_message'] = 'Failed to add item: ' . $conn->error;
    }

    header('Location: inventory.php');
    exit;
}

// Get all categories for dropdown
$categories_result = $conn->query("SELECT category_name FROM inventory_categories ORDER BY category_name ASC");
$categories = [];
if ($categories_result) {
    while ($cat = $categories_result->fetch_assoc()) {
        $categories[] = $cat['category_name'];
    }
}

// Filters
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

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Sorting
$order_by = match ($sort) {
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

// Low stock count
$low_stock_result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE quantity <= reorder_level");
$low_stock_count = $low_stock_result->fetch_assoc()['count'];

// Expired items count
$expired_result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE expiration_date < CURDATE()");
$expired_count = $expired_result->fetch_assoc()['count'];

// Expiring soon count (within 7 days)
$expiring_result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
$expiring_count = $expiring_result->fetch_assoc()['count'];

// Edit item
$edit_item = null;
if (isset($_GET['edit_id'])) {
    $id = intval($_GET['edit_id']);
    $r = $conn->query("SELECT * FROM inventory WHERE id = $id LIMIT 1");
    if ($r && $r->num_rows > 0) {
        $edit_item = $r->fetch_assoc();
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
    <title>Inventory - Pastelaria Portuguesa</title>
    <link rel="stylesheet" href="css/invent.css">
</head>

<body>
    <div class="headerbar">
        <h1 class="heads">Inventory Management</h1>
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
            <li>
                <a href="inventory.php" class="inventory-badge">
                    Inventory
                    <?php if ($show_alerts): ?>
                        <span class="badge-dot"><?= $low_stock_count + $expired_count + $expiring_count ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li><a href="sales-history.php">Sales History</a></li>
            <li><a href="admin-notifications.php">Admin Notifications</a></li>
        </ul>
        <div style="position: absolute; bottom: 20px; left: 20px; right: 20px;">
            <form action="login.php" method="POST" onsubmit="return confirm('Are you sure you want to logout?');">
                <button type="submit" class="logout" style="width:100%; padding:10px; background:#f44336; color:#fff; border:none; border-radius:8px; cursor:pointer;">
                    Logout
                </button>
            </form>
        </div>
    </div>
    <!-- SIDEBAR MENU -->

    <div class="inventory-wrap">
        <!-- Alerts Dashboard -->
        <div class="alerts-dashboard">
            <div class="alert-card low-stock">
                <h3>⚠️ Low Stock</h3>
                <p class="alert-count"><?= $low_stock_count ?></p>
                <a href="?stock=low">View Items</a>
            </div>
            <div class="alert-card expired">
                <h3>❌ Expired</h3>
                <p class="alert-count"><?= $expired_count ?></p>
                <a href="?expiry=expired">View Items</a>
            </div>
            <div class="alert-card expiring">
                <h3>⏰ Expiring Soon (7 days)</h3>
                <p class="alert-count"><?= $expiring_count ?></p>
                <a href="?expiry=custom&expiry_days=7">View Items</a>
            </div>
        </div>

        <?php if (!empty($_SESSION['inventory_message'])): ?>
            <div class="msg"><?php echo htmlspecialchars($_SESSION['inventory_message']);
                                unset($_SESSION['inventory_message']); ?></div>
        <?php endif; ?>

        <!-- Add/Edit Form -->
        <?php if ($edit_item || isset($_GET['add'])): ?>
            <div class="form-section">
                <h3><?= $edit_item ? 'Edit Item (ID: ' . (int)$edit_item['id'] . ')' : 'Add New Item' ?></h3>
                <form action="inventory.php" method="POST" enctype="multipart/form-data">
                    <?php if ($edit_item): ?>
                        <input type="hidden" name="id" value="<?= (int)$edit_item['id'] ?>">
                    <?php endif; ?>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Item Name *</label>
                            <input type="text" name="item_name" value="<?= $edit_item ? htmlspecialchars($edit_item['item_name']) : '' ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"
                                        <?= ($edit_item && $edit_item['category'] == $cat) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Quantity *</label>
                            <input type="number" name="quantity" value="<?= $edit_item ? (int)$edit_item['quantity'] : 0 ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Unit *</label>
                            <input type="text" name="unit" value="<?= $edit_item ? htmlspecialchars($edit_item['unit']) : 'pcs' ?>" placeholder="pcs, kg, liters, etc." required>
                        </div>
                        <div class="form-group">
                            <label>Reorder Level *</label>
                            <input type="number" name="reorder_level" value="<?= $edit_item ? (int)$edit_item['reorder_level'] : 10 ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Supplier</label>
                            <input type="text" name="supplier" value="<?= $edit_item ? htmlspecialchars($edit_item['supplier']) : '' ?>">
                        </div>
                        <div class="form-group">
                            <label>Cost Price *</label>
                            <input type="number" name="cost_price" step="0.01" value="<?= $edit_item ? htmlspecialchars($edit_item['cost_price']) : 0 ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Expiration Date</label>
                            <input type="date" name="expiration_date" value="<?= $edit_item ? htmlspecialchars($edit_item['expiration_date']) : '' ?>">
                        </div>
                        <div class="form-group">
                            <label>Batch Number</label>
                            <input type="text" name="batch_number" value="<?= $edit_item ? htmlspecialchars($edit_item['batch_number']) : '' ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" rows="3"><?= $edit_item ? htmlspecialchars($edit_item['notes']) : '' ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Image</label>
                        <input type="file" name="image" accept="image/*">
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="<?= $edit_item ? 'update_item' : 'add_item' ?>" class="btn btn-primary">
                            <?= $edit_item ? 'Update Item' : 'Add Item' ?>
                        </button>
                        <a href="inventory.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
            <hr>
        <?php endif; ?>

        <!-- Category Management -->
        <div class="category-section">
            <button onclick="document.getElementById('categoryModal').style.display='block'" class="btn btn-secondary">Manage Categories</button>
        </div>

        <!-- Filters & Search -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <input type="text" name="search" placeholder="Search items, supplier, batch..." value="<?= htmlspecialchars($search) ?>">

                <select name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $category_filter == $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="stock">
                    <option value="">All Stock Levels</option>
                    <option value="low" <?= $stock_filter == 'low' ? 'selected' : '' ?>>Low Stock</option>
                    <option value="out" <?= $stock_filter == 'out' ? 'selected' : '' ?>>Out of Stock</option>
                </select>

                <select name="expiry" id="expirySelect" onchange="toggleExpiryInput()">
                    <option value="">All Expiry Status</option>
                    <option value="expired" <?= $expiry_filter == 'expired' ? 'selected' : '' ?>>Expired</option>
                    <option value="custom" <?= $expiry_filter == 'custom' ? 'selected' : '' ?>>Expiring in Custom Days</option>
                </select>

                <div id="customDaysInput" style="display: <?= $expiry_filter == 'custom' ? 'inline-block' : 'none' ?>;">
                    <input type="number" name="expiry_days" min="1" placeholder="Enter days" value="<?= htmlspecialchars($_GET['expiry_days'] ?? '') ?>" style="width: 120px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" title="Number of days">
                </div>

                <select name="sort">
                    <option value="id_desc" <?= $sort == 'id_desc' ? 'selected' : '' ?>>Newest First</option>
                    <option value="id_asc" <?= $sort == 'id_asc' ? 'selected' : '' ?>>Oldest First</option>
                    <option value="name_asc" <?= $sort == 'name_asc' ? 'selected' : '' ?>>Name A-Z</option>
                    <option value="name_desc" <?= $sort == 'name_desc' ? 'selected' : '' ?>>Name Z-A</option>
                    <option value="quantity_asc" <?= $sort == 'quantity_asc' ? 'selected' : '' ?>>Quantity Low-High</option>
                    <option value="quantity_desc" <?= $sort == 'quantity_desc' ? 'selected' : '' ?>>Quantity High-Low</option>
                    <option value="expiry_asc" <?= $sort == 'expiry_asc' ? 'selected' : '' ?>>Expiry Soonest</option>
                    <option value="expiry_desc" <?= $sort == 'expiry_desc' ? 'selected' : '' ?>>Expiry Latest</option>
                </select>

                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="inventory.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>

        <!-- Actions -->
        <div class="actions-bar">
            <form method="POST" style="display:inline;">
                <button type="submit" name="delete_selected" class="btn btn-delete" onclick="return confirm('Delete selected items?')">Delete Selected</button>
            </form>
            <a href="inventory.php?add=1" class="btn btn-add">Add New Item</a>
            <button onclick="window.print()" class="btn btn-print">🖨️ Print Inventory</button>
            <a href="inventory_report.php?<?= http_build_query($_GET) ?>" class="btn btn-report" target="_blank">📊 Generate Report</a>
        </div>

        <!-- Inventory Table -->
        <form method="POST" id="inventoryForm">
            <div class="table-container">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Reorder Level</th>
                            <th>Supplier</th>
                            <th>Cost Price</th>
                            <th>Expiration</th>
                            <th>Batch</th>
                            <th>Status</th>
                            <th>Actions</th>
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

                                $status_class = '';
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
                            ?>
                                <tr class="<?= $status_class ?>">
                                    <td><input type="checkbox" name="ids[]" value="<?= (int)$item['id'] ?>"></td>
                                    <td><?= (int)$item['id'] ?></td>
                                    <td>
                                        <?php if (!empty($item['image'])): ?>
                                            <img src="<?= htmlspecialchars($item['image']) ?>" class="thumb" alt="<?= htmlspecialchars($item['item_name']) ?>">
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                                    <td><?= htmlspecialchars($item['category']) ?></td>
                                    <td class="<?= $is_low_stock ? 'text-warning' : '' ?>"><?= (int)$item['quantity'] ?></td>
                                    <td><?= htmlspecialchars($item['unit']) ?></td>
                                    <td><?= (int)$item['reorder_level'] ?></td>
                                    <td><?= htmlspecialchars($item['supplier']) ?></td>
                                    <td>₱<?= number_format($item['cost_price'], 2) ?></td>
                                    <td class="<?= $is_expired || $is_expiring ? 'text-danger' : '' ?>">
                                        <?= !empty($item['expiration_date']) ? date('M d, Y', strtotime($item['expiration_date'])) : '-' ?>
                                    </td>
                                    <td><?= htmlspecialchars($item['batch_number']) ?></td>
                                    <td><span class="status-badge <?= $status_class ?>"><?= $status_text ?></span></td>
                                    <td>
                                        <a href="inventory.php?edit_id=<?= (int)$item['id'] ?>" class="btn btn-edit">Edit</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="14">No items found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    <!-- Category Modal -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('categoryModal').style.display='none'">&times;</span>
            <h3>Manage Categories</h3>
            <form method="POST">
                <input type="text" name="new_category" placeholder="New category name" required>
                <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
            </form>
            <div class="category-list">
                <h4>Existing Categories:</h4>
                <ul>
                    <?php foreach ($categories as $cat): ?>
                        <li><?= htmlspecialchars($cat) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Select all checkbox
        document.getElementById('selectAll')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="ids[]"]');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
        
        // Toggle custom days input
        function toggleExpiryInput() {
            const select = document.getElementById('expirySelect');
            const customInput = document.getElementById('customDaysInput');
            if (select.value === 'custom') {
                customInput.style.display = 'inline-block';
            } else {
                customInput.style.display = 'none';
            }
        }
    </script>
</body>

</html>