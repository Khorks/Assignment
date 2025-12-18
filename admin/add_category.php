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
    $category_name = mysqli_real_escape_string($conn, trim($_POST['category_name']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($category_name)) {
        $message = "Category name is required!";
        $message_type = "error";
    } else {
        $check_query = "SELECT category_id FROM category WHERE category_name = '$category_name'";
        $check_result = $conn->query($check_query);
        
        if ($check_result->num_rows > 0) {
            $message = "Category name already exists!";
            $message_type = "error";
        } else {
            $sql = "INSERT INTO category (category_name, description, is_active, created_at) 
                    VALUES ('$category_name', '$description', $is_active, NOW())";
            
            if ($conn->query($sql)) {
                $_SESSION['success_message'] = "Category '$category_name' added successfully!";
                header('Location: view_category.php'); // FIXED
                exit();
            } else {
                $message = "Error adding category: " . $conn->error;
                $message_type = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Category - The Drink Lab</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body class="gradient-bg">
    <div class="user-detail-container">
        <div class="page-header">
            <div>
                <h1>➕ Add New Category</h1>
                <p>Create a new product category</p>
            </div>
            <div>
                <a href="view_category.php" class="btn btn-secondary">← Back to Categories</a> <!-- FIXED -->
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="user-detail-card">
            <form method="POST" action="">
                <div class="form-section">
                    <h3>Category Information</h3>
                    <div class="form-group">
                        <label for="category_name">Category Name <span class="required">*</span></label>
                        <input type="text" id="category_name" name="category_name" required
                               value="<?php echo isset($_POST['category_name']) ? htmlspecialchars($_POST['category_name']) : ''; ?>"
                               placeholder="e.g., Coffee, Tea, Juice">
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4"
                                  placeholder="Brief description of this category..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_active" name="is_active" 
                                   <?php echo (!isset($_POST['is_active']) || isset($_POST['is_active'])) ? 'checked' : ''; ?>>
                            <label for="is_active">Active Category</label>
                        </div>
                        <small style="color: #666;">Inactive categories won't appear in product forms</small>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">✅ Create Category</button>
                    <a href="view_category.php" class="btn btn-secondary">Cancel</a> <!-- FIXED -->
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
