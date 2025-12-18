<?php
session_start();
include '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: view_product.php');
    exit();
}

$product_id = intval($_GET['id']);
$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_product'])) {
        $name = mysqli_real_escape_string($conn, trim($_POST['name']));
        $description = mysqli_real_escape_string($conn, trim($_POST['description']));
        $price = floatval($_POST['price']);
        $stock = intval($_POST['stock']);
        $category_id = intval($_POST['category_id']);
        $size = mysqli_real_escape_string($conn, $_POST['size']);
        
        // Get current product data
        $current_product = $conn->query("SELECT image_path FROM product WHERE product_id = $product_id")->fetch_assoc();
        $image_path = $current_product['image_path'];
        
        // Handle new image upload
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/products/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                if ($_FILES['product_image']['size'] <= 5242880) {
                    $new_filename = 'product_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $target_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_path)) {
                        // Delete old image if exists
                        if ($image_path && file_exists($image_path)) {
                            unlink($image_path);
                        }
                        $image_path = $target_path;
                    }
                }
            }
        }
        
        // Handle remove image
        if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
            if ($image_path && file_exists($image_path)) {
                unlink($image_path);
            }
            $image_path = NULL;
        }
        
        if (empty($name)) {
            $message = "Product name is required!";
            $message_type = "error";
        } elseif ($price <= 0) {
            $message = "Price must be greater than 0!";
            $message_type = "error";
        } elseif ($stock < 0) {
            $message = "Stock quantity cannot be negative!";
            $message_type = "error";
        } else {
            $image_sql = $image_path ? "'$image_path'" : "NULL";
            $sql = "UPDATE product SET 
                    Name = '$name', 
                    description = '$description', 
                    price = $price, 
                    stock_quantity = $stock, 
                    category_id = $category_id, 
                    size = '$size',
                    image_path = $image_sql,
                    updated_at = NOW()
                    WHERE product_id = $product_id";
            
            if ($conn->query($sql)) {
                $message = "Product updated successfully!";
                $message_type = "success";
            } else {
                $message = "Error updating product: " . $conn->error;
                $message_type = "error";
            }
        }
    } elseif (isset($_POST['delete_product'])) {
        $sql = "UPDATE product SET is_deleted = 1 WHERE product_id = $product_id";
        if ($conn->query($sql)) {
            $_SESSION['success_message'] = "Product deleted successfully!";
            header('Location: view_product.php');
            exit();
        } else {
            $message = "Error deleting product: " . $conn->error;
            $message_type = "error";
        }
    }
}

// Fetch product details
$product_query = "SELECT p.*, c.category_name 
                  FROM product p 
                  JOIN category c ON p.category_id = c.category_id 
                  WHERE p.product_id = $product_id AND p.is_deleted = 0";
$result = $conn->query($product_query);

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Product not found!";
    header('Location: view_product.php');
    exit();
}

