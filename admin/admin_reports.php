<?php
session_start();
include '../db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Get date filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'overview';

// Sales Overview Statistics
$total_revenue_query = "SELECT SUM(total_amount) as revenue FROM `order` 
                        WHERE status = 'completed' 
                        AND order_date BETWEEN '$date_from' AND '$date_to 23:59:59'";
$total_revenue = $conn->query($total_revenue_query)->fetch_assoc()['revenue'] ?? 0;

$total_orders_query = "SELECT COUNT(*) as count FROM `order` 
                       WHERE order_date BETWEEN '$date_from' AND '$date_to 23:59:59'";
$total_orders = $conn->query($total_orders_query)->fetch_assoc()['count'];

$completed_orders_query = "SELECT COUNT(*) as count FROM `order` 
                           WHERE status = 'completed' 
                           AND order_date BETWEEN '$date_from' AND '$date_to 23:59:59'";
$completed_orders = $conn->query($completed_orders_query)->fetch_assoc()['count'];

$avg_order_value = $completed_orders > 0 ? $total_revenue / $completed_orders : 0;

// Top Selling Products
$top_products_query = "SELECT p.product_id, p.Name, c.category_name,
                       COUNT(oi.order_item_id) as times_ordered,
                       SUM(oi.quantity) as total_quantity,
                       SUM(oi.subtotal) as total_revenue
                       FROM product p
                       JOIN category c ON p.category_id = c.category_id
                       LEFT JOIN order_item oi ON p.product_id = oi.product_id
                       LEFT JOIN `order` o ON oi.order_id = o.order_id
                       WHERE o.order_date BETWEEN '$date_from' AND '$date_to 23:59:59'
                       GROUP BY p.product_id, p.Name, c.category_name
                       ORDER BY total_revenue DESC
                       LIMIT 10";
$top_products = $conn->query($top_products_query);

// Category Sales Report
$category_sales_query = "SELECT c.category_name,
                         COUNT(DISTINCT o.order_id) as order_count,
                         SUM(oi.quantity) as total_quantity,
                         SUM(oi.subtotal) as total_revenue
                         FROM category c
                         LEFT JOIN product p ON c.category_id = p.category_id
                         LEFT JOIN order_item oi ON p.product_id = oi.product_id
                         LEFT JOIN `order` o ON oi.order_id = o.order_id
                         WHERE o.order_date BETWEEN '$date_from' AND '$date_to 23:59:59'
                         GROUP BY c.category_id, c.category_name
                         ORDER BY total_revenue DESC";
$category_sales = $conn->query($category_sales_query);

// Customer Analytics
$top_customers_query = "SELECT u.user_id, u.Name, u.Email,
                        COUNT(o.order_id) as total_orders,
                        SUM(o.total_amount) as total_spent
                        FROM user u
                        JOIN `order` o ON u.user_id = o.user_id
                        WHERE o.order_date BETWEEN '$date_from' AND '$date_to 23:59:59'
                        AND o.status = 'completed'
                        GROUP BY u.user_id, u.Name, u.Email
                        ORDER BY total_spent DESC
                        LIMIT 10";
$top_customers = $conn->query($top_customers_query);

// Daily Sales Report
$daily_sales_query = "SELECT DATE(order_date) as sale_date,
                      COUNT(*) as order_count,
                      SUM(total_amount) as daily_revenue
                      FROM `order`
                      WHERE order_date BETWEEN '$date_from' AND '$date_to 23:59:59'
                      AND status = 'completed'
                      GROUP BY DATE(order_date)
                      ORDER BY sale_date DESC";
$daily_sales = $conn->query($daily_sales_query);

// Payment Methods Report
$payment_methods_query = "SELECT p.payment_method,
                          COUNT(*) as transaction_count,
                          SUM(p.amount) as total_amount
                          FROM payment p
                          JOIN `order` o ON p.order_id = o.order_id
                          WHERE o.order_date BETWEEN '$date_from' AND '$date_to 23:59:59'
                          AND p.payment_status = 'completed'
                          GROUP BY p.payment_method
                          ORDER BY total_amount DESC";
$payment_methods = $conn->query($payment_methods_query);

