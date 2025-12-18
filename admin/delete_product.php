<?php
session_start();
include '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: view_product.php'); // FIXED
    exit();
}

$product_id = intval($_GET['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $sql = "UPDATE product SET is_deleted = 1, updated_at = NOW() WHERE product_id = $product_id";
    
    if ($conn->query($sql)) {
        $_SESSION['success_message'] = "Product deleted successfully!";
        header('Location: view_product.php'); // FIXED
        exit();
    } else {
        $_SESSION['error_message'] = "Error deleting product!";
        header('Location: view_product.php'); // FIXED
        exit();
    }
}

$product = $conn->query("SELECT * FROM product WHERE product_id = $product_id AND is_deleted = 0")->fetch_assoc();

if (!$product) {
    $_SESSION['error_message'] = "Product not found!";
    header('Location: view_product.php'); // FIXED
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Product</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body class="gradient-bg">
    <div class="user-detail-container">
        <div class="page-header">
            <div>
                <h1>🗑️ Delete Product</h1>
                <p>Confirm product deletion</p>
            </div>
            <div>
                <a href="view_product.php" class="btn btn-secondary">← Cancel</a> <!-- FIXED -->
            </div>
        </div>

        <div class="user-detail-card">
            <div class="delete-confirmation">
                <div class="warning-icon">⚠️</div>
                <h2>Are you sure you want to delete this product?</h2>
                <p>This will soft-delete the product from your inventory.</p>
                
                <div class="user-info">
                    <h3>Product Information</h3>
                    <div class="info-grid" style="margin-top: 1rem;">
                        <div class="info-item">
                            <div class="info-label">Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($product['Name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Price</div>
                            <div class="info-value">RM <?php echo number_format($product['price'], 2); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Stock</div>
                            <div class="info-value"><?php echo $product['stock_quantity']; ?> units</div>
                        </div>
                    </div>
                </div>

                <form method="POST">
                    <div class="button-group">
                        <button type="submit" name="confirm_delete" class="btn btn-danger">
                            🗑️ Yes, Delete Product
                        </button>
                        <a href="view_product.php" class="btn btn-secondary">Cancel</a> <!-- FIXED -->
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>