$product = $result->fetch_assoc();
$categories = $conn->query("SELECT * FROM category WHERE is_active = 1 ORDER BY category_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .image-upload-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .image-preview {
            width: 200px;
            height: 200px;
            border: 2px dashed #d9e2ec;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #f9fafb;
            position: relative;
        }
        
        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }
        
        .image-preview-placeholder {
            font-size: 4rem;
            color: #d1d5db;
        }
        
        .remove-image-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            opacity: 0.9;
            transition: all 0.3s ease;
        }
        
        .remove-image-btn:hover {
            opacity: 1;
            transform: scale(1.1);
        }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }
        
        .file-input-label {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        
        .file-input-label:hover {
            background: linear-gradient(135deg, #2a5298 0%, #1e3c72 100%);
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="gradient-bg">
    <div class="edit-user-container">
        <div class="page-header">
            <div>
                <h1>✏️ Edit Product</h1>
                <p><?php echo htmlspecialchars($product['Name']); ?></p>
            </div>
            <div>
                <a href="view_product.php" class="btn btn-secondary">← Back</a>
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
                        <h3>📝 Product Information</h3>
                    </div>
                    <form method="POST" enctype="multipart/form-data" id="productForm">
                        <input type="hidden" name="remove_image" id="removeImageInput" value="0">
                        
                        <!-- Image Upload Section -->
                        <div class="form-group">
                            <label>Product Image</label>
                            <div class="image-upload-container">
                                <div class="image-preview" id="imagePreview">
                                    <?php if ($product['image_path'] && file_exists($product['image_path'])): ?>
                                        <img src="<?php echo $product['image_path']; ?>" alt="Product Image" id="currentImage">
                                        <button type="button" class="remove-image-btn" onclick="removeImage()" title="Remove image">×</button>
                                    <?php else: ?>
                                        <span class="image-preview-placeholder">🥤</span>
                                    <?php endif; ?>
                                </div>
                                <div class="file-input-wrapper">
                                    <input type="file" name="product_image" id="productImage" accept="image/*" onchange="previewImage(event)">
                                    <label for="productImage" class="file-input-label">
                                        <?php echo ($product['image_path'] && file_exists($product['image_path'])) ? '🔄 Change Image' : '📁 Upload Image'; ?>
                                    </label>
                                </div>
                                <small style="color: #666;">Supported formats: JPG, JPEG, PNG, GIF, WEBP (Max 5MB)</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="name">Product Name <span class="required">*</span></label>
                            <input type="text" id="name" name="name" required 
                                   value="<?php echo htmlspecialchars($product['Name']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="category_id">Category <span class="required">*</span></label>
                            <select id="category_id" name="category_id" required>
                                <?php while ($cat = $categories->fetch_assoc()): ?>
                                    <option value="<?php echo $cat['category_id']; ?>"
                                            <?php echo ($product['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($product['description']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="price">Price (RM) <span class="required">*</span></label>
                            <input type="number" id="price" name="price" step="0.01" min="0.01" required
                                   value="<?php echo $product['price']; ?>">
                        </div>
                        <div class="form-group">
                            <label for="stock">Stock Quantity <span class="required">*</span></label>
                            <input type="number" id="stock" name="stock" min="0" required
                                   value="<?php echo $product['stock_quantity']; ?>">
                        </div>
                        <div class="form-group">
                            <label for="size">Size <span class="required">*</span></label>
                            <select id="size" name="size" required>
                                <option value="Small" <?php echo ($product['size'] == 'Small') ? 'selected' : ''; ?>>Small</option>
                                <option value="Medium" <?php echo ($product['size'] == 'Medium') ? 'selected' : ''; ?>>Medium</option>
                                <option value="Large" <?php echo ($product['size'] == 'Large') ? 'selected' : ''; ?>>Large</option>
                                <option value="Extra Large" <?php echo ($product['size'] == 'Extra Large') ? 'selected' : ''; ?>>Extra Large</option>
                            </select>
                        </div>
                        <button type="submit" name="update_product" class="btn btn-primary btn-block">
                            💾 Save Changes
                        </button>
                    </form>
                </div>

                <div class="edit-card">
                    <div class="edit-card-header">
                        <h3>🗑️ Danger Zone</h3>
                    </div>
                    <form method="POST">
                        <p style="color: #666; margin-bottom: 1rem;">
                            Deleting this product will soft-delete it from the system. This action can be reversed.
                        </p>
                        <button type="submit" name="delete_product" class="btn btn-danger btn-block"
                                onclick="return confirm('Are you sure you want to delete this product?')">
                            🗑️ Delete Product
                        </button>
                    </form>
                </div>
            </div>

            <div class="edit-user-sidebar">
                <div class="edit-card stats-card">
                    <div class="edit-card-header">
                        <h3>📊 Product Info</h3>
                    </div>
                    <div class="stats-grid-mini">
                        <div class="stat-mini">
                            <div class="stat-mini-label">Category</div>
                            <div class="stat-mini-value"><?php echo htmlspecialchars($product['category_name']); ?></div>
                        </div>
                        <div class="stat-mini">
                            <div class="stat-mini-label">Current Price</div>
                            <div class="stat-mini-value">RM <?php echo number_format($product['price'], 2); ?></div>
                        </div>
                        <div class="stat-mini">
                            <div class="stat-mini-label">Stock Status</div>
                            <div class="stat-mini-value">
                                <?php if ($product['stock_quantity'] == 0): ?>
                                    <span class="status-badge status-inactive">Out of Stock</span>
                                <?php elseif ($product['stock_quantity'] < 20): ?>
                                    <span class="status-badge status-pending">Low Stock</span>
                                <?php else: ?>
                                    <span class="status-badge status-active">In Stock</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="stat-mini">
                            <div class="stat-mini-label">Created</div>
                            <div class="stat-mini-value"><?php echo date('M d, Y', strtotime($product['created_at'])); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function previewImage(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('imagePreview');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview" id="currentImage"><button type="button" class="remove-image-btn" onclick="removeImage()" title="Remove image">×</button>';
                }
                reader.readAsDataURL(file);
                document.getElementById('removeImageInput').value = '0';
            }
        }
        
        function removeImage() {
            if (confirm('Are you sure you want to remove this image?')) {
                document.getElementById('imagePreview').innerHTML = '<span class="image-preview-placeholder">🥤</span>';
                document.getElementById('productImage').value = '';
                document.getElementById('removeImageInput').value = '1';
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
