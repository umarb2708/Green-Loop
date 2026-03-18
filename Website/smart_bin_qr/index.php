<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? 'admin_dashboard.php' : 'user_dashboard.php'));
    exit();
}

$error   = '';
$success = '';

// ── Login ──────────────────────────────────────────────────────────────────────
if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username !== '' && $password !== '') {
        $conn = getDBConnection();
        $u    = $conn->real_escape_string($username);
        $row  = $conn->query("SELECT * FROM users WHERE username='$u' LIMIT 1")->fetch_assoc();
        $conn->close();

        if ($row && password_verify($password, $row['password'])) {
            $_SESSION['user_id']  = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['name']     = $row['name'];
            $_SESSION['is_admin'] = $row['is_admin'];
            header('Location: ' . ($row['is_admin'] ? 'admin_dashboard.php' : 'user_dashboard.php'));
            exit();
        } else {
            $error = 'Invalid username or password';
        }
    } else {
        $error = 'Please fill in all fields';
    }
}

// ── Signup ─────────────────────────────────────────────────────────────────────
if (isset($_POST['signup'])) {
    $name     = trim($_POST['name']     ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password']         ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($username) || empty($password)) {
        $error = 'All fields are required';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        $conn = getDBConnection();
        $n = $conn->real_escape_string($name);
        $u = $conn->real_escape_string($username);

        $exists = $conn->query("SELECT id FROM users WHERE username='$u' LIMIT 1");
        if ($exists->num_rows > 0) {
            $error = 'Username already taken';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $conn->query("INSERT INTO users (name, username, password, is_admin, rewards_collected)
                          VALUES ('$n','$u','$hash',0,0)");
            $success = 'Account created! Please log in.';
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
    <title>Green Loop – Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <div class="logo">
        <h1>🌱 Green Loop</h1>
        <p>Smart Plastic Collection System</p>
    </div>

    <?php if ($error):   ?><div class="message error"><?php echo htmlspecialchars($error);   ?></div><?php endif; ?>
    <?php if ($success): ?><div class="message success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <!-- Tabs -->
    <div class="tab-buttons">
        <button class="tab-button <?php echo !isset($_POST['signup']) ? 'active' : ''; ?>"
                onclick="switchTab('login')">Login</button>
        <button class="tab-button <?php echo  isset($_POST['signup']) ? 'active' : ''; ?>"
                onclick="switchTab('signup')">Sign Up</button>
    </div>

    <!-- Login Tab -->
    <div id="login-tab" class="tab-content <?php echo !isset($_POST['signup']) ? 'active' : ''; ?>">
        <form method="POST" action="">
            <div class="form-group">
                <label for="login_username">Username</label>
                <input type="text" id="login_username" name="username"
                       placeholder="Enter your username" required>
            </div>
            <div class="form-group">
                <label for="login_password">Password</label>
                <input type="password" id="login_password" name="password"
                       placeholder="Enter your password" required>
            </div>
            <button type="submit" name="login" class="btn">Login</button>
        </form>
    </div>

    <!-- Signup Tab -->
    <div id="signup-tab" class="tab-content <?php echo isset($_POST['signup']) ? 'active' : ''; ?>">
        <form method="POST" action="">
            <div class="form-group">
                <label for="signup_name">Full Name</label>
                <input type="text" id="signup_name" name="name"
                       placeholder="Your full name" required>
            </div>
            <div class="form-group">
                <label for="signup_username">Username</label>
                <input type="text" id="signup_username" name="username"
                       placeholder="Choose a username" required>
            </div>
            <div class="form-group">
                <label for="signup_password">Password</label>
                <input type="password" id="signup_password" name="password"
                       placeholder="Minimum 6 characters" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password"
                       placeholder="Repeat your password" required>
            </div>
            <button type="submit" name="signup" class="btn">Create Account</button>
        </form>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-button').forEach((b,i) =>
        b.classList.toggle('active', (tab==='login'&&i===0)||(tab==='signup'&&i===1)));
    document.getElementById('login-tab').classList.toggle('active',  tab==='login');
    document.getElementById('signup-tab').classList.toggle('active', tab==='signup');
}
</script>
</body>
</html>
