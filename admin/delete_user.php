<?php
session_start();
include '../db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Get user ID from URL
if (!isset($_GET['id'])) {
    header('Location: manage_users.php');
    exit();
}

$user_id = intval($_GET['id']);

// Don't allow deleting yourself
if ($user_id == $_SESSION['user_id']) {
    $_SESSION['error_message'] = "You cannot delete your own account!";
    header('Location: manage_users.php');
    exit();
}

// Fetch user details
$sql = "SELECT * FROM user WHERE user_id = $user_id";
$result = $conn->query($sql);

if ($result->num_rows === 0) {
    header('Location: manage_users.php');
    exit();
}

$user = $result->fetch_assoc();

$message = '';
$message_type = '';

// Handle deletion confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    // Check if user has orders
    $orders_check = "SELECT COUNT(*) as order_count FROM `order` WHERE user_id = $user_id";
    $orders_result = $conn->query($orders_check);
    $orders_data = $orders_result->fetch_assoc();
    
    if ($orders_data['order_count'] > 0) {
        // User has orders, we should handle this carefully
        // Option 1: Prevent deletion
        $message = "Cannot delete user with existing orders. Consider deactivating the account instead.";
        $message_type = "error";
        
        // Option 2: Delete with cascade (uncomment if you want this behavior)
        /*
        // First delete order items
        $conn->query("DELETE oi FROM order_item oi 
                      INNER JOIN `order` o ON oi.order_id = o.order_id 
                      WHERE o.user_id = $user_id");
        
        // Then delete orders
        $conn->query("DELETE FROM `order` WHERE user_id = $user_id");
        */
    }
    
    // Only delete if no error occurred
    if (empty($message)) {
        // Delete user
        $delete_sql = "DELETE FROM user WHERE user_id = $user_id";
        
        if ($conn->query($delete_sql)) {
            $_SESSION['success_message'] = "User deleted successfully!";
            header('Location: manage_users.php');
            exit();
        } else {
            $message = "Error deleting user: " . $conn->error;
            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete User - Confirmation</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body class="gradient-bg">
    <div class="user-detail-container">
        <div class="page-header">
            <div>
                <h1>🗑️ Delete User</h1>
                <p>Confirm user deletion</p>
            </div>
            <div>
                <a href="manage_users.php" class="btn btn-secondary">← Cancel</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="user-detail-card">
            <div class="delete-confirmation">
                <div class="warning-icon">⚠️</div>
                <h2>Are you sure you want to delete this user?</h2>
                <p>This action cannot be undone. All user data will be permanently removed from the system.</p>
                
                <div class="user-info">
                    <h3>User Information</h3>
                    <div class="info-grid" style="margin-top: 1rem;">
                        <div class="info-item">
                            <div class="info-label">Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['Name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['Email']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Role</div>
                            <div class="info-value">
                                <span class="role-badge role-<?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Status</div>
                            <div class="info-value">
                                <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="POST" action="">
                    <div class="button-group">
                        <button type="submit" name="confirm_delete" class="btn btn-danger">
                            🗑️ Yes, Delete User
                        </button>
                        <a href="manage_users.php" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>

                <div style="margin-top: 2rem; padding: 1rem; background: #fff3cd; border-radius: 5px;">
                    <p style="margin: 0; color: #856404; font-size: 0.9rem;">
                        <strong>Note:</strong> If this user has existing orders, consider deactivating the account instead of deleting it to preserve order history.
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>