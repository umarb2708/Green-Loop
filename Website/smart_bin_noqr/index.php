<?php
require_once 'config.php';

// If already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    if (isAdmin()) {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: user_dashboard.php");
    }
    exit();
}

$error = '';
$success = '';

// Handle Login
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $conn = getDBConnection();
    $username = $conn->real_escape_string($username);
    
    $sql = "SELECT * FROM users WHERE username = '$username'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['is_admin'] = $user['is_admin'];
            
            $conn->close();
            
            if ($user['is_admin'] == 1) {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: user_dashboard.php");
            }
            exit();
        } else {
            $error = "Invalid username or password";
        }
    } else {
        $error = "Invalid username or password";
    }
    
    $conn->close();
}

// Handle Signup
if (isset($_POST['signup'])) {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($name) || empty($username) || empty($password)) {
        $error = "All fields are required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } else {
        $conn = getDBConnection();
        $name = $conn->real_escape_string($name);
        $username = $conn->real_escape_string($username);
        
        // Check if username already exists
        $sql = "SELECT id FROM users WHERE username = '$username'";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            $error = "Username already exists";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO users (name, username, password, is_admin, rewards_collected) 
                    VALUES ('$name', '$username', '$hashed_password', 0, 0)";
            
            if ($conn->query($sql)) {
                $success = "Account created successfully! Please login.";
            } else {
                $error = "Error creating account: " . $conn->error;
            }
        }
        
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Green Loop - Smart Plastic Collection</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>🌱 Green Loop</h1>
            <p>Smart Plastic Collection System</p>
        </div>

        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="tab-buttons">
            <button class="tab-button active" onclick="showTab('login')">Login</button>
            <button class="tab-button" onclick="showTab('signup')">Sign Up</button>
        </div>

        <!-- Login Form -->
        <div id="login" class="tab-content active">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="login-username">Username</label>
                    <input type="text" id="login-username" name="username" required>
                </div>

                <div class="form-group">
                    <label for="login-password">Password</label>
                    <input type="password" id="login-password" name="password" required>
                </div>

                <button type="submit" name="login" class="btn">Login</button>
            </form>
        </div>

        <!-- Signup Form -->
        <div id="signup" class="tab-content">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="signup-name">Name</label>
                    <input type="text" id="signup-name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="signup-username">Username</label>
                    <input type="text" id="signup-username" name="username" required>
                </div>

                <div class="form-group">
                    <label for="signup-password">Password</label>
                    <input type="password" id="signup-password" name="password" required minlength="6">
                </div>

                <div class="form-group">
                    <label for="confirm-password">Confirm Password</label>
                    <input type="password" id="confirm-password" name="confirm_password" required>
                </div>

                <button type="submit" name="signup" class="btn">Create Account</button>
            </form>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tabs
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));

            // Remove active class from all buttons
            const buttons = document.querySelectorAll('.tab-button');
            buttons.forEach(btn => btn.classList.remove('active'));

            // Show selected tab
            document.getElementById(tabName).classList.add('active');

            // Activate corresponding button
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
