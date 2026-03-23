<?php
/**
 * Green Loop - Manufacturer Dashboard
 * Version 3.0
 */

require_once 'config.php';
requireManufacturer();

$user = getCurrentUser();
$error = '';
$success = '';
$qrCode = '';

// Handle Add Product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $manufacturer = $user['name'];  // Default to logged-in manufacturer name
    $type = sanitize($_POST['type']);
    
    if (empty($type)) {
        $error = 'Plastic type is required';
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

// Get manufacturer's products
$stmt = $conn->prepare("SELECT * FROM product_data WHERE created_by = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$myProducts = $stmt->fetchAll();

// Get statistics
$stats = [
    'total_products' => count($myProducts),
    'pet_count' => $conn->prepare("SELECT COUNT(*) FROM product_data WHERE created_by = ? AND type = 'PET'")->execute([$user['id']]) ? $conn->query("SELECT FOUND_ROWS()")->fetchColumn() : 0,
    'hdpe_count' => 0,
    'pp_count' => 0
];

foreach ($myProducts as $product) {
    if ($product['type'] == 'HDPE') $stats['hdpe_count']++;
    if ($product['type'] == 'PP') $stats['pp_count']++;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manufacturer Dashboard - <?php echo APP_NAME; ?></title>
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
            background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            color: #e67e22;
            margin-bottom: 5px;
        }
        
        .stat-card .label {
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
            border-bottom: 2px solid #e67e22;
        }
        
        .btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
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
            border-color: #e67e22;
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
        
        .qr-display {
            text-align: center;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🏭 <?php echo APP_NAME; ?> - Manufacturer</h1>
        <div class="user-info">
            <span><?php echo htmlspecialchars($user['name']); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $stats['total_products']; ?></div>
                <div class="label">Total Products</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['pet_count']; ?></div>
                <div class="label">PET Products</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['hdpe_count']; ?></div>
                <div class="label">HDPE Products</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['pp_count']; ?></div>
                <div class="label">PP Products</div>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div style="margin-bottom:30px;">
            <button class="btn" onclick="openModal('addProductModal')">+ Add New Product</button>
        </div>
        
        <!-- My Products -->
        <div class="section">
            <h2>My Products</h2>
            <?php if (empty($myProducts)): ?>
                <p style="color:#666;">No products added yet. Click "Add New Product" to get started.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>QR ID</th>
                            <th>Type</th>
                            <th>Date Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myProducts as $product): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($product['qr_id']); ?></strong></td>
                            <td><?php echo htmlspecialchars($product['type']); ?></td>
                            <td><?php echo formatDateTime($product['created_at'], 'M d, Y H:i'); ?></td>
                            <td>
                                <button class="btn" style="padding:5px 10px;font-size:12px;" 
                                        onclick="showQR('<?php echo $product['qr_id']; ?>')">View QR</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add Product Modal -->
    <div id="addProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Product</h3>
                <button class="close-btn" onclick="closeModal('addProductModal')">&times;</button>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Manufacturer</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['name']); ?>" disabled>
                    <small style="color:#666;display:block;margin-top:5px;">Automatically set to your company name</small>
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
                <button type="submit" name="add_product" class="btn">Generate QR Code & Add Product</button>
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
                <p style="color:#666;font-size:14px;margin-top:10px;">
                    Print this QR code and attach it to your product packaging.
                </p>
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
