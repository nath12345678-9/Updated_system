<?php
session_start();

if (isset($_POST['cash'])) {
    include 'db/db_connect.php';

    // ccompute total directly from the cart
    $total = 0;
    foreach ($_SESSION['cart'] as $item) {
        $total += $item['price'];
    }

    // insert sale and get the new sale IDd
    $conn->query("INSERT INTO sales (total) VALUES ($total)");
    $sale_id = $conn->insert_id;

    foreach ($_SESSION['cart'] as $item) {
        $productName = $item['name'];
        $price = $item['price'];

        $result = $conn->query("SELECT id FROM products WHERE name='$productName' LIMIT 1");
        if ($row = $result->fetch_assoc()) {
            $product_id = $row['id'];

            $conn->query("INSERT INTO sale_items (sale_id, product_id, price) VALUES ($sale_id, $product_id, $price)");
        }
    }

    $_SESSION['last_sale_id'] = $sale_id;

    // clear
    $_SESSION['cart'] = [];
    header('Location: index.php?success=1');
    exit;
}
