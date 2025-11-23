<?php
session_start();
include 'db/db_connect.php';

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['clear'])) {
    $id = intval($_POST['id']);
    $name = $_POST['name'];
    $price = floatval($_POST['price']);

    $_SESSION['cart'][] = [
        'id' => $id,
        'name' => $name,
        'price' => $price
    ];

    $_SESSION['cart_message'] = "✓ $name added to cart";
}

// Clear cart
if (isset($_POST['clear'])) {
    $_SESSION['cart'] = [];
    $_SESSION['cart_message'] = "Cart cleared";
}

header('Location: index.php');
exit;
?>