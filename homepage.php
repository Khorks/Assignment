<?php
session_start();
include 'db_connect.php';

// Redirect admin/staff logic...
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') { header('Location: admin_dashboard.php'); exit(); }
    elseif ($_SESSION['role'] === 'staff') { header('Location: staff_dashboard.php'); exit(); }
}

// Categories and Products Queries
$categories = $conn->query("SELECT * FROM category WHERE is_active = TRUE ORDER BY category_name");
$selected_category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$products_query = "SELECT p.*, c.category_name FROM product p JOIN category c ON p.category_id = c.category_id WHERE p.is_deleted = FALSE";
if ($selected_category > 0) $products_query .= " AND p.category_id = $selected_category";
if (!empty($search_query)) $products_query .= " AND (p.Name LIKE '%$search_query%' OR p.description LIKE '%$search_query%')";
$products_query .= " ORDER BY p.Name";
$products = $conn->query($products_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Drink Lab</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .product-image img { width: 100%; height: 100%; object-fit: cover; }
        .product-image.no-image { display: flex; align-items: center; justify-content: center; font-size: 4rem; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 2rem; border-radius: 15px; width: 90%; max-width: 500px; position: relative; animation: slideDown 0.3s ease; }
        @keyframes slideDown { from {transform: translateY(-50px); opacity: 0;} to {transform: translateY(0); opacity: 1;} }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 2px solid #f0f4f8; padding-bottom: 1rem; }
        .close-modal { font-size: 1.5rem; cursor: pointer; color: #666; }
        .cust-option { margin-bottom: 1.5rem; }
        .cust-option label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #1e3c72; }
        .radio-group { display: flex; gap: 10px; flex-wrap: wrap; }
        .radio-label { flex: 1; text-align: center; cursor: pointer; border: 2px solid #d9e2ec; padding: 0.5rem; border-radius: 8px; transition: all 0.2s; }
        .radio-input { display: none; }
        .radio-input:checked + .radio-label { background: #e0f2fe; border-color: #00d4ff; color: #0284c7; font-weight: bold; }
        
        /* Quantity Input Style */
        .qty-input { width: 100%; padding: 10px; border: 2px solid #d9e2ec; border-radius: 8px; font-size: 1rem; text-align: center; font-weight: bold; color: #1e3c72; }
        .qty-input:focus { border-color: #00d4ff; outline: none; }
    </style>
</head>
<body class="gradient-bg">
    <!-- Header -->
    <div class="homepage-header">
        <div class="header-container">
            <div class="logo">The Drink Lab</div>
            <div class="auth-buttons">
                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'customer'): ?>
                    <span class="welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</span>
                    <a href="customer_dashboard.php" class="btn btn-secondary">👤 My Account</a>
                    <a href="customer/cart.php" class="cart-button">🛒 Cart</a>
                    <a href="auth/logout.php" class="btn btn-secondary">Logout</a>
                <?php else: ?>
                    <a href="auth/login.php" class="btn btn-secondary">Login</a>
                    <a href="auth/register.php" class="btn btn-primary">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Hero & Filters -->
        <div class="hero-section">
            <h1>🧪 Welcome to The Drink Lab</h1>
            <p>Where Science Meets Refreshment</p>
        </div>

        <?php if (!isset($_SESSION['user_id'])): ?>
            <div class="login-prompt">ℹ️ Please <a href="auth/login.php">Login</a> to order!</div>
        <?php endif; ?>

        <div class="filters-section">
            <form method="GET">
                <div class="filter-row" style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 20px; align-items: end;">
                    <div class="filter-group">
                        <label style="font-weight: 600; color: #1e3c72; display: block; margin-bottom: 5px;">🔍 Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search drinks..." style="width: 100%; padding: 10px; border: 2px solid #d9e2ec; border-radius: 8px;">
                    </div>
                    <div class="filter-group">
                        <label style="font-weight: 600; color: #1e3c72; display: block; margin-bottom: 5px;">📂 Category</label>
                        <select name="category" style="width: 100%; padding: 10px; border: 2px solid #d9e2ec; border-radius: 8px; background-color: white;">
                            <option value="0">All Categories</option>
                            <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?php echo $cat['category_id']; ?>" <?php echo ($selected_category == $cat['category_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['category_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary" style="flex: 1; padding: 10px; justify-content: center;">Filter</button>
                            <a href="homepage.php" class="btn btn-secondary" style="flex: 1; padding: 10px; text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center;">Clear</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Products -->
        <div class="products-grid">
            <?php if ($products->num_rows > 0): while ($product = $products->fetch_assoc()): ?>
                <div class="product-card">
                    <?php 
                        $display_path = $product['image_path'];
                        if (!empty($display_path) && strpos($display_path, '../') === 0) $display_path = substr($display_path, 3);
                        $has_image = !empty($display_path) && file_exists($display_path);
                    ?>
                    <div class="product-image <?php echo !$has_image ? 'no-image' : ''; ?>">
                        <?php if ($has_image): ?><img src="<?php echo htmlspecialchars($display_path); ?>"><?php else: ?>🥤<?php endif; ?>
                    </div>
                    <div class="product-details">
                        <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                        <div class="product-name"><?php echo htmlspecialchars($product['Name']); ?></div>
                        <div class="product-description"><?php echo htmlspecialchars($product['description'] ?? ''); ?></div>
                        <div class="product-footer">
                            <div class="product-price">RM <?php echo number_format($product['price'], 2); ?></div>
                            <div class="product-size"><?php echo $product['size']; ?></div>
                        </div>
                        
                        <?php if ($product['stock_quantity'] > 0): ?>
                            <!-- CHANGED: Simplified text -->
                            <button class="add-to-cart-btn" onclick="openCustomizeModal(<?php echo $product['product_id']; ?>, '<?php echo addslashes($product['Name']); ?>')">🛒 Add to Cart</button>
                        <?php else: ?>
                            <button class="add-to-cart-btn" disabled>❌ Out of Stock</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; else: ?>
                <div class="no-products"><h2>No drinks found</h2></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- CUSTOMIZATION MODAL -->
    <div id="customizeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalProductName">Customize Drink</h3>
                <span class="close-modal" onclick="closeModal()">×</span>
            </div>
            
            <input type="hidden" id="modalProductId">
            
            <!-- Temperature Options -->
            <div class="cust-option">
                <label>🌡️ Temperature</label>
                <div class="radio-group">
                    <input type="radio" name="temp" id="temp_ice" value="Normal Ice" class="radio-input" checked>
                    <label for="temp_ice" class="radio-label">Normal Ice</label>
                    <input type="radio" name="temp" id="temp_less" value="Less Ice" class="radio-input">
                    <label for="temp_less" class="radio-label">Less Ice</label>
                    <input type="radio" name="temp" id="temp_no" value="No Ice" class="radio-input">
                    <label for="temp_no" class="radio-label">No Ice</label>
                    <input type="radio" name="temp" id="temp_warm" value="Warm" class="radio-input">
                    <label for="temp_warm" class="radio-label">Warm</label>
                </div>
            </div>

            <!-- Sugar Options -->
            <div class="cust-option">
                <label>🍬 Sweetness</label>
                <div class="radio-group">
                    <input type="radio" name="sugar" id="sugar_100" value="100%" class="radio-input" checked>
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

            <!-- Quantity Input -->
            <div class="cust-option">
                <label>🔢 Quantity</label>
                <input type="number" id="modalQuantity" class="qty-input" value="1" min="1" max="100">
            </div>

            <!-- CHANGED: Removed the tick icon -->
            <button class="btn btn-primary btn-block" onclick="confirmAddToCart()">Add to Cart</button>
        </div>
    </div>

    <script>
        const modal = document.getElementById('customizeModal');

        function openCustomizeModal(id, name) {
            <?php if (!isset($_SESSION['user_id'])): ?>
                window.location.href = 'auth/login.php';
                return;
            <?php endif; ?>
            
            document.getElementById('modalProductId').value = id;
            document.getElementById('modalProductName').innerText = 'Customize: ' + name;
            
            // Reset quantity to 1 every time modal opens
            document.getElementById('modalQuantity').value = 1;
            
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        function confirmAddToCart() {
            const productId = document.getElementById('modalProductId').value;
            const temp = document.querySelector('input[name="temp"]:checked').value;
            const sugar = document.querySelector('input[name="sugar"]:checked').value;
            
            const quantity = document.getElementById('modalQuantity').value;

            fetch('customer/add_cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `product_id=${productId}&quantity=${quantity}&temp=${temp}&sugar=${sugar}`
            })
            .then(response => response.json())
            .then(data => {
                closeModal();
                if (data.success) {
                    alert('✅ Added ' + quantity + ' item(s) to cart!');
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => console.error('Error:', error));
        }

        window.onclick = function(event) {
            if (event.target == modal) closeModal();
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>