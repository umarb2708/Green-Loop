<?php
require_once 'config.php';
requireAdmin();

$message     = '';
$messageType = '';

// ── Add bin ────────────────────────────────────────────────────────────────────
if (isset($_POST['add_bin'])) {
    $location = trim($_POST['location'] ?? '');
    if ($location !== '') {
        $conn = getDBConnection();
        $loc  = $conn->real_escape_string($location);
        $conn->query("INSERT INTO bin_data (location, current_status, weight) VALUES ('$loc','0000',0.0)");
        $bid = $conn->insert_id;
        $message     = 'Bin added! Bin ID: ' . $bid;
        $messageType = 'success';
        $conn->close();
    }
}

// ── Add product ────────────────────────────────────────────────────────────────
if (isset($_POST['add_product'])) {
    $manufacturer = trim($_POST['manufacturer'] ?? '');
    $type         = $_POST['type'] ?? '';
    $allowed      = ['PET','HDPE','PP','Others'];

    if ($manufacturer !== '' && in_array($type, $allowed)) {
        $conn = getDBConnection();
        $m    = $conn->real_escape_string($manufacturer);
        $t    = $conn->real_escape_string($type);
        // Generate 10-char alphanumeric QR ID
        $qr_id = '';
        do {
            $qr_id = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10));
            $check = $conn->query("SELECT id FROM product_data WHERE qr_id='$qr_id' LIMIT 1");
        } while ($check->num_rows > 0);

        $conn->query("INSERT INTO product_data (qr_id, manufacturer, type) VALUES ('$qr_id','$m','$t')");
        $_SESSION['last_qr'] = $qr_id;
        $message     = "Product added! QR ID: $qr_id";
        $messageType = 'success';
        $conn->close();
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit();
}

