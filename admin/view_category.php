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

// Check if viewing single category
$view_single = isset($_GET['id']) && !empty($_GET['id']);
$category_id = $view_single ? intval($_GET['id']) : 0;

if ($view_single) {
    // SINGLE CATEGORY VIEW
    $category = $conn->query("SELECT * FROM category WHERE category_id = $category_id")->fetch_assoc();
    
    if (!$category) {
        $_SESSION['error_message'] = "Category not found!";
        header('Location: view_category.php');
        exit();
    }
    
    // Get products in this category
    $products_query = "SELECT * FROM product 
                       WHERE category_id = $category_id AND is_deleted = 0 
                       ORDER BY Name";
    $products = $conn->query($products_query);
    
    // Get statistics
    $stats_query = "SELECT 
                    COUNT(*) as product_count,
                    SUM(stock_quantity) as total_stock,
                    AVG(price) as avg_price
                    FROM product 
                    WHERE category_id = $category_id AND is_deleted = 0";
    $stats = $conn->query($stats_query)->fetch_assoc();
} else {
    // LIST ALL CATEGORIES
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
    $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
    
    $query = "SELECT c.*, 
              (SELECT COUNT(*) FROM product p WHERE p.category_id = c.category_id AND p.is_deleted = 0) as product_count
              FROM category c 
              WHERE 1=1";
    
    if (!empty($search)) {
        $query .= " AND c.category_name LIKE '%$search%'";
    }
    
    if ($status_filter === 'active') {
        $query .= " AND c.is_active = 1";
    } elseif ($status_filter === 'inactive') {
        $query .= " AND c.is_active = 0";
    }
    
    $query .= " ORDER BY c.category_name ASC";
    $categories = $conn->query($query);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $view_single ? 'View Category - ' . htmlspecialchars($category['category_name']) : 'Manage Categories'; ?> - The Drink Lab</title>
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

<?php if ($view_single): ?>
    <!-- SINGLE CATEGORY VIEW -->
    <div class="user-detail-container">
        <div class="page-header">
            <div>
                <h1>📂 <?php echo htmlspecialchars($category['category_name']); ?></h1>
                <p>Category details and products</p>
            </div>
            <div style="display: flex; gap: 1rem;">
                <a href="edit_category.php?id=<?php echo $category_id; ?>" class="btn btn-primary">✏️ Edit</a>
                <a href="view_category.php" class="btn btn-secondary">← Back</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="user-detail-card">
            <div class="user-info-section">
                <h3>Category Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Category Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($category['category_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span class="status-badge status-<?php echo $category['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $category['is_active'] ? '✓ Active' : '✗ Inactive'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Description</div>
                        <div class="info-value"><?php echo htmlspecialchars($category['description'] ?: 'No description'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Created Date</div>
                        <div class="info-value"><?php echo date('F d, Y', strtotime($category['created_at'])); ?></div>
                    </div>
                </div>
            </div>

            <div class="user-info-section">
                <h3>Statistics</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Total Products</div>
                        <div class="info-value" style="font-size: 1.5rem; color: #667eea; font-weight: bold;">
                            <?php echo $stats['product_count']; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Total Stock</div>
                        <div class="info-value" style="font-size: 1.5rem; color: #27ae60; font-weight: bold;">
                            <?php echo $stats['total_stock'] ?: 0; ?> units
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Average Price</div>
                        <div class="info-value" style="font-size: 1.5rem; color: #f39c12; font-weight: bold;">
                            RM <?php echo $stats['avg_price'] ? number_format($stats['avg_price'], 2) : '0.00'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($products->num_rows > 0): ?>
            <div class="user-info-section">
                <h3>Products in this Category</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Size</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($product = $products->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['Name']); ?></td>
                                <td>RM <?php echo number_format($product['price'], 2); ?></td>
                                <td><?php echo $product['stock_quantity']; ?> units</td>
                                <td><?php echo $product['size']; ?></td>
                                <td>
                                    <a href="edit_product.php?id=<?php echo $product['product_id']; ?>" 
                                       class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.85rem;">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="user-info-section">
                <p style="text-align: center; color: #666; padding: 2rem;">
                    No products in this category yet. 
                    <a href="add_product.php">Add a product</a>
                </p>
            </div>
            <?php endif; ?>

            <div class="action-section">
                <a href="view_category.php" class="btn btn-secondary">← Back to Categories</a>
                <a href="edit_category.php?id=<?php echo $category_id; ?>" class="btn btn-primary">✏️ Edit Category</a>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- LIST ALL CATEGORIES -->
    <div class="users-container">
        <div class="page-header">
            <div>
                <h1>📂 Manage Categories</h1>
                <p>View and manage product categories</p>
            </div>
            <div style="display: flex; gap: 1rem;">
                <a href="add_category.php" class="btn btn-success">➕ Add New Category</a>
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
                <!-- CHANGED: Updated grid layout to 2fr 1fr 1fr -->
                <div class="filter-row" style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 20px; align-items: end;">
                    <div class="filter-group">
                        <label for="search">🔍 Search Categories</label>
                        <input type="text" name="search" id="search" 
                               placeholder="Search by name..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="status">📊 Filter by Status</label>
                        <select name="status" id="status">
                            <option value="all" <?php echo ($status_filter == 'all') ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <!-- CHANGED: Updated Buttons Section -->
                    <div class="filter-group">
                        <div class="filter-buttons">
                            <button type="submit" class="btn-primary filter-btn-shared">
                                🔬 Filter
                            </button>
                            <a href="view_category.php" class="btn-secondary filter-btn-shared">
                                Clear
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Categories Table -->
        <div class="content-card">
            <h2>All Categories (<?php echo $categories->num_rows; ?>)</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Category Name</th>
                            <th>Description</th>
                            <th>Products</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($categories->num_rows > 0): ?>
                            <?php while ($category = $categories->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $category['category_id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($category['category_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars(substr($category['description'], 0, 50)) . (strlen($category['description']) > 50 ? '...' : ''); ?></td>
                                    <td>
                                        <?php if ($category['product_count'] > 0): ?>
                                            <a href="view_product.php?category=<?php echo $category['category_id']; ?>" 
                                               style="color: #667eea; text-decoration: underline;">
                                                <?php echo $category['product_count']; ?> products
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #999;">0 products</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $category['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $category['is_active'] ? '✓ Active' : '✗ Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($category['created_at'])); ?></td>
                                    <td>
                                        <a href="view_category.php?id=<?php echo $category['category_id']; ?>" 
                                           class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.85rem;">
                                            👁️ View
                                        </a>
                                        <a href="edit_category.php?id=<?php echo $category['category_id']; ?>" 
                                           class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.85rem;">
                                            ✏️ Edit
                                        </a>
                                        <a href="delete_category.php?id=<?php echo $category['category_id']; ?>" 
                                           class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.85rem;">
                                            🗑️ Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem;">
                                    No categories found. <a href="add_category.php">Add your first category</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

</body>
</html>
<?php $conn->close(); ?>