<?php
session_start();
include '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$message = '';
$message_type = '';

if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $message = $_SESSION['error_message'];
    $message_type = 'error';
    unset($_SESSION['error_message']);
}

// Get filters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;

$query = "SELECT p.*, c.category_name 
          FROM product p 
          LEFT JOIN category c ON p.category_id = c.category_id 
          WHERE p.is_deleted = 0";

if (!empty($search)) {
    $query .= " AND p.Name LIKE '%$search%'";
}

if ($category_filter > 0) {
    $query .= " AND p.category_id = $category_filter";
}

$query .= " ORDER BY p.Name ASC";
$products = $conn->query($query);

// Get categories for filter
$categories = $conn->query("SELECT * FROM category WHERE is_active = 1 ORDER BY category_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - The Drink Lab</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        /* Force equal height and alignment for filter buttons */
        .filter-buttons {
            display: flex;
            gap: 10px;
            height: 100%;
            align-items: flex-end;
        }
        
        .filter-btn-shared {
            flex: 1;
            padding: 0.9rem;
            height: 52px; /* Standard input height */
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="gradient-bg">
    <div class="users-container">
        <div class="page-header">
            <div>
                <h1>🥤 Manage Products</h1>
                <p>View and manage all products</p>
            </div>
            <div style="display: flex; gap: 1rem;">
                <a href="add_product.php" class="btn btn-success">➕ Add New Product</a>
                <a href="../admin_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" action="">
                <!-- CHANGED: Updated grid columns to 2fr 1fr 1fr for balanced layout -->
                <div class="filter-row" style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 20px; align-items: end;">
                    <div class="filter-group">
                        <label for="search">🔍 Search Products</label>
                        <input type="text" name="search" id="search" 
                               placeholder="Search by name..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="category">📁 Filter by Category</label>
                        <select name="category" id="category">
                            <option value="0">All Categories</option>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?php echo $cat['category_id']; ?>"
                                        <?php echo ($category_filter == $cat['category_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <!-- CHANGED: Updated Buttons Section -->
                    <div class="filter-group">
                        <div class="filter-buttons">
                            <button type="submit" class="btn-primary filter-btn-shared">
                                🔬 Filter
                            </button>
                            <a href="view_product.php" class="btn-secondary filter-btn-shared">
                                Clear
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Products Table -->
        <div class="content-card">
            <h2>All Products (<?php echo $products->num_rows; ?>)</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Size</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($products->num_rows > 0): ?>
                            <?php while ($product = $products->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $product['product_id']; ?></td>
                                    <td><?php echo htmlspecialchars($product['Name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                    <td>RM <?php echo number_format($product['price'], 2); ?></td>
                                    <td>
                                        <?php if ($product['stock_quantity'] == 0): ?>
                                            <span class="status-badge status-inactive">Out of Stock</span>
                                        <?php elseif ($product['stock_quantity'] < 20): ?>
                                            <span class="status-badge status-pending"><?php echo $product['stock_quantity']; ?> units</span>
                                        <?php else: ?>
                                            <span class="status-badge status-active"><?php echo $product['stock_quantity']; ?> units</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $product['size']; ?></td>
                                    <td>
                                        <a href="edit_product.php?id=<?php echo $product['product_id']; ?>" 
                                           class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.85rem;">
                                            ✏️ Edit
                                        </a>
                                        <a href="delete_product.php?id=<?php echo $product['product_id']; ?>" 
                                           class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.85rem;">
                                            🗑️ Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem;">
                                    No products found. <a href="add_product.php">Add your first product</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>