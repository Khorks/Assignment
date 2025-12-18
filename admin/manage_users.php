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

// Check for session messages
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

// Get all users with search and filter
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$role_filter = isset($_GET['role']) ? mysqli_real_escape_string($conn, $_GET['role']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

$query = "SELECT * FROM user WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (Name LIKE '%$search%' OR Email LIKE '%$search%')";
}

if (!empty($role_filter)) {
    $query .= " AND role = '$role_filter'";
}

if (!empty($status_filter)) {
    if ($status_filter === 'active') {
        $query .= " AND is_active = 1";
    } elseif ($status_filter === 'inactive') {
        $query .= " AND is_active = 0";
    }
}

$query .= " ORDER BY created_at DESC";
$users = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - The Drink Lab Admin</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        /* Force equal height and alignment for filter buttons */
        .filter-buttons {
            display: flex;
            gap: 10px;
            height: 100%; /* Match parent height */
            align-items: flex-end; /* Align to bottom like inputs */
        }
        
        .filter-btn-shared {
            flex: 1;
            padding: 0.9rem; /* Same padding as inputs */
            height: 52px; /* Fixed height matching standard input height */
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box; /* Includes padding in height */
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="gradient-bg">
    <div class="users-container">
        <div class="page-header">
            <div>
                <h1>👥 Manage Users</h1>
                <p>View and manage all system users</p>
            </div>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="add_user.php" class="btn btn-success">➕ Add New User</a>
                <a href="../admin_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" action="">
                <div class="filter-row" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 20px; align-items: end;">
                    <div class="filter-group">
                        <label for="search">🔍 Search Users</label>
                        <input type="text" name="search" id="search" 
                               placeholder="Search by name or email..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="role">👤 Filter by Role</label>
                        <select name="role" id="role">
                            <option value="">All Roles</option>
                            <option value="admin" <?php echo ($role_filter === 'admin') ? 'selected' : ''; ?>>Admin</option>
                            <option value="staff" <?php echo ($role_filter === 'staff') ? 'selected' : ''; ?>>Staff</option>
                            <option value="customer" <?php echo ($role_filter === 'customer') ? 'selected' : ''; ?>>Customer</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="status">📊 Filter by Status</label>
                        <select name="status" id="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($status_filter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <!-- CHANGED: Buttons container -->
                    <div class="filter-group">
                        <div class="filter-buttons">
                            <!-- Filter Button -->
                            <button type="submit" class="btn-primary filter-btn-shared">
                                🔬 Filter
                            </button>
                            
                            <!-- Clear Button -->
                            <a href="manage_users.php" class="btn-secondary filter-btn-shared">
                                Clear
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Users Grid -->
        <div class="users-grid-container">
            <div class="users-grid-header">
                <h2>All Users (<?php echo $users->num_rows; ?>)</h2>
            </div>
            
            <?php if ($users->num_rows > 0): ?>
                <div class="users-grid">
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <div class="user-card">
                            <div class="user-card-header">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user['Name'], 0, 2)); ?>
                                </div>
                                <div class="user-card-badges">
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                    <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $user['is_active'] ? '✓ Active' : '✗ Inactive'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="user-card-body">
                                <h3 class="user-card-name"><?php echo htmlspecialchars($user['Name']); ?></h3>
                                <p class="user-card-email">📧 <?php echo htmlspecialchars($user['Email']); ?></p>
                                <p class="user-card-phone">
                                    📱 <?php echo htmlspecialchars($user['phone_number'] ?? 'No phone'); ?>
                                </p>
                                <p class="user-card-date">
                                    📅 Joined: <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </p>
                            </div>
                            
                            <div class="user-card-footer">
                                <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                    <a href="user_details.php?id=<?php echo $user['user_id']; ?>" 
                                       class="btn btn-primary btn-block">
                                        ✏️ Edit User
                                    </a>
                                <?php else: ?>
                                    <a href="user_details.php?id=<?php echo $user['user_id']; ?>" 
                                       class="btn btn-secondary btn-block">
                                        👤 View Profile
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-users-found">
                    <div class="no-users-icon">👥</div>
                    <h3>No Users Found</h3>
                    <p>Try adjusting your search or filter criteria</p>
                    <a href="manage_users.php" class="btn btn-primary">Clear Filters</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>