<?php
session_start();
include '../db_connect.php';

// If user clicks checkout, handle redirection
if (isset($_POST['checkout'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../auth/login.php');
    } else {
        header('Location: checkout.php');
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shopping Cart</title>
    <link rel="stylesheet" href="../styles.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Fix for Header Alignment */
        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            margin-top: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 2rem; border-radius: 15px; width: 90%; max-width: 500px; position: relative; animation: slideDown 0.3s ease; }
        @keyframes slideDown { from {transform: translateY(-50px); opacity: 0;} to {transform: translateY(0); opacity: 1;} }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 2px solid #f0f4f8; padding-bottom: 1rem; }
        .close-modal { font-size: 1.5rem; cursor: pointer; color: #666; }
        .cust-option { margin-bottom: 1.5rem; }
        .cust-option label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #1e3c72; }
        .radio-group { display: flex; gap: 10px; flex-wrap: wrap; }
        .radio-label { flex: 1; text-align: center; cursor: pointer; border: 2px solid #d9e2ec; padding: 0.5rem; border-radius: 8px; transition: all 0.2s; font-size: 0.9rem; }
        .radio-input { display: none; }
        .radio-input:checked + .radio-label { background: #e0f2fe; border-color: #00d4ff; color: #0284c7; font-weight: bold; }
        
        .edit-btn { background: #f0f9ff; color: #0284c7; border: 1px solid #bae6fd; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; cursor: pointer; margin-left: 5px; text-decoration: none; }
        .edit-btn:hover { background: #e0f2fe; }
    </style>
</head>
<body class="gradient-bg">
    <div class="container">
        <!-- Improved Header -->
        <div class="cart-header">
            <h1 style="margin: 0; color: #1e3c72;">🛒 Your Cart</h1>
            <!-- CHANGED: Link points to customer_dashboard.php and text is 'My Account' -->
            <a href="../customer_dashboard.php" class="btn btn-secondary">← My Account</a>
        </div>

        <div class="content-card">
            <div id="cart-content">
                <?php if (empty($_SESSION['cart'])): ?>
                    <div style="text-align: center; padding: 3rem;">
                        <h2 style="color: #6b7280;">Your cart is empty 🧪</h2>
                        <p style="margin-bottom: 1.5rem;">Go add some delicious experiments!</p>
                        <a href="../homepage.php" class="btn btn-primary">Browse Menu</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th width="50%">Product</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Subtotal</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total = 0;
                                // Use $key as the unique index for each cart item
                                foreach ($_SESSION['cart'] as $key => $item): 
                                    $sub = $item['price'] * $item['quantity'];
                                    $total += $sub;
                                    
                                    // Parse Customization string back to values for the modal
                                    $cust_str = $item['customization'] ?? 'Temp: Normal Ice, Sugar: 100%';
                                    preg_match('/Temp: (.*?), Sugar: (.*)/', $cust_str, $matches);
                                    $current_temp = $matches[1] ?? 'Normal Ice';
                                    $current_sugar = $matches[2] ?? '100%';
                                ?>
                                <tr id="row-<?php echo $key; ?>">
                                    <td>
                                        <div style="display:flex; align-items:center; gap:15px;">
                                            <?php 
                                                $img = $item['image'];
                                                if(strpos($img, '../') === false) $img = '../' . $img;
                                            ?>
                                            <?php if($item['image']): ?>
                                                <img src="<?php echo htmlspecialchars($img); ?>" width="60" height="60" style="object-fit:cover; border-radius:8px;">
                                            <?php endif; ?>
                                            
                                            <div>
                                                <div style="font-weight: bold; font-size: 1.1rem; color: #1e3c72;"><?php echo htmlspecialchars($item['name']); ?></div>
                                                <div style="color: #666; font-size: 0.85rem; margin-top: 4px;">
                                                    🎨 <?php echo htmlspecialchars($cust_str); ?>
                                                    <!-- Edit Button -->
                                                    <button type="button" class="edit-btn" 
                                                        onclick="openEditModal(<?php echo $key; ?>, '<?php echo addslashes($item['name']); ?>', '<?php echo $current_temp; ?>', '<?php echo $current_sugar; ?>')">
                                                        ✎ Edit
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>RM <?php echo number_format($item['price'], 2); ?></td>
                                    <td>
                                        <input type="number" min="1" value="<?php echo $item['quantity']; ?>" 
                                               onchange="updateCart(<?php echo $key; ?>, this.value)"
                                               style="width: 60px; padding: 5px; border-radius: 5px; border: 1px solid #ddd; text-align: center;">
                                    </td>
                                    <td>RM <?php echo number_format($sub, 2); ?></td>
                                    <td>
                                        <button onclick="removeFromCart(<?php echo $key; ?>)" class="btn btn-danger" style="padding: 5px 12px; font-size: 1.2rem; line-height: 1;">×</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="text-align: right; margin-top: 2rem; border-top: 2px solid #f0f0f0; padding-top: 1rem;">
                        <h2 style="color: #1e3c72;">Total: RM <?php echo number_format($total, 2); ?></h2>
                        <form method="POST" style="margin-top: 1rem;">
                            <button type="submit" name="checkout" class="btn btn-primary btn-lg">Proceed to Checkout →</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- EDIT CUSTOMIZATION MODAL -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalProductName">Edit Drink</h3>
                <span class="close-modal" onclick="closeModal()">×</span>
            </div>
            
            <input type="hidden" id="editItemIndex">
            
            <div class="cust-option">
                <label>🌡️ Temperature</label>
                <div class="radio-group">
                    <input type="radio" name="temp" id="temp_ice" value="Normal Ice" class="radio-input">
                    <label for="temp_ice" class="radio-label">Normal Ice</label>
                    
                    <input type="radio" name="temp" id="temp_less" value="Less Ice" class="radio-input">
                    <label for="temp_less" class="radio-label">Less Ice</label>
                    
                    <input type="radio" name="temp" id="temp_no" value="No Ice" class="radio-input">
                    <label for="temp_no" class="radio-label">No Ice</label>
                    
                    <input type="radio" name="temp" id="temp_warm" value="Warm" class="radio-input">
                    <label for="temp_warm" class="radio-label">Warm</label>
                </div>
            </div>

            <div class="cust-option">
                <label>🍬 Sweetness</label>
                <div class="radio-group">
                    <input type="radio" name="sugar" id="sugar_100" value="100%" class="radio-input">
                    <label for="sugar_100" class="radio-label">100%</label>
                    
                    <input type="radio" name="sugar" id="sugar_75" value="75%" class="radio-input">
                    <label for="sugar_75" class="radio-label">75%</label>
                    
                    <input type="radio" name="sugar" id="sugar_50" value="50%" class="radio-input">
                    <label for="sugar_50" class="radio-label">50%</label>
                    
                    <input type="radio" name="sugar" id="sugar_25" value="25%" class="radio-input">
                    <label for="sugar_25" class="radio-label">25%</label>
                    
                    <input type="radio" name="sugar" id="sugar_0" value="0%" class="radio-input">
                    <label for="sugar_0" class="radio-label">0%</label>
                </div>
            </div>

            <button class="btn btn-primary btn-block" onclick="saveCustomization()">💾 Update Cart</button>
        </div>
    </div>

    <script>
        const modal = document.getElementById('editModal');

        // FIXED: URL is now 'update_cart.php'
        function updateCart(index, qty) {
            $.post('update_cart.php', {index: index, quantity: qty, action: 'update_qty'}, function(res) {
                if(res.success) location.reload();
            });
        }

        // FIXED: URL is now 'update_cart.php'
        function removeFromCart(index) {
            if(confirm('Remove this item?')) {
                $.post('update_cart.php', {index: index, action: 'remove'}, function(res) {
                    if(res.success) location.reload();
                });
            }
        }

        // Open Modal and Pre-fill values
        function openEditModal(index, name, currentTemp, currentSugar) {
            document.getElementById('editItemIndex').value = index;
            document.getElementById('modalProductName').innerText = 'Edit: ' + name;
            
            // Select the radio buttons based on current values
            $('input[name="temp"][value="' + currentTemp + '"]').prop('checked', true);
            $('input[name="sugar"][value="' + currentSugar + '"]').prop('checked', true);
            
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        function saveCustomization() {
            const index = document.getElementById('editItemIndex').value;
            const temp = document.querySelector('input[name="temp"]:checked').value;
            const sugar = document.querySelector('input[name="sugar"]:checked').value;

            // FIXED: URL is now 'update_cart.php'
            $.post('update_cart.php', {
                index: index, 
                temp: temp, 
                sugar: sugar, 
                action: 'update_customization'
            }, function(res) {
                if(res.success) {
                    location.reload();
                } else {
                    alert('Error updating item');
                }
            });
        }

        // Close modal if clicking outside box
        window.onclick = function(event) {
            if (event.target == modal) closeModal();
        }
    </script>
</body>
</html>