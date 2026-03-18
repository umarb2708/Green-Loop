<?php
require_once 'config.php';
requireLogin();

// Ensure this is not admin
if (isAdmin()) {
    header("Location: admin_dashboard.php");
    exit();
}

$message = '';
$messageType = '';

// Handle Add Reward
if (isset($_POST['add_reward'])) {
    $reward_code = trim(strtoupper($_POST['reward_code']));
    
    if (!empty($reward_code)) {
        $conn = getDBConnection();
        $user_id = $_SESSION['user_id'];
        $reward_code = $conn->real_escape_string($reward_code);
        
        // Check if reward code exists
        $sql = "SELECT * FROM rewards_data WHERE unique_code = '$reward_code'";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            $reward = $result->fetch_assoc();
            
            // Check if already collected
            if ($reward['collected'] == 1) {
                $message = "This reward code has already been collected!";
                $messageType = 'error';
            } else {
                // Get current user rewards
                $sql = "SELECT rewards_collected FROM users WHERE id = $user_id";
                $user_result = $conn->query($sql);
                $user = $user_result->fetch_assoc();
                
                $new_total = $user['rewards_collected'] + $reward['points'];
                
                // Update user rewards
                $sql = "UPDATE users SET rewards_collected = $new_total WHERE id = $user_id";
                $conn->query($sql);
                
                // Mark reward as collected
                $sql = "UPDATE rewards_data SET collected = 1 WHERE unique_code = '$reward_code'";
                $conn->query($sql);
                
                $_SESSION['rewards_collected'] = $new_total;
                
                $message = "Success! Added " . $reward['points'] . " points to your account.";
                $messageType = 'success';
            }
        } else {
            $message = "Invalid reward code!";
            $messageType = 'error';
        }
        
        $conn->close();
    }
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Get current user data
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = $user_id";
$result = $conn->query($sql);
$user_data = $result->fetch_assoc();

// Get reward history
$sql = "SELECT r.*, b.location FROM rewards_data r 
        LEFT JOIN bin_data b ON r.bin_id = b.id 
        WHERE r.collected = 1 
        ORDER BY r.created_at DESC LIMIT 20";
$history_result = $conn->query($sql);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Green Loop</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div>
                <h1>🌱 Green Loop</h1>
                <p>Welcome, <?php echo $_SESSION['name']; ?></p>
            </div>
            <a href="?logout=1" class="logout-btn">Logout</a>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- Current Rewards -->
        <div class="stat-card">
            <h3><?php echo number_format($user_data['rewards_collected']); ?></h3>
            <p>Total Reward Points</p>
        </div>

        <!-- Action Buttons -->
        <div class="button-grid">
            <button class="btn btn-success" onclick="openModal('addRewardModal')">➕ Add New Rewards</button>
            <button class="btn btn-secondary" onclick="alert('Feature coming soon!')">🎁 Claim Rewards</button>
        </div>

        <!-- Reward History -->
        <div class="card">
            <h2>Recent Activity</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Code</th>
                        <th>Points</th>
                        <th>Location</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($history_result->num_rows > 0): ?>
                        <?php while ($row = $history_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                            <td><?php echo $row['unique_code']; ?></td>
                            <td><?php echo $row['points']; ?></td>
                            <td><?php echo $row['location'] ?? 'Unknown'; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: #999;">No rewards claimed yet</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Environmental Impact -->
        <div class="card">
            <h2>Your Environmental Impact</h2>
            <div style="padding: 20px; text-align: center;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div style="padding: 20px; background: #e8f5e9; border-radius: 10px;">
                        <h3 style="color: #2e7d32; font-size: 2em;"><?php echo ceil($user_data['rewards_collected'] / 15); ?></h3>
                        <p style="color: #666;">Bottles Recycled</p>
                    </div>
                    <div style="padding: 20px; background: #e3f2fd; border-radius: 10px;">
                        <h3 style="color: #1565c0; font-size: 2em;"><?php echo number_format($user_data['rewards_collected'] * 0.05, 1); ?></h3>
                        <p style="color: #666;">kg CO₂ Saved</p>
                    </div>
                    <div style="padding: 20px; background: #fff3e0; border-radius: 10px;">
                        <h3 style="color: #e65100; font-size: 2em;"><?php echo number_format($user_data['rewards_collected'] * 0.03, 1); ?></h3>
                        <p style="color: #666;">Liters Oil Saved</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Reward Modal -->
    <div id="addRewardModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Rewards</h2>
                <button class="close-btn" onclick="closeModal('addRewardModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="reward_code">Reward Code</label>
                    <input type="text" id="reward_code" name="reward_code" required 
                           placeholder="Enter 6-digit code from bin" 
                           maxlength="6" 
                           style="text-transform: uppercase;">
                </div>
                <button type="submit" name="add_reward" class="btn">Add Rewards</button>
            </form>
            <div style="margin-top: 20px; padding: 15px; background: #f5f5f5; border-radius: 8px; font-size: 0.9em; color: #666;">
                <strong>How to get a reward code:</strong>
                <ol style="margin: 10px 0 0 20px;">
                    <li>Visit a Green Loop smart bin</li>
                    <li>Scan your plastic QR code</li>
                    <li>Dispose your plastic</li>
                    <li>Get your 6-digit reward code from the screen</li>
                </ol>
            </div>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('active');
                }
            });
        }

        // Auto-uppercase reward code input
        document.getElementById('reward_code').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase();
        });
    </script>
</body>
</html>
