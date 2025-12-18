<?php
session_start();
include '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: view_category.php'); // FIXED
    exit();
}

$category_id = intval($_GET['id']);
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $products_check = "SELECT COUNT(*) as count FROM product 
                      WHERE category_id = $category_id AND is_deleted = 0";
    $products_result = $conn->query($products_check);
    $products_data = $products_result->fetch_assoc();
    
    if ($products_data['count'] > 0) {
        $_SESSION['error_message'] = "Cannot delete category with existing products!";
        header('Location: view_category.php'); // FIXED
        exit();
    }
    
    $sql = "DELETE FROM category WHERE category_id = $category_id";
    
    if ($conn->query($sql)) {
        $_SESSION['success_message'] = "Category deleted successfully!";
        header('Location: view_category.php'); // FIXED
        exit();
    } else {
        $message = "Error deleting category: " . $conn->error;
        $message_type = "error";
    }
}

$category = $conn->query("SELECT * FROM category WHERE category_id = $category_id")->fetch_assoc();

if (!$category) {
    $_SESSION['error_message'] = "Category not found!";
    header('Location: view_category.php'); // FIXED
    exit();
}

$product_count = $conn->query("SELECT COUNT(*) as count FROM product 
                               WHERE category_id = $category_id AND is_deleted = 0")
                      ->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Category - The Drink Lab</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body class="gradient-bg">
    <div class="user-detail-container">
        <div class="page-header">
            <div>
                <h1>🗑️ Delete Category</h1>
                <p>Confirm category deletion</p>
            </div>
            <div>
                <a href="view_category.php" class="btn btn-secondary">← Cancel</a> <!-- FIXED -->
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="user-detail-card">
            <div class="delete-confirmation">
                <?php if ($product_count > 0): ?>
                    <div class="warning-icon">⛔</div>
                    <h2>Cannot Delete This Category</h2>
                    <p style="color: #e74c3c;">
                        This category has <?php echo $product_count; ?> active product(s). 
                        You must reassign or delete all products before deleting this category.
                    </p>
                    
                    <div class="user-info">
                        <h3>Category Information</h3>
                        <div class="info-grid" style="margin-top: 1rem;">
                            <div class="info-item">
                                <div class="info-label">Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($category['category_name']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Active Products</div>
                                <div class="info-value"><?php echo $product_count; ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="button-group">
                        <a href="view_product.php?category=<?php echo $category_id; ?>" class="btn btn-primary">
                            View Products
                        </a>
                        <a href="view_category.php" class="btn btn-secondary">
                            Back to Categories
                        </a>
                    </div>
                <?php else: ?>
                    <div class="warning-icon">⚠️</div>
                    <h2>Are you sure you want to delete this category?</h2>
                    <p>This action cannot be undone. The category will be permanently removed from the system.</p>
                    
                    <div class="user-info">
                        <h3>Category Information</h3>
                        <div class="info-grid" style="margin-top: 1rem;">
                            <div class="info-item">
                                <div class="info-label">Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($category['category_name']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Description</div>
                                <div class="info-value"><?php echo htmlspecialchars($category['description'] ?: 'None'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Status</div>
                                <div class="info-value">
                                    <span class="status-badge status-<?php echo $category['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $category['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Products</div>
                                <div class="info-value">0 (Safe to delete)</div>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="">
                        <div class="button-group">
                            <button type="submit" name="confirm_delete" class="btn btn-danger">
                                🗑️ Yes, Delete Category
                            </button>
                            <a href="view_category.php" class="btn btn-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>