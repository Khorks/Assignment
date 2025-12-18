<?php
session_start();
include '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $category_id = intval($_POST['category_id']);
    $size = mysqli_real_escape_string($conn, $_POST['size']);
    
    // Handle image upload
    $image_path = NULL;
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/products/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            // Check file size (max 5MB)
            if ($_FILES['product_image']['size'] <= 5242880) {
                $new_filename = 'product_' . time() . '_' . uniqid() . '.' . $file_extension;
                $target_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_path)) {
                    $image_path = $target_path;
                } else {
                    $message = "Failed to upload image!";
                    $message_type = "error";
                }
            } else {
                $message = "Image size must be less than 5MB!";
                $message_type = "error";
            }
        } else {
            $message = "Invalid image format. Allowed: JPG, JPEG, PNG, GIF, WEBP";
            $message_type = "error";
        }
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
    } elseif ($category_id <= 0) {
        $message = "Please select a valid category!";
        $message_type = "error";
    } elseif (empty($message)) {
        $image_sql = $image_path ? "'$image_path'" : "NULL";
        $sql = "INSERT INTO product (Name, description, price, stock_quantity, category_id, size, image_path, is_deleted, created_at) 
                VALUES ('$name', '$description', $price, $stock, $category_id, '$size', $image_sql, 0, NOW())";
        
        if ($conn->query($sql)) {
            $_SESSION['success_message'] = "Product '$name' added successfully!";
            header('Location: view_product.php');
            exit();
        } else {
            $message = "Error adding product: " . $conn->error;
            $message_type = "error";
        }
    }
}

$categories = $conn->query("SELECT * FROM category WHERE is_active = 1 ORDER BY category_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product</title>
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
        
        .file-name {
            margin-top: 0.5rem;
            color: #6b7280;
            font-size: 0.9rem;
        }
    </style>
</head>
<body class="gradient-bg">
    <div class="user-detail-container">
        <div class="page-header">
            <div>
                <h1>➕ Add New Product</h1>
                <p>Create a new product in your inventory</p>
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

        <div class="user-detail-card">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-section">
                    <h3>Product Information</h3>
                    
                    <!-- Image Upload Section -->
                    <div class="form-group">
                        <label>Product Image</label>
                        <div class="image-upload-container">
                            <div class="image-preview" id="imagePreview">
                                <span class="image-preview-placeholder">🥤</span>
                            </div>
                            <div class="file-input-wrapper">
                                <input type="file" name="product_image" id="productImage" accept="image/*" onchange="previewImage(event)">
                                <label for="productImage" class="file-input-label">📁 Choose Image</label>
                            </div>
                            <div class="file-name" id="fileName">No file chosen</div>
                            <small style="color: #666;">Supported formats: JPG, JPEG, PNG, GIF, WEBP (Max 5MB)</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Product Name <span class="required">*</span></label>
                            <input type="text" id="name" name="name" required 
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="category_id">Category <span class="required">*</span></label>
                            <select id="category_id" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php while ($cat = $categories->fetch_assoc()): ?>
                                    <option value="<?php echo $cat['category_id']; ?>"
                                            <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="price">Price (RM) <span class="required">*</span></label>
                            <input type="number" id="price" name="price" step="0.01" min="0.01" required
                                   value="<?php echo isset($_POST['price']) ? $_POST['price'] : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="stock">Stock Quantity <span class="required">*</span></label>
                            <input type="number" id="stock" name="stock" min="0" required
                                   value="<?php echo isset($_POST['stock']) ? $_POST['stock'] : '0'; ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="size">Size <span class="required">*</span></label>
                        <select id="size" name="size" required>
                            <option value="Small" <?php echo (isset($_POST['size']) && $_POST['size'] == 'Small') ? 'selected' : ''; ?>>Small</option>
                            <option value="Medium" <?php echo (!isset($_POST['size']) || $_POST['size'] == 'Medium') ? 'selected' : ''; ?>>Medium</option>
                            <option value="Large" <?php echo (isset($_POST['size']) && $_POST['size'] == 'Large') ? 'selected' : ''; ?>>Large</option>
                            <option value="Extra Large" <?php echo (isset($_POST['size']) && $_POST['size'] == 'Extra Large') ? 'selected' : ''; ?>>Extra Large</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">✅ Add Product</button>
                    <a href="view_product.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function previewImage(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('imagePreview');
            const fileName = document.getElementById('fileName');
            
            if (file) {
                fileName.textContent = file.name;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
                }
                reader.readAsDataURL(file);
            } else {
                fileName.textContent = 'No file chosen';
                preview.innerHTML = '<span class="image-preview-placeholder">🥤</span>';
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
