<?php
/**
 * Green Loop - Login & Signup Page
 * Version 3.0
 */

require_once 'config.php';

// If already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin_dashboard.php');
    } elseif (isManufacturer()) {
        redirect('manufacturer_dashboard.php');
    } else {
        redirect('user_dashboard.php');
    }
}

$error = '';
$success = '';
$showSignup = isset($_GET['signup']);

// Handle Login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['is_admin'] = $user['is_admin'];
            $_SESSION['last_activity'] = time();
            
            // Log activity
            logActivity($user['id'], 'login', 'User logged in');
            
            // Redirect based on user type
            if ($user['is_admin'] == 1) {
                redirect('admin_dashboard.php');
            } elseif ($user['is_admin'] == 2) {
                redirect('manufacturer_dashboard.php');
            } else {
                redirect('user_dashboard.php');
            }
        } else {
            $error = 'Invalid username or password';
        }
    }
}

// Handle Signup
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['signup'])) {
    $name = sanitize($_POST['name']);
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if (empty($name) || empty($username) || empty($password)) {
        $error = 'All fields are required';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        // Check if username already exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetchColumn() > 0) {
            $error = 'Username already exists';
        } else {
            // Create new user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, username, password, is_admin, rewards_collected) VALUES (?, ?, ?, 0, 0)");
            
            if ($stmt->execute([$name, $username, $hashedPassword])) {
                $success = 'Account created successfully! Please login.';
                $showSignup = false;
                
                // Log activity
                $newUserId = $conn->lastInsertId();
                logActivity($newUserId, 'signup', 'New user registered');
            } else {
                $error = 'Error creating account. Please try again.';
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
    <title><?php echo APP_NAME; ?> - Login</title>
    <link rel="stylesheet" href="style/main.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        
        .header {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .form-container {
            padding: 40px 30px;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 16px;
            font-weight: 600;
            color: #999;
            transition: all 0.3s;
        }
        
        .tab.active {
            color: #2ecc71;
            border-bottom: 3px solid #2ecc71;
            margin-bottom: -2px;
        }
        
        .form-section {
            display: none;
        }
        
        .form-section.active {
            display: block;
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #2ecc71;
            box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.1);
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            background: #f9f9f9;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🌱 Green Loop</h1>
            <p>Smart Plastic Collection System</p>
        </div>
        
        <div class="form-container">
            <div class="tabs">
                <button class="tab <?php echo !$showSignup ? 'active' : ''; ?>" onclick="showTab('login')">Login</button>
                <button class="tab <?php echo $showSignup ? 'active' : ''; ?>" onclick="showTab('signup')">Sign Up</button>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (isset($_GET['timeout'])): ?>
                <div class="alert alert-error">Session expired. Please login again.</div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <div id="login-form" class="form-section <?php echo !$showSignup ? 'active' : ''; ?>">
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" required autocomplete="username">
                    </div>
                    
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required autocomplete="current-password">
                    </div>
                    
                    <button type="submit" name="login" class="btn">Login</button>
                </form>
            </div>
            
            <!-- Signup Form -->
            <div id="signup-form" class="form-section <?php echo $showSignup ? 'active' : ''; ?>">
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" required autocomplete="username">
                    </div>
                    
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required autocomplete="new-password" minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" required autocomplete="new-password" minlength="6">
                    </div>
                    
                    <button type="submit" name="signup" class="btn">Create Account</button>
                </form>
            </div>
        </div>
        
        <div class="footer">
            <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?> &copy; <?php echo date('Y'); ?>
        </div>
    </div>
    
    <script>
        function showTab(tab) {
            // Hide all sections
            document.querySelectorAll('.form-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected section and activate tab
            if (tab === 'login') {
                document.getElementById('login-form').classList.add('active');
                document.querySelectorAll('.tab')[0].classList.add('active');
            } else {
                document.getElementById('signup-form').classList.add('active');
                document.querySelectorAll('.tab')[1].classList.add('active');
            }
        }
    </script>
</body>
</html>
