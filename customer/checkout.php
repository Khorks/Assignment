<?php
session_start();
include '../db_connect.php';

// 1. Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$cart = $_SESSION['cart'] ?? [];

// 2. CRITICAL FIX: Check if cart is empty. If so, redirect immediately.
if (empty($cart)) {
    echo "<script>alert('Your cart is empty! Redirecting...'); window.location.href='cart.php';</script>";
    exit();
}

// 3. Get User Details safely
$user_query = $conn->query("SELECT * FROM user WHERE user_id = $user_id");
if ($user_query->num_rows === 0) {
    // Should not happen if logged in, but safety first
    session_destroy();
    header('Location: ../auth/login.php');
    exit();
}
$user = $user_query->fetch_assoc();

// 4. Calculate Total
$total_amount = 0;
foreach ($cart as $item) {
    $total_amount += $item['price'] * $item['quantity'];
}

// 5. Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    
    // FIXED: Map payment methods to database enum values
    $payment_method_db = $payment_method;
    switch($payment_method) {
        case 'card':
            $payment_method_db = 'credit_card';
            break;
        case 'ewallet':
            $payment_method_db = 'e-wallet';
            break;
        case 'cash':
            $payment_method_db = 'cash';
            break;
    }
    
    // --- Validation Logic ---
    if ($payment_method == 'card') {
        $card_number = trim($_POST['card_number']);
        $card_name = trim($_POST['card_name']);
        $card_expiry = trim($_POST['card_expiry']);
        $card_cvv = trim($_POST['card_cvv']);
        
        if (empty($card_name) || !preg_match("/^[a-zA-Z\s]+$/", $card_name)) {
            $error_message = "Please enter a valid cardholder name.";
        } elseif (empty($card_number) || !preg_match("/^[0-9\s]{16,19}$/", $card_number)) {
            $error_message = "Please enter a valid 16-digit card number.";
        } elseif (empty($card_expiry) || !preg_match("/^(0[1-9]|1[0-2])\/\d{2}$/", $card_expiry)) {
            $error_message = "Please enter a valid expiry date (MM/YY).";
        } elseif (empty($card_cvv) || !preg_match("/^\d{3,4}$/", $card_cvv)) {
            $error_message = "Please enter a valid CVV.";
        }
    } 
    elseif ($payment_method == 'ewallet') {
        $ewallet_phone = trim($_POST['ewallet_phone']);
        $ewallet_pin = trim($_POST['ewallet_pin']);
        
        if (empty($ewallet_phone) || !preg_match("/^01[0-9]{8,9}$/", $ewallet_phone)) {
            $error_message = "Please enter a valid Malaysian phone number.";
        } elseif (empty($ewallet_pin) || !preg_match("/^\d{6}$/", $ewallet_pin)) {
            $error_message = "Please enter a valid 6-digit PIN.";
        }
    }
    elseif (empty($payment_method)) {
        $error_message = "Please select a payment method.";
    }
    
    // --- Process Order if Valid ---
    if (!isset($error_message)) {
        
        // A. Insert Order
        $stmt = $conn->prepare("INSERT INTO `order` (user_id, total_amount, status, order_date) VALUES (?, ?, 'pending', NOW())");
        $stmt->bind_param("id", $user_id, $total_amount);
        
        if ($stmt->execute()) {
            $order_id = $stmt->insert_id;
            
            // B. Insert Order Items & Deduct Stock
            $stmt_item = $conn->prepare("INSERT INTO order_item (order_id, product_id, quantity, price_each, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stmt_stock = $conn->prepare("UPDATE product SET stock_quantity = stock_quantity - ? WHERE product_id = ?");
            
            foreach ($cart as $item) {
                $item_subtotal = $item['price'] * $item['quantity'];
                
                $stmt_item->bind_param("iiidd", $order_id, $item['product_id'], $item['quantity'], $item['price'], $item_subtotal);
                $stmt_item->execute();
                
                $stmt_stock->bind_param("ii", $item['quantity'], $item['product_id']);
                $stmt_stock->execute();
            }
            
            // C. Create Payment Record - FIXED: Use correct payment_status and payment_method
            $pay_status = ($payment_method == 'cash') ? 'pending' : 'completed';
            $stmt_pay = $conn->prepare("INSERT INTO payment (order_id, amount, payment_method, payment_status, payment_date) VALUES (?, ?, ?, ?, NOW())");
            $stmt_pay->bind_param("idss", $order_id, $total_amount, $payment_method_db, $pay_status);
            $stmt_pay->execute();
            
            // D. Create Delivery Record
            $stmt_del = $conn->prepare("INSERT INTO delivery (order_id, delivery_status, delivery_address, created_at) VALUES (?, 'pending', ?, NOW())");
            $stmt_del->bind_param("is", $order_id, $address);
            $stmt_del->execute();
            
            // E. Clear Cart
            unset($_SESSION['cart']);
            echo "<script>alert('Order placed successfully!'); window.location.href='view_order.php?id=$order_id';</script>";
            exit();
        } else {
            $error_message = "Database error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - The Drink Lab</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .checkout-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px; }
        .order-summary { background: #f8f9fa; padding: 25px; border-radius: 8px; border: 1px solid #eee; }
        .summary-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e0e0e0; color: #333; }
        .summary-total { font-size: 1.3rem; font-weight: 700; color: #1e3c72; margin-top: 15px; padding-top: 15px; border-top: 2px solid #1e3c72; }
        .payment-details { background: #f0f8ff; padding: 20px; border-radius: 8px; margin-top: 15px; display: none; border: 1px solid #cce5ff; }
        .payment-details.active { display: block; }
        .payment-icon { font-size: 1.5rem; margin-right: 8px; }
        .error-field { border: 2px solid #dc3545 !important; }
        .info-section p { margin-bottom: 5px; color: #555; }
        .btn-container { margin-top: 20px; display: flex; gap: 10px; }
        
        @media (max-width: 768px) {
            .checkout-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="gradient-bg">
    
    <div class="container">
        <div class="product-form-card" style="background: white; padding: 2rem; border-radius: 15px; margin: 2rem auto; max-width: 1100px;">
            <h1 style="color: #1e3c72; border-bottom: 2px solid #eee; padding-bottom: 15px;">Checkout</h1>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error" style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="checkout-grid">
                <!-- LEFT COLUMN: Input Forms -->
                <div>
                    <h3 style="color: #1e3c72;">Delivery Information</h3>
                    <div class="info-section" style="background: #f9fafb; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($user['Name']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone_number']); ?></p>
                    </div>
                    
                    <form method="POST" id="checkoutForm" onsubmit="return validateForm()">
                        <!-- Address Field -->
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="font-weight:bold; color:#1e3c72;">Delivery Address <span class="required">*</span></label>
                            <textarea name="address" rows="3" required class="form-control" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:5px;"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>

                        <h3 style="margin-top: 30px; color: #1e3c72;">Payment Method</h3>
                        <div class="form-group">
                            <select name="payment_method" id="payment_method" required onchange="showPaymentDetails()" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:5px;">
                                <option value="">Select Payment Method</option>
                                <option value="cash">💵 Cash on Delivery</option>
                                <option value="card">💳 Credit/Debit Card</option>
                                <option value="ewallet">📱 E-Wallet (TNG/GrabPay)</option>
                            </select>
                        </div>

                        <!-- Cash on Delivery -->
                        <div id="cash-details" class="payment-details">
                            <p style="color: #28a745; font-weight: 600;">✓ Pay when you receive your order</p>
                            <p style="font-size: 0.9rem; color: #666;">Please prepare exact amount for delivery.</p>
                        </div>

                        <!-- Credit/Debit Card -->
                        <div id="card-details" class="payment-details">
                            <h4><span class="payment-icon">💳</span>Card Details</h4>
                            <div class="form-group">
                                <label>Cardholder Name <span class="required">*</span></label>
                                <input type="text" name="card_name" id="card_name" placeholder="John Doe" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                            </div>
                            <div class="form-group">
                                <label>Card Number <span class="required">*</span></label>
                                <input type="text" name="card_number" id="card_number" placeholder="1234 5678 9012 3456" maxlength="19" oninput="formatCardNumber(this)" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div class="form-group">
                                    <label>Expiry Date <span class="required">*</span></label>
                                    <input type="text" name="card_expiry" id="card_expiry" placeholder="MM/YY" maxlength="5" oninput="formatExpiry(this)" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                                </div>
                                <div class="form-group">
                                    <label>CVV <span class="required">*</span></label>
                                    <input type="text" name="card_cvv" id="card_cvv" placeholder="123" maxlength="4" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                                </div>
                            </div>
                        </div>

                        <!-- E-Wallet -->
                        <div id="ewallet-details" class="payment-details">
                            <h4><span class="payment-icon">📱</span>E-Wallet Details</h4>
                            <div class="form-group">
                                <label>Phone Number <span class="required">*</span></label>
                                <input type="tel" name="ewallet_phone" id="ewallet_phone" placeholder="0123456789" maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                            </div>
                            <div class="form-group">
                                <label>6-Digit PIN <span class="required">*</span></label>
                                <input type="password" name="ewallet_pin" id="ewallet_pin" placeholder="••••••" maxlength="6" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                            </div>
                        </div>
                        
                        <div class="btn-container">
                            <button type="submit" class="btn btn-primary" style="background:#1e3c72; color:white; padding:10px 20px; border:none; border-radius:5px; cursor:pointer; font-size:1rem; width:100%;">Place Order</button>
                        </div>
                        <div style="margin-top:10px; text-align:center;">
                            <a href="cart.php" style="color:#666; text-decoration:none;">Cancel & Return to Cart</a>
                        </div>
                    </form>
                </div>
                
                <!-- RIGHT COLUMN: Summary -->
                <div>
                    <h3 style="color: #1e3c72;">Order Summary</h3>
                    <div class="order-summary">
                        <?php foreach ($cart as $item): 
                            $item_total = $item['price'] * $item['quantity'];
                        ?>
                        <div class="summary-item">
                            <div>
                                <strong><?php echo htmlspecialchars($item['name']); ?></strong> x <?php echo $item['quantity']; ?>
                            </div>
                            <span>RM <?php echo number_format($item_total, 2); ?></span>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="summary-total">
                            <div style="display: flex; justify-content: space-between;">
                                <span>Total Amount:</span>
                                <span id="total">RM <?php echo number_format($total_amount, 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function formatCardNumber(input) {
            let value = input.value.replace(/\s/g, '').replace(/[^0-9]/g, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            input.value = formattedValue;
        }
        
        function formatExpiry(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            input.value = value;
        }
        
        function showPaymentDetails() {
            const method = document.getElementById('payment_method').value;
            document.querySelectorAll('.payment-details').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.payment-details input').forEach(input => {
                input.required = false;
                input.classList.remove('error-field');
            });
            
            if (method === 'cash') document.getElementById('cash-details').classList.add('active');
            else if (method === 'card') {
                document.getElementById('card-details').classList.add('active');
                document.getElementById('card_name').required = true;
                document.getElementById('card_number').required = true;
                document.getElementById('card_expiry').required = true;
                document.getElementById('card_cvv').required = true;
            } else if (method === 'ewallet') {
                document.getElementById('ewallet-details').classList.add('active');
                document.getElementById('ewallet_phone').required = true;
                document.getElementById('ewallet_pin').required = true;
            }
        }
        
        function validateForm() {
            const method = document.getElementById('payment_method').value;
            let isValid = true;
            
            if (method === 'card') {
                const cardName = document.getElementById('card_name');
                const cardNumber = document.getElementById('card_number');
                const cardExpiry = document.getElementById('card_expiry');
                const cardCvv = document.getElementById('card_cvv');
                
                document.querySelectorAll('.error-field').forEach(el => el.classList.remove('error-field'));
                
                if (!/^[a-zA-Z\s]+$/.test(cardName.value.trim())) { cardName.classList.add('error-field'); isValid = false; }
                if (!/^\d{16}$/.test(cardNumber.value.replace(/\s/g, ''))) { cardNumber.classList.add('error-field'); isValid = false; }
                if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(cardExpiry.value)) { cardExpiry.classList.add('error-field'); isValid = false; }
                if (!/^\d{3,4}$/.test(cardCvv.value)) { cardCvv.classList.add('error-field'); isValid = false; }
                
                if (!isValid) alert('Please check your card details.');
            } 
            else if (method === 'ewallet') {
                const phone = document.getElementById('ewallet_phone');
                const pin = document.getElementById('ewallet_pin');
                
                if (!/^01[0-9]{8,9}$/.test(phone.value)) { phone.classList.add('error-field'); isValid = false; }
                if (!/^\d{6}$/.test(pin.value)) { pin.classList.add('error-field'); isValid = false; }
                
                if (!isValid) alert('Please check your e-wallet details.');
            }
            return isValid;
        }
        
        <?php if (isset($_POST['payment_method'])): ?>
        document.getElementById('payment_method').value = '<?php echo $_POST['payment_method']; ?>';
        showPaymentDetails();
        <?php endif; ?>
    </script>
</body>
</html>