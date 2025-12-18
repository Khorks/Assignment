<?php
session_start();
include '../db_connect.php';

if (!isset($_GET['id'])) header('Location: view_order.php');
$order_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Get Status
$query = "SELECT d.delivery_status, d.tracking_number, o.status as order_status 
          FROM delivery d JOIN `order` o ON d.order_id = o.order_id 
          WHERE d.order_id = $order_id AND o.user_id = $user_id";
$result = $conn->query($query);
$data = $result->fetch_assoc();

if (!$data) die("Order not found");

// Determine progress step
$status = $data['delivery_status'];
$step = 1;
if ($status == 'processing') $step = 2;
if ($status == 'shipped') $step = 3;
if ($status == 'delivered') $step = 4;
if ($status == 'cancelled') $step = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Track Order #<?php echo $order_id; ?></title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .track-container { display: flex; justify-content: space-between; position: relative; margin: 3rem 0; }
        .track-line { position: absolute; top: 15px; left: 0; width: 100%; height: 4px; background: #e0e0e0; z-index: 1; }
        .track-fill { position: absolute; top: 15px; left: 0; height: 4px; background: #10b981; z-index: 2; transition: width 0.5s; }
        .track-step { position: relative; z-index: 3; text-align: center; }
        .dot { width: 30px; height: 30px; background: #e0e0e0; border-radius: 50%; margin: 0 auto 10px; border: 4px solid white; }
        .active .dot { background: #10b981; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.2); }
    </style>
</head>
<body class="gradient-bg">
    <div class="container">
        <div class="content-card" style="max-width: 800px; margin: 2rem auto;">
            <h2 class="text-center">🚚 Tracking Order #<?php echo $order_id; ?></h2>
            <p class="text-center" style="color: #666;">Current Status: <strong><?php echo ucfirst($status); ?></strong></p>
            
            <?php if($status !== 'cancelled'): ?>
            <div class="track-container">
                <div class="track-line"></div>
                <div class="track-fill" style="width: <?php echo ($step - 1) * 33; ?>%;"></div>
                
                <div class="track-step <?php echo $step >= 1 ? 'active' : ''; ?>">
                    <div class="dot"></div>
                    <div>Pending</div>
                </div>
                <div class="track-step <?php echo $step >= 2 ? 'active' : ''; ?>">
                    <div class="dot"></div>
                    <div>Processing</div>
                </div>
                <div class="track-step <?php echo $step >= 3 ? 'active' : ''; ?>">
                    <div class="dot"></div>
                    <div>Shipped</div>
                </div>
                <div class="track-step <?php echo $step >= 4 ? 'active' : ''; ?>">
                    <div class="dot"></div>
                    <div>Delivered</div>
                </div>
            </div>
            <?php else: ?>
                <div class="alert alert-error text-center" style="margin-top: 2rem;">🚫 This order has been cancelled.</div>
            <?php endif; ?>

            <?php if($data['tracking_number']): ?>
                <div style="background: #f9fafb; padding: 1rem; border-radius: 8px; text-align: center; margin-top: 2rem;">
                    Tracking Number: <strong style="color: #1e3c72;"><?php echo $data['tracking_number']; ?></strong>
                </div>
            <?php endif; ?>

            <div class="text-center" style="margin-top: 2rem;">
                <a href="view_order.php?id=<?php echo $order_id; ?>" class="btn btn-secondary">Back to Details</a>
            </div>
        </div>
    </div>
</body>
</html>