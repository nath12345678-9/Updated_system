<?php
// GCash Payment System Setup Script
// Run this file ONCE to setup the database for GCash payments

include 'db/db_connect.php';

$errors = [];
$success = [];

echo "<!DOCTYPE html>
<html>
<head>
    <title>GCash Setup - Pastelaria Portuguesa</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        h1 { color: #0066ff; }
        .success { background: #d4edda; color: #155724; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .step { background: #f8f9fa; padding: 15px; margin: 15px 0; border-left: 4px solid #0066ff; }
    </style>
</head>
<body>
    <h1>🔧 GCash Payment System Setup</h1>
    <p>This will add GCash payment support to your POS system.</p>
";

// Step 1: Add columns to sales table
echo "<div class='step'><h3>Step 1: Updating sales table...</h3>";

$columns_to_add = [
    'payment_method' => "VARCHAR(50) DEFAULT 'Cash'",
    'payment_status' => "VARCHAR(20) DEFAULT 'completed'",
    'gcash_reference' => "VARCHAR(100)",
    'gcash_sender_name' => "VARCHAR(100)",
    'gcash_sender_number' => "VARCHAR(20)",
    'payment_submitted_at' => "DATETIME",
    'payment_confirmed_at' => "DATETIME"
];

foreach ($columns_to_add as $column => $definition) {
    // Check if column exists
    $check = $conn->query("SHOW COLUMNS FROM sales LIKE '$column'");
    
    if ($check->num_rows == 0) {
        // Column doesn't exist, add it
        $sql = "ALTER TABLE sales ADD COLUMN $column $definition";
        if ($conn->query($sql)) {
            echo "<div class='success'>✓ Added column: $column</div>";
        } else {
            echo "<div class='error'>✗ Failed to add column $column: " . $conn->error . "</div>";
            $errors[] = "Failed to add column: $column";
        }
    } else {
        echo "<div class='info'>ℹ Column already exists: $column</div>";
    }
}
echo "</div>";

// Step 2: Create admin_notifications table
echo "<div class='step'><h3>Step 2: Creating admin_notifications table...</h3>";

$create_table_sql = "CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'gcash_payment',
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
)";

if ($conn->query($create_table_sql)) {
    echo "<div class='success'>✓ admin_notifications table created successfully</div>";
} else {
    if (strpos($conn->error, 'already exists') !== false) {
        echo "<div class='info'>ℹ admin_notifications table already exists</div>";
    } else {
        echo "<div class='error'>✗ Error creating table: " . $conn->error . "</div>";
        $errors[] = "Failed to create admin_notifications table";
    }
}
echo "</div>";

// Step 3: Add indexes
echo "<div class='step'><h3>Step 3: Adding database indexes...</h3>";

$indexes = [
    'sales' => [
        'idx_payment_status' => 'payment_status'
    ],
    'admin_notifications' => [
        'idx_is_read' => 'is_read',
        'idx_created_at' => 'created_at'
    ]
];

foreach ($indexes as $table => $table_indexes) {
    foreach ($table_indexes as $index_name => $column) {
        // Check if index exists
        $check = $conn->query("SHOW INDEX FROM $table WHERE Key_name = '$index_name'");
        
        if ($check->num_rows == 0) {
            $sql = "CREATE INDEX $index_name ON $table($column)";
            if ($conn->query($sql)) {
                echo "<div class='success'>✓ Created index: $index_name on $table</div>";
            } else {
                echo "<div class='error'>✗ Failed to create index $index_name: " . $conn->error . "</div>";
            }
        } else {
            echo "<div class='info'>ℹ Index already exists: $index_name</div>";
        }
    }
}
echo "</div>";

// Final status
echo "<div class='step'>";
if (empty($errors)) {
    echo "<h2 style='color: green;'>✓ Setup Complete!</h2>";
    echo "<p><strong>Your GCash payment system is ready to use.</strong></p>";
    echo "<h3>Next Steps:</h3>";
    echo "<ol>
        <li>Update your GCash details in <code>gcash-payment.php</code> (lines 60-61)</li>
        <li>Replace your <code>index.php</code> with the updated version</li>
        <li>Access admin notifications at: <a href='admin-notifications.php'>admin-notifications.php</a></li>
        <li><strong>DELETE this setup-gcash.php file for security</strong></li>
    </ol>";
    echo "<p><a href='index.php' style='display: inline-block; padding: 15px 30px; background: #0066ff; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px;'>Go to POS System</a></p>";
} else {
    echo "<h2 style='color: red;'>⚠ Setup Completed with Errors</h2>";
    echo "<p>Some steps failed. Please check the errors above.</p>";
    echo "<h3>Errors:</h3><ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
}
echo "</div>";

// Show current database structure
echo "<div class='step'>";
echo "<h3>📊 Current Database Structure:</h3>";
echo "<h4>Sales Table Columns:</h4>";
$columns = $conn->query("SHOW COLUMNS FROM sales");
echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Field</th><th>Type</th><th>Default</th></tr>";
while ($col = $columns->fetch_assoc()) {
    echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Default']}</td></tr>";
}
echo "</table>";

echo "<h4 style='margin-top: 20px;'>Admin Notifications Table:</h4>";
$check_table = $conn->query("SHOW TABLES LIKE 'admin_notifications'");
if ($check_table->num_rows > 0) {
    $columns = $conn->query("SHOW COLUMNS FROM admin_notifications");
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Default</th></tr>";
    while ($col = $columns->fetch_assoc()) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Default']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Table does not exist</p>";
}
echo "</div>";

echo "</body></html>";

$conn->close();
?>