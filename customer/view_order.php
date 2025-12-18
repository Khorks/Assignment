<?php
session_start();
include '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order History - The Drink Lab</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body class="gradient-bg">
    <div class="container">
        
        <?php if ($order_id > 0): 
            // --- VIEW SINGLE ORDER DETAILS ---
            $order_query = "SELECT * FROM `order` WHERE order_id = $order_id AND user_id = $user_id";
            $order = $conn->query($order_query)->fetch_assoc();
            
            if (!$order) { 
                echo "<div class='content-card'><h2 style='text-align:center; color:red;'>🚫 Order not found or access denied.</h2><div style='text-align:center; margin-top:20px;'><a href='view_order.php' class='btn btn-secondary'>Back to List</a></div></div>"; 
                exit; 
            }
            
            // Get Items for this order
            $items = $conn->query("SELECT oi.*, p.Name, p.image_path FROM order_item oi JOIN product p ON oi.product_id = p.product_id WHERE oi.order_id = $order_id");
            $delivery = $conn->query("SELECT * FROM delivery WHERE order_id = $order_id")->fetch_assoc();
        ?>
            <div class="page-header" style="background: white; padding: 2rem; border-radius: 15px; margin-bottom: 2rem;">
                <h1>📦 Order #<?php echo $order_id; ?></h1>
                <div style="margin-top: 1rem;">
                    <a href="view_order.php" class="btn btn-secondary">← Back to List</a>
                </div>
            </div>

            <div class="content-card">
                <h3>Order Items</h3>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
                        <tbody>
                            <?php while($item = $items->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['Name']); ?></strong>
                                    <?php if(!empty($item['customization'])): ?>
                                        <br><small style="color: #666;">🎨 <?php echo htmlspecialchars($item['customization']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>RM <?php echo number_format($item['price_each'], 2); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>RM <?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <tr>
                                <td colspan="3" align="right"><strong>Total:</strong></td>
                                <td style="color: #10b981; font-weight: bold;">RM <?php echo number_format($order['total_amount'], 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top: 2rem;">
                    <p><strong>Delivery Address:</strong> <?php echo htmlspecialchars($delivery['delivery_address'] ?? 'N/A'); ?></p>
                    <p><strong>Status:</strong> <span class="status-badge status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></p>
                </div>
                
                <div style="margin-top: 2rem; display: flex; gap: 10px;">
                    <a href="reorder.php?id=<?php echo $order_id; ?>" class="btn btn-warning">🔄 Re-order Items</a>
                    <a href="track_delivery.php?id=<?php echo $order_id; ?>" class="btn btn-primary">🚚 Track Delivery</a>
                </div>
            </div>

        <?php else: 
            // --- VIEW ALL ORDERS LIST ---
            $orders = $conn->query("SELECT * FROM `order` WHERE user_id = $user_id ORDER BY order_date DESC");
        ?>
            <div class="page-header" style="background: white; padding: 2rem; border-radius: 15px; margin-bottom: 2rem;">
                <h1>📜 My Order History</h1>
                <a href="../customer_dashboard.php" class="btn btn-secondary">← My Account</a>
            </div>

            <div class="content-card">
                <?php if ($orders->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>ID</th><th>Date</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php while($row = $orders->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $row['order_id']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['order_date'])); ?></td>
                                <td>RM <?php echo number_format($row['total_amount'], 2); ?></td>
                                <td><span class="status-badge status-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                <td><a href="view_order.php?id=<?php echo $row['order_id']; ?>" class="btn btn-primary">View</a></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 2rem;">
                        <h3>No orders yet.</h3>
                        <a href="../homepage.php" class="btn btn-primary">Start Ordering</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>