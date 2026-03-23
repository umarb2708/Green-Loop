<?php
/**
 * Green Loop - User Dashboard
 * Version 3.0
 */

require_once 'config.php';
requireUser();

$user = getCurrentUser();
$error = '';
$success = '';

// Handle Reward Code Collection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['collect_reward'])) {
    $rewardCode = sanitize(strtoupper($_POST['reward_code']));
    
    if (empty($rewardCode)) {
        $error = 'Please enter a reward code';
    } else {
        // Check if reward code exists
        $stmt = $conn->prepare("SELECT * FROM rewards_data WHERE unique_code = ?");
        $stmt->execute([$rewardCode]);
        $reward = $stmt->fetch();
        
        if (!$reward) {
            $error = 'Invalid reward code';
        } elseif ($reward['collected'] == 1) {
            $error = 'This reward code has already been collected';
        } else {
            // Collect the reward
            $conn->beginTransaction();
            
            try {
                // Update user's rewards
                $stmt = $conn->prepare("UPDATE users SET rewards_collected = rewards_collected + ? WHERE id = ?");
                $stmt->execute([$reward['points'], $user['id']]);
                
                // Mark reward as collected
                $stmt = $conn->prepare("UPDATE rewards_data SET collected = 1, collected_by = ?, collected_at = NOW() WHERE id = ?");
                $stmt->execute([$user['id'], $reward['id']]);
                
                $conn->commit();
                
                // Log activity
                logActivity($user['id'], 'collect_reward', "Collected {$reward['points']} points with code {$rewardCode}");
                
                $success = "Successfully collected {$reward['points']} points!";
                
                // Refresh user data
                $user = getCurrentUser();
                
            } catch (Exception $e) {
                $conn->rollBack();
                $error = 'Error collecting reward. Please try again.';
            }
        }
    }
}

