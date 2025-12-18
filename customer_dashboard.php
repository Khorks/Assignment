<?php
session_start();
include 'db_connect.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get Customer Stats
$orders_query = "SELECT COUNT(*) as count FROM `order` WHERE user_id = $user_id";
$total_orders = $conn->query($orders_query)->fetch_assoc()['count'];

$spent_query = "SELECT SUM(total_amount) as total FROM `order` WHERE user_id = $user_id AND status = 'completed'";
$total_spent = $conn->query($spent_query)->fetch_assoc()['total'] ?? 0;

// Get Recent Orders
$recent_query = "SELECT * FROM `order` WHERE user_id = $user_id ORDER BY order_date DESC LIMIT 5";
$recent_orders = $conn->query($recent_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - The Drink Lab</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="gradient-bg">
    <div class="dashboard-container">
        
        <!-- Header Section -->
        <div class="dashboard-header">
            <div>
                <h1>👤 My Account</h1>
                <p>Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?>!</p>
            </div>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <!-- MOVED: Edit Profile Button is now here -->
                <a href="customer/edit_profile.php" class="btn btn-secondary" style="background-color: #f39c12; border: none;">✏️ Edit Profile</a>
                
                <a href="homepage.php" class="btn btn-primary">🛍️ Browse Menu</a>
                <a href="customer/cart.php" class="btn btn-primary">🛒 Cart</a>
                <a href="auth/logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </div>

        <!-- Customer Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>📦 Total Orders</h3>
                <div class="stat-value"><?php echo $total_orders; ?></div>
            </div>
            <div class="stat-card">
                <h3>💰 Total Spent</h3>
                <div class="stat-value">RM <?php echo number_format($total_spent, 2); ?></div>
            </div>
        </div>

        <!-- Recent Orders (Full Width) -->
        <div style="margin-top: 2rem;">
            <div class="content-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h2>🕒 Recent Experiments</h2>
                    <!-- Kept this link so user can still access full history -->
                    <a href="customer/view_order.php" class="btn btn-secondary" style="font-size: 0.9rem; padding: 5px 15px;">📜 View All History</a>
                </div>
                
                <div class="table-responsive">
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_orders->num_rows > 0): ?>
                                <?php while ($order = $recent_orders->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $order['order_id']; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                        <td>RM <?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="customer/view_order.php?id=<?php echo $order['order_id']; ?>" class="btn btn-primary" style="padding: 0.3rem 0.8rem; font-size: 0.8rem;">View</a>
                                            <a href="customer/track_delivery.php?id=<?php echo $order['order_id']; ?>" class="btn btn-warning" style="padding: 0.3rem 0.8rem; font-size: 0.8rem;">Track</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center">No orders yet. Start mixing!</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</body>
</html>
<?php $conn->close(); ?>