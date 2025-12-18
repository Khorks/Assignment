<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    switch($_SESSION['role']) {
        case 'admin':
            header("Location: ../admin_dashboard.php");
            break;
        case 'staff':
            header("Location: ../staff_dashboard.php");
            break;
        case 'customer':
            header("Location: ../customer_dashboard.php");
            break;
    }
    exit();
}

include '../db_connect.php';

$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));
    
    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "All required fields must be filled!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format!";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long!";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match!";
    } else {
        // Check if email already exists
        $check_email = "SELECT user_id FROM user WHERE Email = '$email'";
        $result = $conn->query($check_email);
        
        if ($result->num_rows > 0) {
            $error_message = "Email already registered!";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $sql = "INSERT INTO user (Name, Email, password, phone_number, address, role) 
                    VALUES ('$name', '$email', '$hashed_password', '$phone', '$address', 'customer')";
            
            if ($conn->query($sql) === TRUE) {
                $success_message = "Registration successful! You can now login.";
            } else {
                $error_message = "Error: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - The Drink Lab</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .register-container {
            max-width: 500px;
            margin: 4rem auto; /* More margin for vertical centering */
            padding: 0 1rem;
        }

        /* ADDED: White Box Style (Same as Login Page) */
        .form-container {
            background: #ffffff;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-header h1 {
            color: #1e3c72;
            margin-bottom: 0.5rem;
            font-size: 2rem;
        }

        .form-header p {
            color: #666;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 0.9rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit; /* Ensures font matches for textarea */
        }

        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.1);
        }

        .btn-block {
            width: 100%;
            padding: 0.9rem;
            font-size: 1.1rem;
        }

        .form-footer {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #f0f0f0;
            color: #666;
        }

        .form-footer a {
            color: #1e3c72;
            text-decoration: none;
            font-weight: 600;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .back-home {
            text-align: center;
            margin-top: 1.5rem;
        }

        .back-home a {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-home a:hover {
            color: white;
            text-decoration: underline;
        }
        
        .required {
            color: #ef4444;
        }
    </style>
</head>
<body class="gradient-bg">
    <div class="container register-container">
        
        <!-- White Box Container -->
        <div class="form-container">
            <div class="form-header">
                <h1>🥤 Create Account</h1>
                <p>Join The Drink Lab and start ordering!</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                    <br><br>
                    <!-- FIXED: Changed link to login.php -->
                    <a href="login.php" class="btn btn-primary" style="display:inline-block; margin-top:10px;">Go to Login</a>
                </div>
            <?php else: ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Full Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" 
                           placeholder="Enter your full name" 
                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" 
                           placeholder="Enter your email" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" 
                           placeholder="e.g., 0123456789" 
                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="address">Delivery Address</label>
                    <textarea id="address" name="address" rows="3" 
                              placeholder="Enter your delivery address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" 
                           placeholder="At least 6 characters" 
                           required>
                    <small style="color: #666; font-size: 0.85rem; margin-top: 5px; display: block;">Password must be at least 6 characters long</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" 
                           placeholder="Re-enter your password" 
                           required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Register</button>

                <div class="form-footer">
                    <!-- FIXED: Changed href="login.html" to href="login.php" -->
                    Already have an account? <a href="login.php">Login here</a>
                </div>
            </form>

            <?php endif; ?>
        </div>
        <!-- End White Box -->

        <div class="back-home">
            <a href="../homepage.php">← Back to Homepage</a>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>