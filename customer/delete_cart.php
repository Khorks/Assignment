<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

unset($_SESSION['cart']);
echo json_encode(['success' => true, 'message' => 'Cart cleared', 'cart_count' => 0]);
exit;