// Order Status Distribution
$order_status_query = "SELECT status, COUNT(*) as count
                       FROM `order`
                       WHERE order_date BETWEEN '$date_from' AND '$date_to 23:59:59'
                       GROUP BY status";
$order_status = $conn->query($order_status_query);

// Low Stock Products
$low_stock_query = "SELECT p.product_id, p.Name, c.category_name, p.stock_quantity, p.price
                    FROM product p
                    JOIN category c ON p.category_id = c.category_id
                    WHERE p.stock_quantity < 20 AND p.is_deleted = 0
                    ORDER BY p.stock_quantity ASC
                    LIMIT 10";
$low_stock_products = $conn->query($low_stock_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - The Drink Lab</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .report-tabs {
            background: white;
            padding: 1rem 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .report-tab {
            padding: 0.8rem 1.5rem;
            background: #f0f4f8;
            color: #1e3c72;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .report-tab:hover {
            background: #d9e2ec;
            transform: translateY(-2px);
        }
        
        .report-tab.active {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
        }
        
        .report-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .report-section h2 {
            color: #1e3c72;
            margin-bottom: 1.5rem;
            padding-bottom: 0.8rem;
            border-bottom: 3px solid #00d4ff;
        }
        
        .chart-placeholder {
            background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%);
            padding: 3rem;
            border-radius: 10px;
            text-align: center;
            color: #6b7280;
            font-size: 1.1rem;
            margin: 1rem 0;
        }
        
        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .export-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%);
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .summary-value {
            font-size: 2rem;
            font-weight: bold;
            color: #1e3c72;
            margin: 0.5rem 0;
        }
        
        .summary-label {
            font-size: 0.9rem;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .progress-bar {
            height: 8px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #059669);
            transition: width 0.3s ease;
        }
    </style>
</head>
<body class="gradient-bg">
    <div class="users-container">
        <div class="page-header">
            <div>
                <h1>📊 Reports & Analytics</h1>
                <p>Business insights and performance metrics</p>
            </div>
            <div style="display: flex; gap: 1rem;">
                <a href="../admin_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>
        </div>

        <!-- Date Filter -->
        <div class="filters-section">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="date_from">📅 Date From</label>
                        <input type="date" name="date_from" id="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="filter-group">
                        <label for="date_to">📅 Date To</label>
                        <input type="date" name="date_to" id="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="filter-group">
                        <label for="report_type">📋 Report Type</label>
                        <select name="report_type" id="report_type">
                            <option value="overview" <?php echo ($report_type == 'overview') ? 'selected' : ''; ?>>Overview</option>
                            <option value="sales" <?php echo ($report_type == 'sales') ? 'selected' : ''; ?>>Sales Details</option>
                            <option value="products" <?php echo ($report_type == 'products') ? 'selected' : ''; ?>>Products</option>
                            <option value="customers" <?php echo ($report_type == 'customers') ? 'selected' : ''; ?>>Customers</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary">Generate Report</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Key Metrics -->
        <div class="report-section">
            <h2>📈 Key Performance Metrics</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>💰 Total Revenue</h3>
                    <div class="stat-value">RM <?php echo number_format($total_revenue, 2); ?></div>
                    <small style="color: #10b981;">✓ Completed Orders</small>
                </div>
                <div class="stat-card">
                    <h3>📦 Total Orders</h3>
                    <div class="stat-value"><?php echo $total_orders; ?></div>
                    <small style="color: #6b7280;">All statuses</small>
                </div>
                <div class="stat-card">
                    <h3>✅ Completed Orders</h3>
                    <div class="stat-value"><?php echo $completed_orders; ?></div>
                    <small style="color: #10b981;">Successfully fulfilled</small>
                </div>
                <div class="stat-card">
                    <h3>📊 Avg Order Value</h3>
                    <div class="stat-value">RM <?php echo number_format($avg_order_value, 2); ?></div>
                    <small style="color: #6b7280;">Per completed order</small>
                </div>
            </div>
        </div>

        <!-- Order Status Distribution -->
        <div class="report-section">
            <h2>📊 Order Status Distribution</h2>
            <div class="summary-grid">
                <?php 
                $status_colors = [
                    'pending' => '#f59e0b',
                    'processing' => '#3b82f6',
                    'completed' => '#10b981',
                    'cancelled' => '#ef4444'
                ];
                while ($status = $order_status->fetch_assoc()): 
                    $percentage = $total_orders > 0 ? ($status['count'] / $total_orders) * 100 : 0;
                ?>
                <div class="summary-card">
                    <div class="summary-label"><?php echo ucfirst($status['status']); ?></div>
                    <div class="summary-value"><?php echo $status['count']; ?></div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%; background: <?php echo $status_colors[$status['status']] ?? '#6b7280'; ?>;"></div>
                    </div>
                    <small style="color: #6b7280;"><?php echo number_format($percentage, 1); ?>%</small>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Top Selling Products -->
        <div class="report-section">
            <h2>🏆 Top Selling Products</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Times Ordered</th>
                            <th>Total Quantity</th>
                            <th>Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        if ($top_products->num_rows > 0):
                            while ($product = $top_products->fetch_assoc()): 
                        ?>
                        <tr>
                            <td>
                                <?php if ($rank <= 3): ?>
                                    <span style="font-size: 1.5rem;">
                                        <?php echo $rank == 1 ? '🥇' : ($rank == 2 ? '🥈' : '🥉'); ?>
                                    </span>
                                <?php else: ?>
                                    <strong>#<?php echo $rank; ?></strong>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($product['Name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                            <td><?php echo $product['times_ordered']; ?></td>
                            <td><?php echo $product['total_quantity']; ?> units</td>
                            <td><strong style="color: #10b981;">RM <?php echo number_format($product['total_revenue'], 2); ?></strong></td>
                        </tr>
                        <?php 
                            $rank++;
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem; color: #6b7280;">
                                No sales data for the selected period
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Category Sales Report -->
        <div class="report-section">
            <h2>📂 Sales by Category</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Orders</th>
                            <th>Items Sold</th>
                            <th>Revenue</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($category_sales->num_rows > 0):
                            while ($category = $category_sales->fetch_assoc()): 
                                $cat_percentage = $total_revenue > 0 ? ($category['total_revenue'] / $total_revenue) * 100 : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($category['category_name']); ?></strong></td>
                            <td><?php echo $category['order_count']; ?></td>
                            <td><?php echo $category['total_quantity']; ?> units</td>
                            <td><strong style="color: #10b981;">RM <?php echo number_format($category['total_revenue'], 2); ?></strong></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div class="progress-bar" style="flex: 1;">
                                        <div class="progress-fill" style="width: <?php echo $cat_percentage; ?>%;"></div>
                                    </div>
                                    <span><?php echo number_format($cat_percentage, 1); ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem; color: #6b7280;">
                                No category sales data for the selected period
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Customers -->
        <div class="report-section">
            <h2>👥 Top Customers</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Customer Name</th>
                            <th>Email</th>
                            <th>Total Orders</th>
                            <th>Total Spent</th>
                            <th>Avg Order Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        if ($top_customers->num_rows > 0):
                            while ($customer = $top_customers->fetch_assoc()): 
                                $avg_value = $customer['total_orders'] > 0 ? $customer['total_spent'] / $customer['total_orders'] : 0;
                        ?>
                        <tr>
                            <td><strong>#<?php echo $rank; ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($customer['Name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($customer['Email']); ?></td>
                            <td><?php echo $customer['total_orders']; ?></td>
                            <td><strong style="color: #10b981;">RM <?php echo number_format($customer['total_spent'], 2); ?></strong></td>
                            <td>RM <?php echo number_format($avg_value, 2); ?></td>
                        </tr>
                        <?php 
                            $rank++;
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem; color: #6b7280;">
                                No customer data for the selected period
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Daily Sales Report -->
        <div class="report-section">
            <h2>📅 Daily Sales Report</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Number of Orders</th>
                            <th>Daily Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($daily_sales->num_rows > 0):
                            while ($daily = $daily_sales->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><strong><?php echo date('l, M d, Y', strtotime($daily['sale_date'])); ?></strong></td>
                            <td><?php echo $daily['order_count']; ?> orders</td>
                            <td><strong style="color: #10b981;">RM <?php echo number_format($daily['daily_revenue'], 2); ?></strong></td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 2rem; color: #6b7280;">
                                No daily sales data for the selected period
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payment Methods Report -->
        <div class="report-section">
            <h2>💳 Payment Methods Report</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Payment Method</th>
                            <th>Transactions</th>
                            <th>Total Amount</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_payment_amount = 0;
                        $payment_data = [];
                        while ($payment = $payment_methods->fetch_assoc()) {
                            $total_payment_amount += $payment['total_amount'];
                            $payment_data[] = $payment;
                        }
                        
                        if (count($payment_data) > 0):
                            foreach ($payment_data as $payment): 
                                $payment_percentage = $total_payment_amount > 0 ? ($payment['total_amount'] / $total_payment_amount) * 100 : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $payment['payment_method']))); ?></strong></td>
                            <td><?php echo $payment['transaction_count']; ?> transactions</td>
                            <td><strong style="color: #10b981;">RM <?php echo number_format($payment['total_amount'], 2); ?></strong></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div class="progress-bar" style="flex: 1;">
                                        <div class="progress-fill" style="width: <?php echo $payment_percentage; ?>%;"></div>
                                    </div>
                                    <span><?php echo number_format($payment_percentage, 1); ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            endforeach;
                        else:
                        ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 2rem; color: #6b7280;">
                                No payment data for the selected period
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Low Stock Alert -->
        <div class="report-section">
            <h2>⚠️ Low Stock Inventory Alert</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Price</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($low_stock_products->num_rows > 0):
                            while ($product = $low_stock_products->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($product['Name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                            <td class="low-stock-warning"><?php echo $product['stock_quantity']; ?> units</td>
                            <td>RM <?php echo number_format($product['price'], 2); ?></td>
                            <td>
                                <?php if ($product['stock_quantity'] == 0): ?>
                                    <span class="status-badge status-cancelled">Out of Stock</span>
                                <?php else: ?>
                                    <span class="status-badge status-pending">Low Stock</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem; color: #10b981;">
                                ✅ All products have sufficient stock!
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($low_stock_products->num_rows > 0): ?>
            <div style="margin-top: 1rem;">
                <a href="view_product.php" class="btn btn-warning">🔄 Restock Products</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Summary Section -->
        <div class="report-section">
            <h2>📋 Report Summary</h2>
            <div class="summary-grid" style="grid-template-columns: 1fr;">
                <div style="background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%); padding: 2rem; border-radius: 10px;">
                    <h3 style="color: #1e3c72; margin-bottom: 1rem;">Period: <?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?></h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-top: 1rem;">
                        <div>
                            <div style="font-size: 0.9rem; color: #6b7280; font-weight: 600;">Total Revenue</div>
                            <div style="font-size: 1.8rem; font-weight: bold; color: #10b981;">RM <?php echo number_format($total_revenue, 2); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 0.9rem; color: #6b7280; font-weight: 600;">Total Orders</div>
                            <div style="font-size: 1.8rem; font-weight: bold; color: #1e3c72;"><?php echo $total_orders; ?></div>
                        </div>
                        <div>
                            <div style="font-size: 0.9rem; color: #6b7280; font-weight: 600;">Completion Rate</div>
                            <div style="font-size: 1.8rem; font-weight: bold; color: #3b82f6;">
                                <?php echo $total_orders > 0 ? number_format(($completed_orders / $total_orders) * 100, 1) : 0; ?>%
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 0.9rem; color: #6b7280; font-weight: 600;">Avg Order Value</div>
                            <div style="font-size: 1.8rem; font-weight: bold; color: #f59e0b;">RM <?php echo number_format($avg_order_value, 2); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="export-buttons">
                <button onclick="window.print()" class="btn btn-primary">🖨️ Print Report</button>
                <button onclick="alert('Export functionality would be implemented here')" class="btn btn-secondary">📥 Export to Excel</button>
                <button onclick="alert('PDF export would be implemented here')" class="btn btn-secondary">📄 Export to PDF</button>
            </div>
        </div>

    </div>
</body>
</html>
<?php $conn->close(); ?>