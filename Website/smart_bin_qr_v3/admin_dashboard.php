<?php
/**
 * Green Loop - Admin Dashboard
 * Version 3.0
 */

require_once 'config.php';
require_once 'vendor/qr_generator.php';  // We'll create this for QR generation

requireAdmin();

$user = getCurrentUser();
$error = '';
$success = '';
$newBinId = '';
$qrCode = '';

// Handle Add New Bin
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_bin'])) {
    $location = sanitize($_POST['location']);
    
    if (empty($location)) {
        $error = 'Location is required';
    } else {
        $stmt = $conn->prepare("INSERT INTO bin_data (location, current_status, weight) VALUES (?, '0000', 0.000)");
        if ($stmt->execute([$location])) {
            $newBinId = $conn->lastInsertId();
            logActivity($user['id'], 'add_bin', "Added new bin at {$location}");
            $success = "Bin added successfully! Bin ID: {$newBinId}";
        } else {
            $error = 'Error adding bin';
        }
    }
}

// Handle Add Product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $manufacturer = sanitize($_POST['manufacturer']);
    $type = sanitize($_POST['type']);
    
    if (empty($manufacturer) || empty($type)) {
        $error = 'All fields are required';
    } else {
        $qrId = generateQRCode();
        
        $stmt = $conn->prepare("INSERT INTO product_data (qr_id, manufacturer, type, created_by) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$qrId, $manufacturer, $type, $user['id']])) {
            logActivity($user['id'], 'add_product', "Added product {$qrId} - {$manufacturer} ({$type})");
            $qrCode = $qrId;
            $success = "Product added successfully! QR ID: {$qrId}";
        } else {
            $error = 'Error adding product';
        }
    }
}

// Get all bins
$bins = $conn->query("SELECT * FROM bin_status_summary ORDER BY id DESC")->fetchAll();

