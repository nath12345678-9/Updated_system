<?php
$success = "";
$error = "";

include 'db/db_connect.php';



// Handle adding new category
if (isset($_POST['add_category'])) {
    $new_category = trim($_POST['new_category']);
    if (!empty($new_category)) {
        // Check if category already exists
        $check_stmt = $conn->prepare("SELECT name FROM categories WHERE name = ? LIMIT 1");
        $check_stmt->bind_param("s", $new_category);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "❌ Category already exists!";
        } else {
            // Insert new category
            $insert_stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
            $insert_stmt->bind_param("s", $new_category);
            if ($insert_stmt->execute()) {
                $success = "✅ Category '$new_category' added successfully!";
            } else {
                $error = "❌ Failed to add category!";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

// Get existing categories from categories table
$categories_query = "SELECT name FROM categories ORDER BY name";
$categories_result = $conn->query($categories_query);
$existing_categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $existing_categories[] = $row['name'];
}

// Handle adding product
if (isset($_POST['submit_product'])) {
    $category = $_POST['category'];
    $name = $_POST['name'];
    $price = $_POST['price'];

    $targetDir = __DIR__ . "/uploads/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777);
    }

    $imageName = basename($_FILES["image"]["name"]);
    $targetFile = $targetDir . $imageName;

    if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
        $relativePath = "uploads/" . $imageName;
        $float_price = floatval($price);
        $stmt = $conn->prepare("INSERT INTO products (category, name, price, image) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssds", $category, $name, $float_price, $relativePath);

        if ($stmt->execute()) {
            $success = "✅ Item inserted successfully!";
        } else {
            $error = "❌ Database insert failed: " . $stmt->error;
        }

        $stmt->close();
    } else {
        $error = "❌ Failed to upload image!";
    }
}

$low_stock_result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE quantity <= reorder_level");
$low_stock_count = $low_stock_result ? $low_stock_result->fetch_assoc()['count'] : 0;
$expired_result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE expiration_date < CURDATE()");
$expired_count = $expired_result ? $expired_result->fetch_assoc()['count'] : 0;
$expiring_result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
$expiring_count = $expiring_result ? $expiring_result->fetch_assoc()['count'] : 0;
$show_alerts = ($low_stock_count > 0 || $expired_count > 0 || $expiring_count > 0);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Add Product - Pastelaria Portuguesa</title>
    <link rel="stylesheet" href="css/add.css">

</head>

<body>
    <div class="headerbar">
        <h1 class="heads">Add Products</h1>
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

    <!-- Popup Notification -->
    <?php if ($success || $error): ?>
        <div class="popup-notification <?= $success ? 'success' : 'error' ?>" id="popup">
            <?= $success ? $success : $error ?>
        </div>
    <?php endif; ?>

    <div class="container">
        <h2>Add New Product</h2>

        <!-- Category Management Section -->
        <div class="category-section">
            <h3>📁 Category Management</h3>

            <!-- Add New Category Form -->
            <div class="new-category-form">
                <h4>➕ Add New Category</h4>
                <form action="" method="POST">
                    <div class="category-input-group">
                        <input type="text" name="new_category" placeholder="Enter new category name (e.g., Desserts, Snacks, Drinks...)" required />
                        <button type="submit" name="add_category" class="btn-add-category">Add Category</button>
                    </div>
                </form>
            </div>

            <!-- Existing Categories -->
            <div class="existing-categories">
                <strong>📋 Existing Categories:</strong>
                <?php if (!empty($existing_categories)): ?>
                    <?php foreach ($existing_categories as $cat): ?>
                        <span class="category-badge"><?php echo htmlspecialchars($cat); ?></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #999; font-style: italic; margin: 10px 0 0 0;">No categories yet. Add your first category above!</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Product Form -->
        <form action="" method="POST" enctype="multipart/form-data">
            <label>Category:</label>
            <select name="category" required>
                <option value="">Select category</option>
                <?php foreach ($existing_categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>">
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Item Name:</label>
            <input type="text" name="name" required />

            <label>Price:</label>
            <input type="number" name="price" required />

            <label>Image:</label>
            <input type="file" name="image" accept="image/*" required />

            <button type="submit" name="submit_product">Insert Item</button>
        </form>
    </div>

    <script>
        // Auto-hide popup notification after 5 seconds
        setTimeout(function() {
            const popup = document.getElementById('popup');
            if (popup) {
                popup.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                popup.style.opacity = '0';
                popup.style.transform = 'translateX(400px)';
                setTimeout(() => popup.remove(), 500);
            }
        }, 5000);
    </script>
</body>

</html>