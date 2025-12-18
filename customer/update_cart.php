<?php
session_start();
header('Content-Type: application/json');

// We receive the Array Index (0, 1, 2...) instead of Product ID
// This ensures we target the specific customized item
$index = isset($_POST['index']) ? intval($_POST['index']) : -1;
$action = isset($_POST['action']) ? $_POST['action'] : 'update_qty'; // default action

// Check if the item exists in the cart
if (!isset($_SESSION['cart']) || !isset($_SESSION['cart'][$index])) {
    echo json_encode(['success' => false, 'message' => 'Item not found in cart']);
    exit;
}

// 1. UPDATE QUANTITY
if ($action === 'update_qty') {
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    if ($quantity < 1) $quantity = 1;
    
    $_SESSION['cart'][$index]['quantity'] = $quantity;
} 

// 2. UPDATE CUSTOMIZATION (Edit Modal)
elseif ($action === 'update_customization') {
    $temp = $_POST['temp'];
    $sugar = $_POST['sugar'];
    
    // Update the customization string for this specific item
    $_SESSION['cart'][$index]['customization'] = "Temp: $temp, Sugar: $sugar";
} 

// 3. REMOVE ITEM
elseif ($action === 'remove') {
    unset($_SESSION['cart'][$index]);
    // Re-index the array so keys are consecutive (0, 1, 2...) again
    $_SESSION['cart'] = array_values($_SESSION['cart']);
}

// Recalculate Cart Total
$total = 0;
foreach ($_SESSION['cart'] as $item) {
    $total += $item['price'] * $item['quantity'];
}

echo json_encode(['success' => true, 'new_total' => number_format($total, 2)]);
?>