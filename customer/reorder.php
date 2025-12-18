<?php
session_start();
include '../db_connect.php';

if (!isset($_GET['id']) || !isset($_SESSION['user_id'])) {
    header('Location: view_order.php');
    exit();
}

$order_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Check order ownership
$check = $conn->query("SELECT order_id FROM `order` WHERE order_id = $order_id AND user_id = $user_id");
if ($check->num_rows == 0) die("Unauthorized");

// Get Items
$items = $conn->query("SELECT product_id, quantity FROM order_item WHERE order_id = $order_id");

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

while ($row = $items->fetch_assoc()) {
    $pid = $row['product_id'];
    $qty = $row['quantity'];

    // Get current product details (to ensure price is current and stock exists)
    $prod = $conn->query("SELECT Name, price, stock_quantity, image_path FROM product WHERE product_id = $pid")->fetch_assoc();

    if ($prod && $prod['stock_quantity'] >= $qty) {
        // Add to session cart logic (similar to add_cart.php)
        $found = false;
        foreach ($_SESSION['cart'] as &$cart_item) {
            if ($cart_item['product_id'] == $pid) {
                $cart_item['quantity'] += $qty;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $_SESSION['cart'][] = [
                'product_id' => $pid,
                'name' => $prod['Name'],
                'price' => $prod['price'],
                'image' => $prod['image_path'],
                'quantity' => $qty
            ];
        }
    }
}

header('Location: checkout.php');
exit();
?>