// Get recent rewards collected
$stmt = $conn->prepare("SELECT r.*, b.location FROM rewards_data r 
                        LEFT JOIN bin_data b ON r.bin_id = b.id 
                        WHERE r.collected_by = ? 
                        ORDER BY r.collected_at DESC LIMIT 10");
$stmt->execute([$user['id']]);
$recentRewards = $stmt->fetchAll();

// Get leaderboard
$leaderboard = $conn->query("SELECT * FROM user_leaderboard LIMIT 10")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style/main.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }
        
        .navbar {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar h1 {
            font-size: 24px;
        }
        
        .navbar .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .navbar .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .navbar .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .rewards-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .rewards-card h2 {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        .rewards-card p {
            font-size: 18px;
            opacity: 0.9;
        }
        
        .actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .action-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .action-card h3 {
            color: #2ecc71;
            margin-bottom: 10px;
            font-size: 20px;
        }
        
        .action-card p {
            color: #666;
            font-size: 14px;
        }
        
        .section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .section h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #2ecc71;
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
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #2ecc71;
        }
        
        .btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
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
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        table th {
            background: #f9f9f9;
            font-weight: 600;
            color: #333;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            color: #333;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        .close-btn:hover {
            color: #333;
        }
        
        #qr-video {
            width: 100%;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🌱 <?php echo APP_NAME; ?></h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($user['name']); ?>!</span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="rewards-card">
            <h2><?php echo number_format($user['rewards_collected']); ?></h2>
            <p>Total Reward Points</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="actions">
            <div class="action-card" onclick="openModal('collectModal')">
                <h3>💰 Collect Reward</h3>
                <p>Enter code from bin</p>
            </div>
            
            <div class="action-card" onclick="openModal('disposeModal')">
                <h3>♻️ Start Disposal</h3>
                <p>Scan QR & dispose plastic</p>
            </div>
            
            <div class="action-card">
                <h3>🎁 Claim Rewards</h3>
                <p>Coming Soon!</p>
            </div>
        </div>
        
        <?php if (!empty($recentRewards)): ?>
        <div class="section">
            <h2>Recent Rewards Collected</h2>
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Points</th>
                        <th>Location</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentRewards as $reward): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($reward['unique_code']); ?></strong></td>
                        <td><?php echo $reward['points']; ?></td>
                        <td><?php echo htmlspecialchars($reward['location']); ?></td>
                        <td><?php echo formatDateTime($reward['collected_at'], 'M d, Y H:i'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($leaderboard)): ?>
        <div class="section">
            <h2>🏆 Leaderboard - Top Users</h2>
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Name</th>
                        <th>Points</th>
                        <th>Collections</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leaderboard as $index => $leader): ?>
                    <tr <?php echo $leader['id'] == $user['id'] ? 'style="background:#f0fff4;"' : ''; ?>>
                        <td><strong>#<?php echo $index + 1; ?></strong></td>
                        <td><?php echo htmlspecialchars($leader['name']); ?></td>
                        <td><?php echo number_format($leader['rewards_collected']); ?></td>
                        <td><?php echo $leader['total_collections']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Collect Reward Modal -->
    <div id="collectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Collect Reward</h3>
                <button class="close-btn" onclick="closeModal('collectModal')">&times;</button>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Enter Reward Code</label>
                    <input type="text" name="reward_code" placeholder="e.g., ABC123" required style="text-transform:uppercase;">
                    <small style="color:#666;display:block;margin-top:5px;">
                        Enter the 6-character code displayed on the smart bin after disposal.
                    </small>
                </div>
                <button type="submit" name="collect_reward" class="btn">Collect Points</button>
            </form>
        </div>
    </div>
    
    <!-- Disposal Modal -->
    <div id="disposeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Start Disposal Session</h3>
                <button class="close-btn" onclick="closeModal('disposeModal')">&times;</button>
            </div>
            
            <div id="step1" style="display:block;">
                <div class="form-group">
                    <label>Select Bin Location</label>
                    <select id="binSelect">
                        <option value="">-- Select Bin --</option>
                        <?php
                        $bins = $conn->query("SELECT * FROM bin_data ORDER BY location")->fetchAll();
                        foreach ($bins as $bin) {
                            echo "<option value='{$bin['id']}'>" . htmlspecialchars($bin['location']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <button class="btn" onclick="startScanning()">Next</button>
            </div>
            
            <div id="step2" style="display:none;">
                <div style="text-align:center;margin-bottom:20px;">
                    <button class="btn" onclick="openScanner()">📷 Start Scanning</button>
                    <button class="btn btn-secondary" onclick="stopDisposal()">Stop Session</button>
                </div>
                
                <div id="scannerDiv" style="display:none;">
                    <video id="qr-video" playsinline></video>
                    <button class="btn btn-secondary" onclick="closeScanner()">Close Scanner</button>
                </div>
                
                <div id="scanResult"></div>
            </div>
        </div>
    </div>
    
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
        let currentBinId = null;
        let html5QrCode = null;
        
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function startScanning() {
            const binId = document.getElementById('binSelect').value;
            if (!binId) {
                alert('Please select a bin location');
                return;
            }
            
            currentBinId = binId;
            document.getElementById('step1').style.display = 'none';
            document.getElementById('step2').style.display = 'block';
        }
        
        function stopDisposal() {
            closeModal('disposeModal');
            document.getElementById('step1').style.display = 'block';
            document.getElementById('step2').style.display = 'none';
            currentBinId = null;
        }
        
        function openScanner() {
            document.getElementById('scannerDiv').style.display = 'block';
            
            html5QrCode = new Html5Qrcode("qr-video");
            
            html5QrCode.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: 250 },
                onScanSuccess,
                onScanError
            );
        }
        
        function closeScanner() {
            if (html5QrCode) {
                html5QrCode.stop().then(() => {
                    document.getElementById('scannerDiv').style.display = 'none';
                });
            }
        }
        
        function onScanSuccess(decodedText, decodedResult) {
            // Stop scanning
            closeScanner();
            
            // Send to server
            fetch('api/add_disposal.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    qr_id: decodedText,
                    bin_id: currentBinId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('scanResult').innerHTML = 
                        '<div class="alert alert-success">✓ ' + data.message + '</div>';
                } else {
                    document.getElementById('scanResult').innerHTML = 
                        '<div class="alert alert-error">✗ ' + data.error + '</div>';
                }
            });
        }
        
        function onScanError(error) {
            // Ignore errors during scanning
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>