// Get recent products
$recentProducts = $conn->query("SELECT p.*, u.name as creator_name FROM product_data p 
                                LEFT JOIN users u ON p.created_by = u.id 
                                ORDER BY p.created_at DESC LIMIT 20")->fetchAll();

// Get statistics
$stats = [
    'total_bins' => $conn->query("SELECT COUNT(*) FROM bin_data")->fetchColumn(),
    'total_products' => $conn->query("SELECT COUNT(*) FROM product_data")->fetchColumn(),
    'total_users' => $conn->query("SELECT COUNT(*) FROM users WHERE is_admin = 0")->fetchColumn(),
    'total_disposals' => $conn->query("SELECT COUNT(*) FROM disposal_data WHERE confirmed = 1")->fetchColumn()
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
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
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
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
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stat-card .number {
            font-size: 36px;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 5px;
        }
        
        .stat-card .label {
            color: #666;
            font-size: 14px;
        }
        
        .actions {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
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
            border-bottom: 2px solid #3498db;
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
            border-color: #3498db;
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
            max-width: 600px;
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
        
        .status-indicator {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-available {
            background: #d4edda;
            color: #155724;
        }
        
        .status-full {
            background: #f8d7da;
            color: #721c24;
        }
        
        .qr-display {
            text-align: center;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .qr-display canvas {
            max-width: 300px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🛡️ <?php echo APP_NAME; ?> - Admin</h1>
        <div class="user-info">
            <span>Admin: <?php echo htmlspecialchars($user['name']); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $stats['total_bins']; ?></div>
                <div class="label">Total Bins</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['total_products']; ?></div>
                <div class="label">Total Products</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['total_users']; ?></div>
                <div class="label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['total_disposals']; ?></div>
                <div class="label">Total Disposals</div>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="actions">
            <button class="btn" onclick="openModal('addBinModal')">+ Add New Bin</button>
            <button class="btn btn-success" onclick="openModal('addProductModal')">+ Add Product</button>
        </div>
        
        <!-- Bin Status Section -->
        <div class="section">
            <h2>Bin Status Monitor</h2>
            <div class="form-group">
                <label>Select Bin Location</label>
                <select id="binSelector" onchange="updateBinDisplay()">
                    <option value="">-- Select a bin --</option>
                    <?php foreach ($bins as $bin): ?>
                        <option value="<?php echo $bin['id']; ?>"
                                data-status="<?php echo $bin['current_status']; ?>"
                                data-weight="<?php echo $bin['weight']; ?>"
                                data-pet="<?php echo $bin['pet_status']; ?>"
                                data-hdpe="<?php echo $bin['hdpe_status']; ?>"
                                data-pp="<?php echo $bin['pp_status']; ?>"
                                data-others="<?php echo $bin['others_status']; ?>">
                            <?php echo htmlspecialchars($bin['location']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="binDisplay" style="display:none;">
                <table>
                    <thead>
                        <tr>
                            <th>Chamber</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>PET</td>
                            <td id="pet-status"></td>
                        </tr>
                        <tr>
                            <td>HDPE</td>
                            <td id="hdpe-status"></td>
                        </tr>
                        <tr>
                            <td>PP</td>
                            <td id="pp-status"></td>
                        </tr>
                        <tr>
                            <td>Others</td>
                            <td id="others-status"></td>
                        </tr>
                    </tbody>
                </table>
                <p style="margin-top:15px;"><strong>Total Weight:</strong> <span id="weight-display"></span> kg</p>
            </div>
        </div>
        
        <!-- Recently Added Products -->
        <div class="section">
            <h2>Recently Added Products</h2>
            <table>
                <thead>
                    <tr>
                        <th>QR ID</th>
                        <th>Manufacturer</th>
                        <th>Type</th>
                        <th>Created By</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentProducts as $product): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($product['qr_id']); ?></strong></td>
                        <td><?php echo htmlspecialchars($product['manufacturer']); ?></td>
                        <td><?php echo htmlspecialchars($product['type']); ?></td>
                        <td><?php echo htmlspecialchars($product['creator_name'] ?? 'N/A'); ?></td>
                        <td><?php echo formatDateTime($product['created_at'], 'M d, Y'); ?></td>
                        <td>
                            <button class="btn" style="padding:5px 10px;font-size:12px;" 
                                    onclick="showQR('<?php echo $product['qr_id']; ?>')">View QR</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Add Bin Modal -->
    <div id="addBinModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Bin</h3>
                <button class="close-btn" onclick="closeModal('addBinModal')">&times;</button>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Bin Location *</label>
                    <input type="text" name="location" placeholder="e.g., Main Campus - Building A" required>
                </div>
                <button type="submit" name="add_bin" class="btn">Add Bin</button>
            </form>
        </div>
    </div>
    
    <!-- Add Product Modal -->
    <div id="addProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Product</h3>
                <button class="close-btn" onclick="closeModal('addProductModal')">&times;</button>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Manufacturer *</label>
                    <input type="text" name="manufacturer" placeholder="e.g., Coca Cola Company" required>
                </div>
                <div class="form-group">
                    <label>Plastic Type *</label>
                    <select name="type" required>
                        <option value="">-- Select Type --</option>
                        <option value="PET">PET (Polyethylene Terephthalate)</option>
                        <option value="HDPE">HDPE (High-Density Polyethylene)</option>
                        <option value="PP">PP (Polypropylene)</option>
                        <option value="Others">Others</option>
                    </select>
                </div>
                <button type="submit" name="add_product" class="btn btn-success">Generate QR & Add Product</button>
            </form>
        </div>
    </div>
    
    <!-- QR Code Modal -->
    <div id="qrModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Product QR Code</h3>
                <button class="close-btn" onclick="closeModal('qrModal')">&times;</button>
            </div>
            <div class="qr-display">
                <div id="qrcode"></div>
                <p style="margin-top:15px;">QR ID: <strong id="qr-text"></strong></p>
                <button class="btn" onclick="downloadQR()" style="margin-top:15px;">Download QR Code</button>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>
    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function updateBinDisplay() {
            const select = document.getElementById('binSelector');
            const option = select.options[select.selectedIndex];
            
            if (!option.value) {
                document.getElementById('binDisplay').style.display = 'none';
                return;
            }
            
            document.getElementById('binDisplay').style.display = 'block';
            
            const petStatus = option.dataset.pet;
            const hdpeStatus = option.dataset.hdpe;
            const ppStatus = option.dataset.pp;
            const othersStatus = option.dataset.others;
            const weight = option.dataset.weight;
            
            document.getElementById('pet-status').innerHTML = 
                `<span class="status-indicator status-${petStatus.toLowerCase()}">${petStatus}</span>`;
            document.getElementById('hdpe-status').innerHTML = 
                `<span class="status-indicator status-${hdpeStatus.toLowerCase()}">${hdpeStatus}</span>`;
            document.getElementById('pp-status').innerHTML = 
                `<span class="status-indicator status-${ppStatus.toLowerCase()}">${ppStatus}</span>`;
            document.getElementById('others-status').innerHTML = 
                `<span class="status-indicator status-${othersStatus.toLowerCase()}">${othersStatus}</span>`;
            document.getElementById('weight-display').textContent = weight;
        }
        
        let currentQR = null;
        
        function showQR(qrId) {
            document.getElementById('qrcode').innerHTML = '';
            document.getElementById('qr-text').textContent = qrId;
            
            currentQR = new QRCode(document.getElementById('qrcode'), {
                text: qrId,
                width: 256,
                height: 256
            });
            
            openModal('qrModal');
        }
        
        function downloadQR() {
            const canvas = document.querySelector('#qrcode canvas');
            const url = canvas.toDataURL('image/png');
            const link = document.createElement('a');
            link.download = 'qr-' + document.getElementById('qr-text').textContent + '.png';
            link.href = url;
            link.click();
        }
        
        // Auto-show QR if just added
        <?php if ($qrCode): ?>
            window.onload = function() {
                showQR('<?php echo $qrCode; ?>');
            };
        <?php endif; ?>
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>
