<?php
session_start();
include '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: view_category.php');
    exit();
}

$category_id = intval($_GET['id']);
$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_category'])) {
        $category_name = mysqli_real_escape_string($conn, trim($_POST['category_name']));
        $description = mysqli_real_escape_string($conn, trim($_POST['description']));
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($category_name)) {
            $message = "Category name is required!";
            $message_type = "error";
        } else {
            // Check if category name already exists (excluding current)
            $check_query = "SELECT category_id FROM category 
                           WHERE category_name = '$category_name' 
                           AND category_id != $category_id";
            $check_result = $conn->query($check_query);
            
            if ($check_result->num_rows > 0) {
                $message = "Category name already exists!";
                $message_type = "error";
            } else {
                $sql = "UPDATE category SET 
                        category_name = '$category_name', 
                        description = '$description', 
                        is_active = $is_active
                        WHERE category_id = $category_id";
                
                if ($conn->query($sql)) {
                    $message = "Category updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating category: " . $conn->error;
                    $message_type = "error";
                }
            }
        }
    } elseif (isset($_POST['delete_category'])) {
        // Check if category has products
        $products_check = "SELECT COUNT(*) as count FROM product 
                          WHERE category_id = $category_id AND is_deleted = 0";
        $products_result = $conn->query($products_check);
        $products_data = $products_result->fetch_assoc();
        
        if ($products_data['count'] > 0) {
            $message = "Cannot delete category with existing products! Please reassign or delete products first.";
            $message_type = "error";
        } else {
            $sql = "DELETE FROM category WHERE category_id = $category_id";
            if ($conn->query($sql)) {
                $_SESSION['success_message'] = "Category deleted successfully!";
                header('Location: view_category.php');
                exit();
            } else {
                $message = "Error deleting category: " . $conn->error;
                $message_type = "error";
            }
        }
    }
}

// Fetch category details
$category_query = "SELECT * FROM category WHERE category_id = $category_id";
$result = $conn->query($category_query);

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Category not found!";
    header('Location: view_category.php');
    exit();
}

$category = $result->fetch_assoc();

// Get product count
$product_count_query = "SELECT COUNT(*) as count FROM product 
                        WHERE category_id = $category_id AND is_deleted = 0";
$product_count = $conn->query($product_count_query)->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Category - The Drink Lab</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body class="gradient-bg">
    <div class="edit-user-container">
        <div class="page-header">
            <div>
                <h1>✏️ Edit Category</h1>
                <p><?php echo htmlspecialchars($category['category_name']); ?></p>
            </div>
            <div>
                <a href="view_category.php" class="btn btn-secondary">← Back to Categories</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="edit-user-layout">
            <div class="edit-user-main">
                <div class="edit-card">
                    <div class="edit-card-header">
                        <h3>📝 Category Information</h3>
                    </div>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="category_name">Category Name <span class="required">*</span></label>
                            <input type="text" id="category_name" name="category_name" required
                                   value="<?php echo htmlspecialchars($category['category_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($category['description']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="is_active" name="is_active" 
                                       <?php echo $category['is_active'] ? 'checked' : ''; ?>>
                                <label for="is_active">Active Category</label>
                            </div>
                        </div>
                        <button type="submit" name="update_category" class="btn btn-primary btn-block">
                            💾 Save Changes
                        </button>
                    </form>
                </div>

                <div class="edit-card">
                    <div class="edit-card-header">
                        <h3>🗑️ Danger Zone</h3>
                    </div>
                    <form method="POST" action="">
                        <?php if ($product_count > 0): ?>
                            <p style="color: #e74c3c; margin-bottom: 1rem;">
                                ⚠️ This category has <?php echo $product_count; ?> active product(s). 
                                You must reassign or delete all products before deleting this category.
                            </p>
                            <button type="button" class="btn btn-danger btn-block" disabled>
                                🗑️ Cannot Delete (Has Products)
                            </button>
                        <?php else: ?>
                            <p style="color: #666; margin-bottom: 1rem;">
                                Deleting this category will permanently remove it from the system.
                            </p>
                            <button type="submit" name="delete_category" class="btn btn-danger btn-block"
                                    onclick="return confirm('Are you sure you want to delete this category? This action cannot be undone.')">
                                🗑️ Delete Category
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="edit-user-sidebar">
                <div class="edit-card stats-card">
                    <div class="edit-card-header">
                        <h3>📊 Category Statistics</h3>
                    </div>
                    <div class="stats-big">
                        <div class="stat-big">
                            <div class="stat-big-value"><?php echo $product_count; ?></div>
                            <div class="stat-big-label">Active Products</div>
                        </div>
                        <div class="stat-big">
                            <div class="stat-big-value">
                                <span class="status-badge status-<?php echo $category['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $category['is_active'] ? '✓ Active' : '✗ Inactive'; ?>
                                </span>
                            </div>
                            <div class="stat-big-label">Status</div>
                        </div>
                        <div class="stat-big">
                            <div class="stat-big-value"><?php echo date('M d, Y', strtotime($category['created_at'])); ?></div>
                            <div class="stat-big-label">Created Date</div>
                        </div>
                    </div>
                </div>

                <?php if ($product_count > 0): ?>
                <div class="edit-card">
                    <div class="edit-card-header">
                        <h3>🥤 Products in Category</h3>
                    </div>
                    <div style="padding: 1rem;">
                        <a href="view_product.php?category=<?php echo $category_id; ?>" class="btn btn-primary btn-block">
                            View All Products
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
