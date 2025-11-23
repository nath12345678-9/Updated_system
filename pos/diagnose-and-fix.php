<?php
// Complete Database Diagnostic and Fix Tool
include 'db/db_connect.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Diagnostic & Fix - Pastelaria Portuguesa</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 30px auto; padding: 20px; background: #f5f5f5; }
        h1 { color: #0066ff; }
        .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { background: #d4edda; color: #155724; padding: 12px; margin: 8px 0; border-radius: 4px; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; margin: 8px 0; border-radius: 4px; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; color: #856404; padding: 12px; margin: 8px 0; border-radius: 4px; border-left: 4px solid #ffc107; }
        .info { background: #d1ecf1; color: #0c5460; padding: 12px; margin: 8px 0; border-radius: 4px; border-left: 4px solid #17a2b8; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .btn { display: inline-block; padding: 12px 24px; background: #0066ff; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .btn:hover { background: #0052cc; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <h1>🔍 Database Diagnostic & Fix Tool</h1>
    <p>This tool will check your database structure and fix any issues.</p>
";

$fixes_applied = [];
$errors = [];

// ===== STEP 1: Check Database Connection =====
echo "<div class='section'>";
echo "<h2>Step 1: Database Connection</h2>";
if ($conn->connect_error) {
    echo "<div class='error'>✗ Connection failed: " . $conn->connect_error . "</div>";
    die("</div></body></html>");
} else {
    echo "<div class='success'>✓ Database connection successful</div>";
}
echo "</div>";

// ===== STEP 2: Check and Create Tables =====
echo "<div class='section'>";
echo "<h2>Step 2: Check Required Tables</h2>";

// Check if sales table exists
$sales_exists = $conn->query("SHOW TABLES LIKE 'sales'")->num_rows > 0;
echo $sales_exists ? "<div class='success'>✓ Table 'sales' exists</div>" : "<div class='error'>✗ Table 'sales' missing</div>";

// Check if sale_items table exists
$sale_items_exists = $conn->query("SHOW TABLES LIKE 'sale_items'")->num_rows > 0;

if (!$sale_items_exists) {
    echo "<div class='warning'>⚠ Table 'sale_items' does not exist. Creating it now...</div>";
    
    $create_sale_items = "CREATE TABLE sale_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($create_sale_items)) {
        echo "<div class='success'>✓ Table 'sale_items' created successfully</div>";
        $fixes_applied[] = "Created sale_items table";
    } else {
        echo "<div class='error'>✗ Failed to create sale_items table: " . $conn->error . "</div>";
        $errors[] = "Failed to create sale_items table";
    }
} else {
    echo "<div class='success'>✓ Table 'sale_items' exists</div>";
}

echo "</div>";

// ===== STEP 3: Check and Add Columns to Sales Table =====
echo "<div class='section'>";
echo "<h2>Step 3: Update Sales Table Structure</h2>";

$required_columns = [
    'payment_method' => "VARCHAR(50) DEFAULT 'Cash'",
    'payment_status' => "VARCHAR(20) DEFAULT 'completed'",
    'gcash_reference' => "VARCHAR(100) NULL",
    'gcash_sender_name' => "VARCHAR(100) NULL",
    'gcash_sender_number' => "VARCHAR(20) NULL",
    'payment_submitted_at' => "DATETIME NULL",
    'payment_confirmed_at' => "DATETIME NULL"
];

foreach ($required_columns as $column => $definition) {
    $check = $conn->query("SHOW COLUMNS FROM sales LIKE '$column'");
    
    if ($check->num_rows == 0) {
        $sql = "ALTER TABLE sales ADD COLUMN $column $definition";
        if ($conn->query($sql)) {
            echo "<div class='success'>✓ Added column: <code>$column</code></div>";
            $fixes_applied[] = "Added column: $column";
        } else {
            echo "<div class='error'>✗ Failed to add column <code>$column</code>: " . $conn->error . "</div>";
            $errors[] = "Failed to add column: $column";
        }
    } else {
        echo "<div class='info'>ℹ Column <code>$column</code> already exists</div>";
    }
}

echo "</div>";

// ===== STEP 4: Create Admin Notifications Table =====
echo "<div class='section'>";
echo "<h2>Step 4: Admin Notifications Table</h2>";

$admin_notif_exists = $conn->query("SHOW TABLES LIKE 'admin_notifications'")->num_rows > 0;

if (!$admin_notif_exists) {
    echo "<div class='warning'>⚠ Creating admin_notifications table...</div>";
    
    $create_notif = "CREATE TABLE admin_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(50) DEFAULT 'gcash_payment',
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($create_notif)) {
        echo "<div class='success'>✓ admin_notifications table created</div>";
        $fixes_applied[] = "Created admin_notifications table";
    } else {
        echo "<div class='error'>✗ Failed to create admin_notifications: " . $conn->error . "</div>";
        $errors[] = "Failed to create admin_notifications";
    }
} else {
    echo "<div class='success'>✓ admin_notifications table exists</div>";
}

echo "</div>";

// ===== STEP 5: Add Indexes =====
echo "<div class='section'>";
echo "<h2>Step 5: Database Indexes</h2>";

$indexes = [
    ['table' => 'sales', 'name' => 'idx_payment_status', 'column' => 'payment_status'],
    ['table' => 'admin_notifications', 'name' => 'idx_is_read', 'column' => 'is_read'],
    ['table' => 'admin_notifications', 'name' => 'idx_created_at', 'column' => 'created_at']
];

foreach ($indexes as $index) {
    // Check if table exists first
    $table_exists = $conn->query("SHOW TABLES LIKE '{$index['table']}'")->num_rows > 0;
    
    if ($table_exists) {
        $check = $conn->query("SHOW INDEX FROM {$index['table']} WHERE Key_name = '{$index['name']}'");
        
        if ($check->num_rows == 0) {
            $sql = "CREATE INDEX {$index['name']} ON {$index['table']}({$index['column']})";
            if ($conn->query($sql)) {
                echo "<div class='success'>✓ Created index: <code>{$index['name']}</code></div>";
                $fixes_applied[] = "Created index: {$index['name']}";
            } else {
                echo "<div class='warning'>⚠ Could not create index {$index['name']}: " . $conn->error . "</div>";
            }
        } else {
            echo "<div class='info'>ℹ Index <code>{$index['name']}</code> exists</div>";
        }
    }
}

echo "</div>";

// ===== STEP 6: Display Current Structure =====
echo "<div class='section'>";
echo "<h2>Step 6: Current Database Structure</h2>";

echo "<h3>Sales Table:</h3>";
$result = $conn->query("SHOW COLUMNS FROM sales");
if ($result) {
    echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    while ($col = $result->fetch_assoc()) {
        echo "<tr><td><code>{$col['Field']}</code></td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Default']}</td></tr>";
    }
    echo "</table>";
}

if ($sale_items_exists) {
    echo "<h3>Sale Items Table:</h3>";
    $result = $conn->query("SHOW COLUMNS FROM sale_items");
    if ($result) {
        echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
        while ($col = $result->fetch_assoc()) {
            echo "<tr><td><code>{$col['Field']}</code></td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Default']}</td></tr>";
        }
        echo "</table>";
    }
}

if ($admin_notif_exists) {
    echo "<h3>Admin Notifications Table:</h3>";
    $result = $conn->query("SHOW COLUMNS FROM admin_notifications");
    if ($result) {
        echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
        while ($col = $result->fetch_assoc()) {
            echo "<tr><td><code>{$col['Field']}</code></td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Default']}</td></tr>";
        }
        echo "</table>";
    }
}

echo "</div>";

// ===== FINAL SUMMARY =====
echo "<div class='section'>";
echo "<h2>📊 Summary</h2>";

if (empty($errors)) {
    echo "<div class='success'>";
    echo "<h3>✓ All Checks Passed!</h3>";
    echo "<p>Your database is properly configured for the GCash payment system.</p>";
    
    if (!empty($fixes_applied)) {
        echo "<h4>Fixes Applied:</h4><ul>";
        foreach ($fixes_applied as $fix) {
            echo "<li>$fix</li>";
        }
        echo "</ul>";
    }
    
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Update GCash details in <code>gcash-payment.php</code> (lines 60-61)</li>";
    echo "<li>Make sure your <code>index.php</code> is updated with the latest code</li>";
    echo "<li><strong>DELETE this diagnose-and-fix.php file for security</strong></li>";
    echo "<li>Test the system by making a sale with GCash payment</li>";
    echo "</ol>";
    
    echo "<a href='index.php' class='btn'>Go to POS System</a>";
    echo "<a href='admin-notification.php' class='btn'>View Admin Panel</a>";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h3>⚠ Some Issues Found</h3>";
    echo "<h4>Errors:</h4><ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
    echo "<p>Please check the errors above and contact support if needed.</p>";
    echo "</div>";
}

echo "</div>";

echo "</body></html>";

$conn->close();
?>