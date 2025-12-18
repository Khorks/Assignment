<?php
session_start();
include '../db_connect.php';

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password!";
    } else {
        $sql = "SELECT user_id, Name, Email, password, role, is_active FROM user WHERE Email = '$email'";
        $result = $conn->query($sql);
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            if ($user['is_active'] == 0) {
                $error_message = "Your account has been deactivated. Please contact support.";
            } elseif (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['Name'];
                $_SESSION['email'] = $user['Email'];
                $_SESSION['role'] = $user['role'];
                
                // Redirect based on role
                if ($user['role'] == 'admin') {
                    header("Location: ../admin_dashboard.php");
                } elseif ($user['role'] == 'staff') {
                    header("Location: ../staff_dashboard.php");
                } else {
                    // Customer stays on homepage
                    header("Location: ../homepage.php");
                }
                exit();
            } else {
                $error_message = "Invalid email or password!";
            }
        } else {
            $error_message = "Invalid email or password!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - The Drink Lab</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .login-container {
            max-width: 450px;
            margin: 4rem auto; /* Added margin top/bottom to center it vertically a bit more */
            padding: 0 1rem;
        }

        /* ADDED: White Box Style */
        .form-container {
            background: #ffffff;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2); /* Drop shadow */
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-header h1 {
            color: #1e3c72; /* Matched to primary theme color */
            margin-bottom: 0.5rem;
            font-size: 2rem;
        }

        .form-header p {
            color: #666;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
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

        .form-group input {
            width: 100%;
            padding: 0.9rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
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
    </style>
</head>
<body class="gradient-bg">
    <div class="container login-container">
        
        <!-- White Box Container -->
        <div class="form-container">
            <div class="form-header">
                <h1>🥤 Welcome Back</h1>
                <p>Login to your account</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" 
                           placeholder="Enter your email" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" 
                           placeholder="Enter your password" 
                           required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Login</button>

                <div class="form-footer">
                    Don't have an account? <a href="register.php">Register here</a>
                </div>
            </form>
        </div>
        <!-- End White Box -->

        <div class="back-home">
            <a href="../homepage.php">← Back to Homepage</a>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>