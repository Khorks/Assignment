<?php
session_start();
include '../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    $pass_sql = "";
    if (!empty($_POST['password'])) {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $pass_sql = ", Password = '$hash'";
    }

    $sql = "UPDATE user SET Name='$name', phone_number='$phone', address='$address', Email='$email' $pass_sql WHERE user_id=$user_id";
    
    if ($conn->query($sql)) {
        $_SESSION['name'] = $name; 
        $msg = "<div class='alert alert-success'>Profile updated successfully!</div>";
    } else {
        $msg = "<div class='alert alert-error'>Error updating profile: " . $conn->error . "</div>";
    }
}

// Fetch User Data
$result = $conn->query("SELECT * FROM user WHERE user_id = $user_id");

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    // Handle error if user not found (e.g., account deleted)
    session_destroy();
    header('Location: ../auth/login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body class="gradient-bg">
    <div class="container">
        <div class="content-card" style="max-width: 600px; margin: 2rem auto;">
            <h2>✏️ Edit Profile</h2>
            <?php echo $msg; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['Name']); ?>" required style="width: 100%; padding: 10px;">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['Email']); ?>" required style="width: 100%; padding: 10px;">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" style="width: 100%; padding: 10px;">
                </div>
                <div class="form-group">
                    <label>Default Address</label>
                    <textarea name="address" rows="3" style="width: 100%; padding: 10px;"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label>New Password (leave blank to keep current)</label>
                    <input type="password" name="password" style="width: 100%; padding: 10px;">
                </div>
                
                <div class="form-actions" style="margin-top: 2rem; display: flex; gap: 15px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1; padding: 12px; font-size: 1rem;">Save Changes</button>
                    <a href="../customer_dashboard.php" class="btn btn-secondary" style="flex: 1; padding: 12px; font-size: 1rem; text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center;">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>