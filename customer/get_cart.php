<?php
session_start();
include '../db_connect.php';

$cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];

if (empty($cart)) {
    echo '<div class="cart-empty">Your cart is empty.</div>';
    exit;
}

$total = 0.0;
$html = '<div class="cart-items">';
foreach ($cart as $item) {
    $subtotal = $item['price'] * $item['quantity'];
    $total += $subtotal;
    $html .= '<div class="cart-item" data-product-id="'.htmlspecialchars($item['product_id']).'">';
    $html .= '<div class="cart-item-name">'.htmlspecialchars($item['name']).'</div>';
    $html .= '<div class="cart-item-qty">Qty: '.intval($item['quantity']).'</div>';
    $html .= '<div class="cart-item-price">RM '.number_format($subtotal,2).'</div>';
    $html .= '</div>';
}
$html .= '</div>';
$html .= '<div class="cart-total">Total: RM '.number_format($total,2).'</div>';
$html .= '<div class="cart-actions"><a href="../customer/cart.php" class="btn">View Cart</a> <a href="../customer/checkout.php" class="btn">Checkout</a></div>';

echo $html;
exit;
