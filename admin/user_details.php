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
$is_own_profile = ($user_id == $_SESSION['user_id']);

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update user information
    if (isset($_POST['update_info'])) {
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        
        // Check if email already exists (excluding current user)
        $check_query = "SELECT user_id FROM user WHERE Email = '$email' AND user_id != $user_id";
        $check_result = $conn->query($check_query);
        
        if ($check_result->num_rows > 0) {
            $message = "Email already exists!";
            $message_type = "error";
        } else {
            $sql = "UPDATE user SET Name = '$name', Email = '$email', phone_number = '$phone' WHERE user_id = $user_id";
            
            if ($conn->query($sql)) {
                $message = "User information updated successfully!";
                $message_type = "success";
            } else {
                $message = "Error updating user: " . $conn->error;
                $message_type = "error";
            }
        }
    }
    
    // Change role
    if (isset($_POST['change_role']) && !$is_own_profile) {
        $new_role = mysqli_real_escape_string($conn, $_POST['new_role']);
        $sql = "UPDATE user SET role = '$new_role' WHERE user_id = $user_id";
        
        if ($conn->query($sql)) {
            $message = "User role updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating role: " . $conn->error;
            $message_type = "error";
        }
    }
    
    // Toggle status
    if (isset($_POST['toggle_status']) && !$is_own_profile) {
        $new_status = intval($_POST['new_status']);
        $sql = "UPDATE user SET is_active = $new_status WHERE user_id = $user_id";
        
        if ($conn->query($sql)) {
            $message = $new_status ? "User activated successfully!" : "User deactivated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating status: " . $conn->error;
            $message_type = "error";
        }
    }
    
    // Delete user
    if (isset($_POST['delete_user']) && !$is_own_profile) {
        // Check if user has orders
        $orders_check = "SELECT COUNT(*) as order_count FROM `order` WHERE user_id = $user_id";
        $orders_result = $conn->query($orders_check);
        $orders_data = $orders_result->fetch_assoc();
        
        if ($orders_data['order_count'] > 0) {
            $message = "Cannot delete user with existing orders. Consider deactivating the account instead.";
            $message_type = "error";
        } else {
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
}

// Fetch user details
$sql = "SELECT * FROM user WHERE user_id = $user_id";
$result = $conn->query($sql);

if ($result->num_rows === 0) {
    header('Location: manage_users.php');
    exit();
}

$user = $result->fetch_assoc();

// Get user's order statistics
$stats_query = "SELECT 
                COUNT(DISTINCT o.order_id) as total_orders,
                COALESCE(SUM(oi.quantity * oi.price_each), 0) as total_spent
                FROM `order` o
                LEFT JOIN order_item oi ON o.order_id = oi.order_id
                WHERE o.user_id = $user_id";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get recent orders
$orders_query = "SELECT o.*, 
                 COUNT(oi.order_item_id) as item_count
                 FROM `order` o
                 LEFT JOIN order_item oi ON o.order_id = oi.order_id
                 WHERE o.user_id = $user_id
                 GROUP BY o.order_id
                 ORDER BY o.order_date DESC
                 LIMIT 5";
$orders = $conn->query($orders_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - <?php echo htmlspecialchars($user['Name']); ?></title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body class="gradient-bg">
    <div class="edit-user-container">
        <div class="page-header">
            <div>
                <h1><?php echo $is_own_profile ? '👤 Your Profile' : '✏️ Edit User'; ?></h1>
                <p><?php echo htmlspecialchars($user['Name']); ?></p>
            </div>
            <div>
                <!-- FIXED: Changed from edit_users.php to manage_users.php -->
                <a href="manage_users.php" class="btn btn-secondary">← Back to Users</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="edit-user-layout">
            <!-- Left Column: User Info & Actions -->
            <div class="edit-user-main">
                <!-- Basic Information -->
                <div class="edit-card">
                    <div class="edit-card-header">
                        <h3>📝 Basic Information</h3>
                    </div>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="name">Full Name <span class="required">*</span></label>
                            <input type="text" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($user['Name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address <span class="required">*</span></label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['Email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>">
                        </div>
                        <button type="submit" name="update_info" class="btn btn-primary btn-block">
                            💾 Save Changes
                        </button>
                    </form>
                </div>

                <?php if (!$is_own_profile): ?>
                <!-- Account Management -->
                <div class="edit-card">
                    <div class="edit-card-header">
                        <h3>⚙️ Account Management</h3>
                    </div>
                    
                    <!-- Change Role -->
                    <form method="POST" action="" class="management-form">
                        <div class="form-group">
                            <label for="new_role">User Role</label>
                            <select id="new_role" name="new_role" class="form-select">
                                <option value="customer" <?php echo ($user['role'] === 'customer') ? 'selected' : ''; ?>>Customer</option>
                                <option value="staff" <?php echo ($user['role'] === 'staff') ? 'selected' : ''; ?>>Staff</option>
                                <option value="admin" <?php echo ($user['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <button type="submit" name="change_role" class="btn btn-primary btn-block"
                                onclick="return confirm('Are you sure you want to change this user\'s role?')">
                            🔄 Change Role
                        </button>
                    </form>

                    <hr style="margin: 1.5rem 0; border: none; border-top: 2px solid #f0f0f0;">

                    <!-- Toggle Status -->
                    <form method="POST" action="" class="management-form">
                        <input type="hidden" name="new_status" value="<?php echo $user['is_active'] ? 0 : 1; ?>">
                        <button type="submit" name="toggle_status" 
                                class="btn <?php echo $user['is_active'] ? 'btn-warning' : 'btn-success'; ?> btn-block"
                                onclick="return confirm('Are you sure you want to <?php echo $user['is_active'] ? 'deactivate' : 'activate'; ?> this user?')">
                            <?php echo $user['is_active'] ? '🔒 Deactivate Account' : '✅ Activate Account'; ?>
                        </button>
                    </form>

                    <hr style="margin: 1.5rem 0; border: none; border-top: 2px solid #f0f0f0;">

                    <!-- Delete User -->
                    <form method="POST" action="" class="management-form">
                        <button type="submit" name="delete_user" class="btn btn-danger btn-block"
                                onclick="return confirm('⚠️ WARNING: This will permanently delete the user account!\n\nThis action cannot be undone.\n\nAre you absolutely sure?')">
                            🗑️ Delete User Permanently
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column: Statistics & Orders -->
            <div class="edit-user-sidebar">
                <!-- Account Status -->
                <div class="edit-card stats-card">
                    <div class="edit-card-header">
                        <h3>📊 Account Status</h3>
                    </div>
                    <div class="stats-grid-mini">
                        <div class="stat-mini">
                            <div class="stat-mini-label">Role</div>
                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </div>
                        <div class="stat-mini">
                            <div class="stat-mini-label">Status</div>
                            <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $user['is_active'] ? '✓ Active' : '✗ Inactive'; ?>
                            </span>
                        </div>
                        <div class="stat-mini">
                            <div class="stat-mini-label">Member Since</div>
                            <div class="stat-mini-value">
                                <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                            </div>
                        </div>
                        <div class="stat-mini">
                            <div class="stat-mini-label">Days Active</div>
                            <div class="stat-mini-value">
                                <?php echo floor((time() - strtotime($user['created_at'])) / (60 * 60 * 24)); ?> days
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Purchase Statistics -->
                <div class="edit-card stats-card">
                    <div class="edit-card-header">
                        <h3>💰 Purchase Statistics</h3>
                    </div>
                    <div class="stats-big">
                        <div class="stat-big">
                            <div class="stat-big-value"><?php echo $stats['total_orders']; ?></div>
                            <div class="stat-big-label">Total Orders</div>
                        </div>
                        <div class="stat-big">
                            <div class="stat-big-value">RM <?php echo number_format($stats['total_spent'], 2); ?></div>
                            <div class="stat-big-label">Total Spent</div>
                        </div>
                        <div class="stat-big">
                            <div class="stat-big-value">
                                RM <?php echo $stats['total_orders'] > 0 ? number_format($stats['total_spent'] / $stats['total_orders'], 2) : '0.00'; ?>
                            </div>
                            <div class="stat-big-label">Average Order</div>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <?php if ($orders->num_rows > 0): ?>
                <div class="edit-card">
                    <div class="edit-card-header">
                        <h3>📦 Recent Orders</h3>
                    </div>
                    <div class="orders-list">
                        <?php while ($order = $orders->fetch_assoc()): ?>
                        <div class="order-item">
                            <div class="order-item-header">
                                <span class="order-id">#<?php echo $order['order_id']; ?></span>
                                <span class="order-status status-<?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status'] ?? 'Pending'); ?>
                                </span>
                            </div>
                            <div class="order-item-details">
                                <span>📅 <?php echo date('M d, Y', strtotime($order['order_date'])); ?></span>
                                <span>📦 <?php echo $order['item_count']; ?> items</span>
                            </div>
                            <div class="order-item-amount">
                                RM <?php echo number_format($order['total_amount'], 2); ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>