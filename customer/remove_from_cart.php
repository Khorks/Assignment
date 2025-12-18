<?php
session_start();
header('Content-Type: application/json');

$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$success = false;

if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['product_id'] == $product_id) {
            unset($_SESSION['cart'][$key]);
            $success = true;
            break;
        }
    }
    // Re-index array
    $_SESSION['cart'] = array_values($_SESSION['cart']);
}

echo json_encode(['success' => $success]);
?>