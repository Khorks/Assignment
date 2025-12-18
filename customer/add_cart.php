<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();
include '../db_connect.php'; 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$product_id = intval($_POST['product_id']);
$quantity = intval($_POST['quantity']);
// Get Customization
$temp = isset($_POST['temp']) ? $_POST['temp'] : 'Normal Ice';
$sugar = isset($_POST['sugar']) ? $_POST['sugar'] : '100%';
$customization = "Temp: $temp, Sugar: $sugar";

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit;
}

// Get Product Details
$stmt = $conn->prepare("SELECT product_id, Name, price, image_path FROM product WHERE product_id = ? AND is_deleted = 0");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}
$product = $res->fetch_assoc();

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// Check if exact product + customization exists
$found = false;
foreach ($_SESSION['cart'] as &$item) {
    // We check BOTH product_id AND customization string
    if ($item['product_id'] === $product_id && $item['customization'] === $customization) {
        $item['quantity'] += $quantity;
        $found = true;
        break;
    }
}
unset($item);

if (!$found) {
    $_SESSION['cart'][] = [
        'product_id' => $product_id,
        'name' => $product['Name'],
        'price' => floatval($product['price']),
        'quantity' => $quantity,
        'image' => $product['image_path'],
        'customization' => $customization // Store the choice
    ];
}

$cart_count = 0;
foreach ($_SESSION['cart'] as $c) $cart_count += $c['quantity'];

echo json_encode(['success' => true, 'message' => 'Added', 'cart_count' => $cart_count]);
?>