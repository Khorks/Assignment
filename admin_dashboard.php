<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: auth/login.php');
    exit();
}

// Get statistics
$total_users_query = "SELECT COUNT(*) as count FROM user WHERE role = 'customer'";
$total_users = $conn->query($total_users_query)->fetch_assoc()['count'];

$total_products_query = "SELECT COUNT(*) as count FROM product WHERE is_deleted = FALSE";
$total_products = $conn->query($total_products_query)->fetch_assoc()['count'];

$total_orders_query = "SELECT COUNT(*) as count FROM `order`";
$total_orders = $conn->query($total_orders_query)->fetch_assoc()['count'];

$total_revenue_query = "SELECT SUM(total_amount) as revenue FROM `order` WHERE status = 'completed'";
$total_revenue_result = $conn->query($total_revenue_query)->fetch_assoc();
$total_revenue = $total_revenue_result['revenue'] ?? 0;

// Get recent orders
$recent_orders_query = "SELECT o.order_id, u.Name, o.total_amount, o.status, o.order_date 
                        FROM `order` o 
                        JOIN user u ON o.user_id = u.user_id 
                        ORDER BY o.order_date DESC 
                        LIMIT 5";
$recent_orders = $conn->query($recent_orders_query);

// Get low stock products
$low_stock_query = "SELECT p.product_id, p.Name, p.stock_quantity, c.category_name 
                    FROM product p 
                    JOIN category c ON p.category_id = c.category_id 
                    WHERE p.stock_quantity < 20 AND p.is_deleted = FALSE 
                    ORDER BY p.stock_quantity ASC 
                    LIMIT 5";
$low_stock = $conn->query($low_stock_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - The Drink Lab</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="gradient-bg">
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div>
                <h1>🧪 The Drink Lab - Admin Dashboard</h1>
                <p>Welcome back, Dr. <?php echo htmlspecialchars($_SESSION['name']); ?>!</p>
            </div>
            <div style="display: flex; gap: 1rem;">
                <a href="auth/logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>👥 Total Customers</h3>
                <div class="stat-value"><?php echo $total_users; ?></div>
            </div>
            <div class="stat-card">
                <h3>🥤 Total Products</h3>
                <div class="stat-value"><?php echo $total_products; ?></div>
            </div>
            <div class="stat-card">
                <h3>📦 Total Orders</h3>
                <div class="stat-value"><?php echo $total_orders; ?></div>
            </div>
            <div class="stat-card">
                <h3>💰 Total Revenue</h3>
                <div class="stat-value">RM <?php echo number_format($total_revenue, 2); ?></div>
            </div>
        </div>

        <!-- Admin Navigation -->
        <div class="admin-nav">
            <div class="admin-nav-links">
                <a href="admin/manage_users.php">👥 Manage Users</a>
                <a href="admin/view_product.php">🥤 Manage Products</a>
                <a href="admin/view_category.php">📁 Manage Categories</a>
                <a href="admin/admin_orders.php">📦 Manage Orders</a>
                <a href="admin/admin_reports.php">📊 View Reports</a>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Orders -->
            <div class="content-card">
                <h2>📦 Recent Orders</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_orders->num_rows > 0): ?>
                                <?php while ($order = $recent_orders->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $order['order_id']; ?></td>
                                        <td><?php echo htmlspecialchars($order['Name']); ?></td>
                                        <td>RM <?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">No orders yet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top: 1rem; text-align: center;">
                    <a href="admin/admin_orders.php" class="btn btn-primary">View All Orders</a>
                </div>
            </div>

            <!-- Low Stock Products -->
            <div class="content-card">
                <h2>⚠️ Low Stock Alert</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($low_stock->num_rows > 0): ?>
                                <?php while ($product = $low_stock->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['Name']); ?></td>
                                        <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                        <td class="low-stock-warning">
                                            <?php echo $product['stock_quantity']; ?> units
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align: center;">All products have sufficient stock</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top: 1rem; text-align: center;">
                    <a href="admin/view_product.php" class="btn btn-primary">Manage Products</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>