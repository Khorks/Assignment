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

// Fetch user details
$sql = "SELECT * FROM user WHERE user_id = $user_id";
$result = $conn->query($sql);

if ($result->num_rows === 0) {
    header('Location: manage_users.php');
    exit();
}

$user = $result->fetch_assoc();

// Get user's order history
$orders_query = "SELECT o.*, 
                 COUNT(oi.order_item_id) as item_count,
                 SUM(oi.quantity * oi.price) as total_amount
                 FROM `order` o
                 LEFT JOIN order_item oi ON o.order_id = oi.order_id
                 WHERE o.user_id = $user_id
                 GROUP BY o.order_id
                 ORDER BY o.order_date DESC
                 LIMIT 10";
$orders = $conn->query($orders_query);

// Get user statistics
$stats_query = "SELECT 
                COUNT(DISTINCT o.order_id) as total_orders,
                COALESCE(SUM(oi.quantity * oi.price), 0) as total_spent
                FROM `order` o
                LEFT JOIN order_item oi ON o.order_id = oi.order_id
                WHERE o.user_id = $user_id";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User - <?php echo htmlspecialchars($user['Name']); ?></title>
    <!-- FIXED: Changed from styles.css to ../styles.css -->
    <link rel="stylesheet" href="../styles.css">
</head>
<body class="gradient-bg">
    <div class="user-detail-container">
        <div class="page-header">
            <div>
                <h1>👤 User Details</h1>
                <p>View complete user information</p>
            </div>
            <div>
                <a href="manage_users.php" class="btn btn-secondary">← Back to Users</a>
            </div>
        </div>

        <div class="user-detail-card">
            <!-- Basic Information -->
            <div class="user-info-section">
                <h3>Basic Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">User ID</div>
                        <div class="info-value">#<?php echo $user['user_id']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Full Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['Name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email Address</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['Email']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone Number</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['phone_number'] ?? 'Not provided'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Account Information -->
            <div class="user-info-section">
                <h3>Account Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Role</div>
                        <div class="info-value">
                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Account Status</div>
                        <div class="info-value">
                            <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Registration Date</div>
                        <div class="info-value"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Member Since</div>
                        <div class="info-value">
                            <?php 
                            $days = floor((time() - strtotime($user['created_at'])) / (60 * 60 * 24));
                            echo $days . ' days';
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="user-info-section">
                <h3>Purchase Statistics</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Total Orders</div>
                        <div class="info-value" style="font-size: 1.5rem; color: #667eea; font-weight: bold;">
                            <?php echo $stats['total_orders']; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Total Spent</div>
                        <div class="info-value" style="font-size: 1.5rem; color: #27ae60; font-weight: bold;">
                            RM <?php echo number_format($stats['total_spent'], 2); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Average Order Value</div>
                        <div class="info-value" style="font-size: 1.5rem; color: #f39c12; font-weight: bold;">
                            RM <?php echo $stats['total_orders'] > 0 ? number_format($stats['total_spent'] / $stats['total_orders'], 2) : '0.00'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <?php if ($orders->num_rows > 0): ?>
            <div class="user-info-section">
                <h3>Recent Orders (Last 10)</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = $orders->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $order['order_id']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                <td><?php echo $order['item_count']; ?> items</td>
                                <td>RM <?php echo number_format($order['total_amount'], 2); ?></td>
                                <td>
                                    <span class="badge badge-info">
                                        <?php echo ucfirst($order['status'] ?? 'Pending'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="action-section">
                <a href="manage_users.php" class="btn btn-secondary">← Back to List</a>
                <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                    <a href="delete_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-danger">🗑️ Delete User</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>