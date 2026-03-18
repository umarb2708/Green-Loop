<?php
require_once 'config.php';
requireAdmin();

$message = '';
$messageType = '';

// Handle Add New Bin
if (isset($_POST['add_bin'])) {
    $location = trim($_POST['location']);
    
    if (!empty($location)) {
        $conn = getDBConnection();
        $location = $conn->real_escape_string($location);
        
        $sql = "INSERT INTO bin_data (location, current_status, weight) VALUES ('$location', '0000', 0.0)";
        
        if ($conn->query($sql)) {
            $bin_id = $conn->insert_id;
            $message = "Bin added successfully! Bin ID: " . $bin_id;
            $messageType = 'success';
        } else {
            $message = "Error adding bin: " . $conn->error;
            $messageType = 'error';
        }
        
        $conn->close();
    }
}

// Handle Add Product
if (isset($_POST['add_product'])) {
    $manufacturer = trim($_POST['manufacturer']);
    $type = $_POST['type'];
    
    if (!empty($manufacturer) && !empty($type)) {
        $conn = getDBConnection();
        $manufacturer = $conn->real_escape_string($manufacturer);
        
        // Generate 10 character alphanumeric QR ID
        $qr_id = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10);
        
        $sql = "INSERT INTO product_data (qr_id, manufacturer, type) VALUES ('$qr_id', '$manufacturer', '$type')";
        
        if ($conn->query($sql)) {
            $message = "Product added successfully! QR ID: " . $qr_id;
            $messageType = 'success';
            $_SESSION['qr_code'] = $qr_id;
        } else {
            $message = "Error adding product: " . $conn->error;
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

// Get all bins for dropdown
$conn = getDBConnection();
$bins_result = $conn->query("SELECT id, location FROM bin_data ORDER BY id DESC");
$bins = [];
while ($row = $bins_result->fetch_assoc()) {
    $bins[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Green Loop</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div>
                <h1>🌱 Admin Dashboard</h1>
                <p>Welcome, <?php echo $_SESSION['name']; ?></p>
            </div>
            <a href="?logout=1" class="logout-btn">Logout</a>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="card">
            <h2>Actions</h2>
            <div class="button-grid">
                <button class="btn btn-success" onclick="openModal('addBinModal')">➕ Add New Bin</button>
                <button class="btn btn-success" onclick="openModal('addProductModal')">📦 Add Product</button>
            </div>
        </div>

        <!-- Bin Status Section -->
        <div class="card">
            <h2>Bin Status Monitor</h2>
            <div class="form-group">
                <label for="bin-select">Select Bin Location:</label>
                <select id="bin-select" onchange="loadBinStatus()">
                    <option value="">-- Select a bin --</option>
                    <?php foreach ($bins as $bin): ?>
                        <option value="<?php echo $bin['id']; ?>"><?php echo $bin['location']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="bin-status-display" style="display:none;">
                <div class="bin-status-grid">
                    <div id="pet-status" class="bin-chamber available">
                        <h3>PET</h3>
                        <p>Available</p>
                    </div>
                    <div id="hdpe-status" class="bin-chamber available">
                        <h3>HDPE</h3>
                        <p>Available</p>
                    </div>
                    <div id="pp-status" class="bin-chamber available">
                        <h3>PP</h3>
                        <p>Available</p>
                    </div>
                    <div id="others-status" class="bin-chamber available">
                        <h3>Others</h3>
                        <p>Available</p>
                    </div>
                </div>
                <div style="margin-top: 20px; padding: 15px; background: #f5f5f5; border-radius: 8px;">
                    <strong>Total Weight:</strong> <span id="weight-display">0.00</span> kg
                </div>
            </div>
        </div>

        <!-- Recent Products -->
        <div class="card">
            <h2>Recent Products</h2>
            <table>
                <thead>
                    <tr>
                        <th>QR ID</th>
                        <th>Manufacturer</th>
                        <th>Type</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $conn = getDBConnection();
                    $result = $conn->query("SELECT * FROM product_data ORDER BY id DESC LIMIT 10");
                    while ($row = $result->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?php echo $row['qr_id']; ?></td>
                        <td><?php echo $row['manufacturer']; ?></td>
                        <td><?php echo $row['type']; ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                    </tr>
                    <?php endwhile; $conn->close(); ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Bin Modal -->
    <div id="addBinModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Bin</h2>
                <button class="close-btn" onclick="closeModal('addBinModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" required placeholder="e.g., Main Campus Entrance">
                </div>
                <button type="submit" name="add_bin" class="btn">Add Bin</button>
            </form>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div id="addProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Product</h2>
                <button class="close-btn" onclick="closeModal('addProductModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="manufacturer">Manufacturer</label>
                    <input type="text" id="manufacturer" name="manufacturer" required placeholder="e.g., Coca Cola">
                </div>
                <div class="form-group">
                    <label for="type">Plastic Type</label>
                    <select id="type" name="type" required>
                        <option value="">-- Select Type --</option>
                        <option value="PET">PET</option>
                        <option value="HDPE">HDPE</option>
                        <option value="PP">PP</option>
                        <option value="Others">Others</option>
                    </select>
                </div>
                <button type="submit" name="add_product" class="btn">Add Product</button>
            </form>
        </div>
    </div>

    <!-- QR Code Modal -->
    <?php if (isset($_SESSION['qr_code'])): ?>
    <div id="qrModal" class="modal active">
        <div class="modal-content">
            <div class="modal-header">
                <h2>QR Code Generated</h2>
                <button class="close-btn" onclick="closeQRModal()">&times;</button>
            </div>
            <div class="qr-display">
                <p><strong>QR ID:</strong> <?php echo $_SESSION['qr_code']; ?></p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?php echo $_SESSION['qr_code']; ?>" alt="QR Code">
                <p style="margin-top: 15px; color: #666;">Right-click to save or print this QR code</p>
                <button class="btn" onclick="window.print()">Print QR Code</button>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['qr_code']); ?>
    <?php endif; ?>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function closeQRModal() {
            document.getElementById('qrModal').classList.remove('active');
        }

        function loadBinStatus() {
            const binId = document.getElementById('bin-select').value;
            
            if (!binId) {
                document.getElementById('bin-status-display').style.display = 'none';
                return;
            }

            fetch('get_bin_status.php?bin_id=' + binId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('bin-status-display').style.display = 'block';
                        
                        // Update chamber status
                        const chambers = ['pet', 'hdpe', 'pp', 'others'];
                        const status = data.status.split('');
                        
                        chambers.forEach((chamber, index) => {
                            const element = document.getElementById(chamber + '-status');
                            if (status[index] === '1') {
                                element.className = 'bin-chamber full';
                                element.querySelector('p').textContent = 'Full';
                            } else {
                                element.className = 'bin-chamber available';
                                element.querySelector('p').textContent = 'Available';
                            }
                        });
                        
                        // Update weight
                        document.getElementById('weight-display').textContent = data.weight.toFixed(2);
                    }
                })
                .catch(error => console.error('Error:', error));
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
    </script>
</body>
</html>
