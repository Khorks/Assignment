<?php
session_start();
include '../db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$message = '';
$message_type = '';

// Handle success/error messages
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $message = $_SESSION['error_message'];
    $message_type = 'error';
    unset($_SESSION['error_message']);
}

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $update_query = "UPDATE `order` SET status = '$new_status', updated_at = NOW() WHERE order_id = $order_id";
    
    if ($conn->query($update_query)) {
        $message = "Order #$order_id status updated to " . ucfirst($new_status);
        $message_type = "success";
        
        if(isset($_GET['view']) && $_GET['view'] == 'detail') {
            header("Location: admin_orders.php?view=detail&id=$order_id");
            exit();
        }
    } else {
        $message = "Error updating order status";
        $message_type = "error";
    }
}

// Handle delivery status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_delivery'])) {
    $order_id = intval($_POST['order_id']);
    $delivery_status = mysqli_real_escape_string($conn, $_POST['delivery_status']);
    
    $check_delivery = "SELECT delivery_id FROM delivery WHERE order_id = $order_id";
    $delivery_exists = $conn->query($check_delivery);
    
    if ($delivery_exists->num_rows > 0) {
        $update_delivery = "UPDATE delivery SET delivery_status = '$delivery_status', updated_at = NOW() WHERE order_id = $order_id";
    } else {
        $get_address = "SELECT address FROM user u JOIN `order` o ON u.user_id = o.user_id WHERE o.order_id = $order_id";
        $address_result = $conn->query($get_address)->fetch_assoc();
        $address = $address_result['address'] ?? 'Address not provided';
        
        $update_delivery = "INSERT INTO delivery (order_id, delivery_status, delivery_address, created_at) 
                           VALUES ($order_id, '$delivery_status', '$address', NOW())";
    }
    
    if ($conn->query($update_delivery)) {
        $message = "Delivery status updated successfully";
        $message_type = "success";
        
        if(isset($_GET['view']) && $_GET['view'] == 'detail') {
            header("Location: admin_orders.php?view=detail&id=$order_id");
            exit();
        }
    } else {
        $message = "Error updating delivery status";
        $message_type = "error";
    }
}

// Get filters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'all';
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'list';
$selected_order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Build query
$query = "SELECT o.*, u.Name as customer_name, u.Email, u.phone_number, u.address,
          p.payment_status, p.payment_method, p.payment_date,
          d.delivery_status, d.delivery_address, d.tracking_number
          FROM `order` o
          JOIN user u ON o.user_id = u.user_id
          LEFT JOIN payment p ON o.order_id = p.order_id
          LEFT JOIN delivery d ON o.order_id = d.order_id
          WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (o.order_id LIKE '%$search%' OR u.Name LIKE '%$search%' OR u.Email LIKE '%$search%')";
}

if ($status_filter !== 'all') {
    $query .= " AND o.status = '$status_filter'";
}

$query .= " ORDER BY o.order_date DESC";
$orders = $conn->query($query);

// If viewing single order
$order_details = null;
$order_items = null;
if ($view_mode === 'detail' && $selected_order_id > 0) {
    $detail_query = "SELECT o.*, u.Name as customer_name, u.Email, u.phone_number, u.address,
                     p.payment_status, p.payment_method, p.payment_date, p.transaction_id,
                     d.delivery_status, d.delivery_address, d.tracking_number, d.estimated_delivery
                     FROM `order` o
                     JOIN user u ON o.user_id = u.user_id
                     LEFT JOIN payment p ON o.order_id = p.order_id
                     LEFT JOIN delivery d ON o.order_id = d.order_id
                     WHERE o.order_id = $selected_order_id";
    $order_details = $conn->query($detail_query)->fetch_assoc();
    
    if ($order_details) {
        $items_query = "SELECT oi.*, p.Name as product_name, p.size, c.category_name
                       FROM order_item oi
                       JOIN product p ON oi.product_id = p.product_id
                       JOIN category c ON p.category_id = c.category_id
                       WHERE oi.order_id = $selected_order_id";
        $order_items = $conn->query($items_query);
    }
}