$conn      = getDBConnection();
$bins      = $conn->query("SELECT * FROM bin_data ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$products  = $conn->query("SELECT * FROM product_data ORDER BY id DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);
$rewards   = $conn->query("SELECT r.*, b.location FROM rewards_data r
                           LEFT JOIN bin_data b ON r.bin_id=b.id
                           ORDER BY r.created_at DESC LIMIT 30")->fetch_all(MYSQLI_ASSOC);
$disposals = $conn->query("SELECT * FROM disposal ORDER BY created_at DESC LIMIT 30")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard – Green Loop</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="dashboard-container">

    <div class="dashboard-header">
        <div>
            <h1>🌱 Admin Dashboard</h1>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></p>
        </div>
        <a href="?logout=1" class="logout-btn">Logout</a>
    </div>

    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- Action buttons -->
    <div class="card">
        <h2>Actions</h2>
        <div class="button-grid">
            <button class="btn btn-success" onclick="openModal('addBinModal')">➕ Add Bin</button>
            <button class="btn btn-success" onclick="openModal('addProductModal')">➕ Add Product / QR</button>
        </div>
    </div>

    <!-- Bins -->
    <div class="card">
        <h2>Bins</h2>
        <table>
            <thead><tr><th>ID</th><th>Location</th><th>Status (PET/HDPE/PP/Other)</th><th>Weight (g)</th><th>Last Update</th></tr></thead>
            <tbody>
                <?php foreach ($bins as $b): ?>
                <tr>
                    <td><?php echo $b['id']; ?></td>
                    <td><?php echo htmlspecialchars($b['location']); ?></td>
                    <td><?php
                        $s = str_pad($b['current_status'], 4, '0');
                        $labels = ['PET','HDPE','PP','Others'];
                        $chips  = '';
                        for ($i=0;$i<4;$i++) {
                            $full = $s[$i]==='1';
                            $chips .= '<span class="bin-chamber ' . ($full?'full':'available') . '" style="display:inline-block;padding:4px 8px;margin:2px;font-size:.8em;">'
                                     . $labels[$i] . ($full?' ●':' ○') . '</span>';
                        }
                        echo $chips;
                    ?></td>
                    <td><?php echo number_format($b['weight'], 1); ?></td>
                    <td><?php echo date('d M H:i', strtotime($b['last_updated'])); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($bins)): ?><tr><td colspan="5" style="text-align:center;color:#999;">No bins yet</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Products -->
    <div class="card">
        <h2>Registered Products</h2>
        <?php if (isset($_SESSION['last_qr'])): ?>
        <div class="message success">Last QR Code: <strong><?php echo htmlspecialchars($_SESSION['last_qr']); ?></strong>
            – use this to test scanning on the user dashboard.</div>
        <?php unset($_SESSION['last_qr']); ?>
        <?php endif; ?>
        <table>
            <thead><tr><th>QR ID</th><th>Manufacturer</th><th>Type</th><th>Added</th></tr></thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($p['qr_id']); ?></code></td>
                    <td><?php echo htmlspecialchars($p['manufacturer']); ?></td>
                    <td><?php echo htmlspecialchars($p['type']); ?></td>
                    <td><?php echo date('d M Y', strtotime($p['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($products)): ?><tr><td colspan="4" style="text-align:center;color:#999;">No products yet</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Disposals -->
    <div class="card">
        <h2>Recent Disposals</h2>
        <table>
            <thead><tr><th>ID</th><th>Sync Code</th><th>Type</th><th>Confirmed</th><th>Time</th></tr></thead>
            <tbody>
                <?php foreach ($disposals as $d): ?>
                <tr>
                    <td><?php echo $d['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($d['sync_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($d['type']); ?></td>
                    <td><?php echo $d['confirmed'] ? '✅ Yes' : '⏳ Pending'; ?></td>
                    <td><?php echo date('d M H:i', strtotime($d['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($disposals)): ?><tr><td colspan="5" style="text-align:center;color:#999;">No disposals yet</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Rewards -->
    <div class="card">
        <h2>Reward Codes</h2>
        <table>
            <thead><tr><th>Code</th><th>Points</th><th>Collected</th><th>Bin</th><th>Date</th></tr></thead>
            <tbody>
                <?php foreach ($rewards as $r): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($r['unique_code']); ?></strong></td>
                    <td><?php echo intval($r['points']); ?></td>
                    <td><?php echo $r['collected'] ? '✅' : '⏳'; ?></td>
                    <td><?php echo htmlspecialchars($r['location'] ?? '-'); ?></td>
                    <td><?php echo date('d M Y', strtotime($r['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($rewards)): ?><tr><td colspan="5" style="text-align:center;color:#999;">No rewards yet</td></tr><?php endif; ?>
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
                <label>Location</label>
                <input type="text" name="location" required placeholder="e.g. Main Entrance, Block A">
            </div>
            <button type="submit" name="add_bin" class="btn">Add Bin</button>
        </form>
    </div>
</div>

<!-- Add Product Modal -->
<div id="addProductModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add Product (generates QR ID)</h2>
            <button class="close-btn" onclick="closeModal('addProductModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="form-group">
                <label>Manufacturer</label>
                <input type="text" name="manufacturer" required placeholder="e.g. Coca Cola">
            </div>
            <div class="form-group">
                <label>Plastic Type</label>
                <select name="type" required>
                    <option value="">-- Select --</option>
                    <option value="PET">PET (Polyethylene terephthalate)</option>
                    <option value="HDPE">HDPE (High-density polyethylene)</option>
                    <option value="PP">PP (Polypropylene)</option>
                    <option value="Others">Others</option>
                </select>
            </div>
            <button type="submit" name="add_product" class="btn">Generate QR &amp; Add</button>
        </form>
        <p style="margin-top:15px;font-size:.85em;color:#666;">
            A unique 10-character QR ID will be generated. Encode it in a QR code image
            and print it on product labels for scanning.
        </p>
    </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
window.addEventListener('click', e => {
    document.querySelectorAll('.modal').forEach(m => {
        if (e.target === m) closeModal(m.id);
    });
});
</script>
</body>
</html>