// Get statistics
$total_orders = $conn->query("SELECT COUNT(*) as count FROM `order`")->fetch_assoc()['count'];
$pending_orders = $conn->query("SELECT COUNT(*) as count FROM `order` WHERE status = 'pending'")->fetch_assoc()['count'];
$processing_orders = $conn->query("SELECT COUNT(*) as count FROM `order` WHERE status = 'processing'")->fetch_assoc()['count'];
$completed_orders = $conn->query("SELECT COUNT(*) as count FROM `order` WHERE status = 'completed'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - The Drink Lab</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .filter-buttons {
            display: flex;
            gap: 10px;
            height: 100%;
            align-items: flex-end;
        }
        
        .filter-btn-shared {
            flex: 1;
            padding: 0.9rem;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }

        .status-badge {
            padding: 0.4rem 1.2rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 700;
            display: inline-block;
            text-align: center;
            -webkit-text-fill-color: initial !important; 
            text-fill-color: initial !important;
            background-clip: border-box !important;
            -webkit-background-clip: border-box !important;
        }
        
        .status-pending { background-color: #fef3c7 !important; color: #b45309 !important; }
        .status-processing { background-color: #dbeafe !important; color: #1e40af !important; }
        .status-completed, .status-paid { background-color: #d1fae5 !important; color: #065f46 !important; }
        .status-cancelled { background-color: #fee2e2 !important; color: #991b1b !important; }
        .status-shipped { background-color: #e0e7ff !important; color: #3730a3 !important; }
        .status-delivered { background-color: #dcfce7 !important; color: #166534 !important; }

        .order-detail-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .order-detail-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .order-detail-main {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .order-detail-sidebar {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .action-section {
            display: flex;
            gap: 1rem;
            justify-content: flex-start;
            margin-top: 2rem;
        }
        
        .user-info-section {
            margin-bottom: 2rem;
        }
        
        .user-info-section h3 {
            color: #1e3c72;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #00d4ff;
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .form-section h3 {
            color: #1e3c72;
            margin-bottom: 1rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        @media (max-width: 1200px) {
            .order-detail-layout {
                grid-template-columns: 1fr;
            }
            
            .order-detail-sidebar {
                order: -1;
            }
        }
    </style>
</head>
<body class="gradient-bg">

<?php if ($view_mode === 'detail' && $order_details): ?>
    <!-- ORDER DETAIL VIEW -->
    <div class="order-detail-container">
        <div class="page-header">
            <div>
                <h1>📦 Order #<?php echo $order_details['order_id']; ?></h1>
                <p>Order details and management</p>
            </div>
            <div style="display: flex; gap: 1rem;">
                <a href="admin_orders.php" class="btn btn-secondary">← Back to Orders</a>
                <a href="../admin_dashboard.php" class="btn btn-secondary">🏠 Dashboard</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="order-detail-layout">
            <div class="order-detail-main">
                <!-- Customer Information -->
                <div class="edit-card">
                    <div class="edit-card-header">
                        <h3>👤 Customer Information</h3>
                    </div>
                    <div class="user-info-section" style="padding: 2rem;">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Customer Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($order_details['customer_name']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($order_details['Email']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Phone Number</div>
                                <div class="info-value"><?php echo htmlspecialchars($order_details['phone_number'] ?: 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Delivery Address</div>
                                <div class="info-value"><?php echo htmlspecialchars($order_details['delivery_address'] ?: $order_details['address'] ?: 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="edit-card">
                    <div class="edit-card-header">
                        <h3>🛒 Order Items</h3>
                    </div>
                    <div style="padding: 2rem;">
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Size</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($item = $order_items->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($item['product_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['size']); ?></td>
                                        <td>RM <?php echo number_format($item['price_each'], 2); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td><strong>RM <?php echo number_format($item['subtotal'], 2); ?></strong></td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <tr style="background: #f0f4f8; font-weight: bold;">
                                        <td colspan="5" style="text-align: right;">Total Amount:</td>
                                        <td style="font-size: 1.2rem; color: #10b981;">
                                            RM <?php echo number_format($order_details['total_amount'], 2); ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Payment Information -->
                <div class="edit-card">
                    <div class="edit-card-header">
                        <h3>💳 Payment Information</h3>
                    </div>
                    <div class="user-info-section" style="padding: 2rem;">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Payment Method</div>
                                <div class="info-value"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order_details['payment_method'] ?? 'N/A'))); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Payment Status</div>
                                <div class="info-value">
                                    <span class="status-badge status-<?php echo $order_details['payment_status'] ?? 'pending'; ?>">
                                        <?php echo ucfirst($order_details['payment_status'] ?? 'Pending'); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Payment Date</div>
                                <div class="info-value"><?php echo $order_details['payment_date'] ? date('M d, Y H:i', strtotime($order_details['payment_date'])) : 'N/A'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Transaction ID</div>
                                <div class="info-value"><?php echo htmlspecialchars($order_details['transaction_id'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="order-detail-sidebar">
                <!-- Order Status Management -->
                <div class="edit-card">
                    <div class="edit-card-header">
                        <h3>📊 Order Status</h3>
                    </div>
                    <form method="POST" style="padding: 2rem;">
                        <input type="hidden" name="order_id" value="<?php echo $order_details['order_id']; ?>">
                        <div class="form-group">
                            <label for="status">Update Order Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="pending" <?php echo ($order_details['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo ($order_details['status'] == 'processing') ? 'selected' : ''; ?>>Processing</option>
                                <option value="completed" <?php echo ($order_details['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo ($order_details['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <button type="submit" name="update_status" class="btn btn-primary btn-block">
                            💾 Update Status
                        </button>
                    </form>
                </div>

                <!-- Delivery Status Management -->
                <div class="edit-card">
                    <div class="edit-card-header">
                        <h3>🚚 Delivery Status</h3>
                    </div>
                    <form method="POST" style="padding: 2rem;">
                        <input type="hidden" name="order_id" value="<?php echo $order_details['order_id']; ?>">
                        <div class="form-group">
                            <label for="delivery_status">Update Delivery Status</label>
                            <select name="delivery_status" id="delivery_status" class="form-select">
                                <option value="pending" <?php echo ($order_details['delivery_status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo ($order_details['delivery_status'] == 'processing') ? 'selected' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo ($order_details['delivery_status'] == 'shipped') ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo ($order_details['delivery_status'] == 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo ($order_details['delivery_status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <button type="submit" name="update_delivery" class="btn btn-primary btn-block">
                            💾 Update Delivery
                        </button>
                    </form>
                </div>

                <!-- Order Summary -->
                <div class="edit-card stats-card">
                    <div class="edit-card-header">
                        <h3>📋 Order Summary</h3>
                    </div>
                    <div class="stats-big">
                        <div class="stat-big">
                            <div class="stat-big-value">RM <?php echo number_format($order_details['total_amount'], 2); ?></div>
                            <div class="stat-big-label">Total Amount</div>
                        </div>
                        <div class="stat-big">
                            <div class="stat-big-value"><?php echo date('M d, Y', strtotime($order_details['order_date'])); ?></div>
                            <div class="stat-big-label">Order Date</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- ORDERS LIST VIEW -->
    <div class="users-container">
        <div class="page-header">
            <div>
                <h1>📦 Manage Orders</h1>
                <p>View and manage all customer orders</p>
            </div>
            <div style="display: flex; gap: 1rem;">
                <a href="../admin_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid" style="margin-bottom: 2rem;">
            <div class="stat-card">
                <h3>📊 Total Orders</h3>
                <div class="stat-value"><?php echo $total_orders; ?></div>
            </div>
            <div class="stat-card">
                <h3>⏳ Pending</h3>
                <div class="stat-value" style="color: #f59e0b;"><?php echo $pending_orders; ?></div>
            </div>
            <div class="stat-card">
                <h3>🔄 Processing</h3>
                <div class="stat-value" style="color: #3b82f6;"><?php echo $processing_orders; ?></div>
            </div>
            <div class="stat-card">
                <h3>✅ Completed</h3>
                <div class="stat-value" style="color: #10b981;"><?php echo $completed_orders; ?></div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" action="">
                <div class="filter-row" style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 20px; align-items: end;">
                    <div class="filter-group">
                        <label for="search">🔍 Search Orders</label>
                        <input type="text" name="search" id="search" 
                               placeholder="Search by order ID, customer name, or email..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="status">📊 Filter by Status</label>
                        <select name="status" id="status">
                            <option value="all" <?php echo ($status_filter == 'all') ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo ($status_filter == 'processing') ? 'selected' : ''; ?>>Processing</option>
                            <option value="completed" <?php echo ($status_filter == 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo ($status_filter == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <div class="filter-buttons">
                            <button type="submit" class="btn-primary filter-btn-shared">
                                🔬 Filter
                            </button>
                            <a href="admin_orders.php" class="btn-secondary filter-btn-shared">
                                Clear
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="content-card">
            <h2>All Orders (<?php echo $orders->num_rows; ?>)</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Total Amount</th>
                            <th>Order Date</th>
                            <th>Order Status</th>
                            <th>Payment Status</th>
                            <th>Delivery Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders->num_rows > 0): ?>
                            <?php while ($order = $orders->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $order['order_id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['Email']); ?></td>
                                    <td><strong style="color: #10b981;">RM <?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $order['status']; ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $order['payment_status'] ?? 'pending'; ?>">
                                            <?php echo ucfirst($order['payment_status'] ?? 'Pending'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $order['delivery_status'] ?? 'pending'; ?>">
                                            <?php echo ucfirst($order['delivery_status'] ?? 'Pending'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="admin_orders.php?view=detail&id=<?php echo $order['order_id']; ?>" 
                                           class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.85rem;">
                                            👁️ View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 2rem;">
                                    No orders found matching your criteria
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

</body>
</html>
<?php $conn->close(); ?>