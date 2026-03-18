<?php
require_once 'config.php';
requireLogin();

if (isAdmin()) {
    header('Location: admin_dashboard.php');
    exit();
}

// ── Collect reward code ────────────────────────────────────────────────────────
$message     = '';
$messageType = '';

if (isset($_POST['add_reward'])) {
    $reward_code = strtoupper(trim($_POST['reward_code'] ?? ''));

    if ($reward_code !== '') {
        $conn    = getDBConnection();
        $user_id = intval($_SESSION['user_id']);
        $code    = $conn->real_escape_string($reward_code);

        $res = $conn->query("SELECT * FROM rewards_data WHERE unique_code='$code' LIMIT 1");
        if ($res->num_rows > 0) {
            $reward = $res->fetch_assoc();
            if ($reward['collected'] == 1) {
                $message     = 'This reward code has already been used!';
                $messageType = 'error';
            } else {
                $new_total = intval($_SESSION['rewards_collected'] ?? 0) + intval($reward['points']);
                $conn->query("UPDATE users SET rewards_collected=$new_total WHERE id=$user_id");
                $conn->query("UPDATE rewards_data SET collected=1, user_id=$user_id WHERE unique_code='$code'");
                $_SESSION['rewards_collected'] = $new_total;
                $message     = 'Success! Added ' . $reward['points'] . ' points.';
                $messageType = 'success';
            }
        } else {
            $message     = 'Invalid reward code.';
            $messageType = 'error';
        }
        $conn->close();
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit();
}

// ── Page data ──────────────────────────────────────────────────────────────────
$conn    = getDBConnection();
$user_id = intval($_SESSION['user_id']);
$user    = $conn->query("SELECT * FROM users WHERE id=$user_id LIMIT 1")->fetch_assoc();
$history = $conn->query(
    "SELECT r.*, b.location FROM rewards_data r
     LEFT JOIN bin_data b ON r.bin_id = b.id
     WHERE r.collected=1 AND r.user_id=$user_id
     ORDER BY r.created_at DESC LIMIT 20"
);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – Green Loop</title>
    <link rel="stylesheet" href="style.css">
    <!-- jsQR: browser-based QR code decoder -->
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
</head>
<body>
<div class="dashboard-container">

    <!-- Header -->
    <div class="dashboard-header">
        <div>
            <h1>🌱 Green Loop</h1>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></p>
        </div>
        <a href="?logout=1" class="logout-btn">Logout</a>
    </div>

    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- Points summary -->
    <div class="stat-card">
        <h3><?php echo number_format($user['rewards_collected']); ?></h3>
        <p>Total Reward Points</p>
    </div>

    <!-- Action buttons -->
    <div class="button-grid">
        <button class="btn btn-success" onclick="startDispose()">♻️ Dispose Now</button>
        <button class="btn btn-secondary" onclick="openModal('rewardModal')">➕ Claim Reward Code</button>
    </div>

    <!-- History -->
    <div class="card">
        <h2>Recent Activity</h2>
        <table>
            <thead>
                <tr><th>Date</th><th>Code</th><th>Points</th><th>Location</th></tr>
            </thead>
            <tbody>
                <?php if ($history->num_rows > 0): ?>
                    <?php while ($row = $history->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date('d M Y H:i', strtotime($row['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($row['unique_code']); ?></td>
                        <td><?php echo intval($row['points']); ?></td>
                        <td><?php echo htmlspecialchars($row['location'] ?? 'Unknown'); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center;color:#999;">No history yet</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Environmental impact -->
    <div class="card">
        <h2>Your Environmental Impact</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:20px;padding:10px 0;">
            <div style="padding:20px;background:#e8f5e9;border-radius:10px;text-align:center;">
                <h3 style="color:#2e7d32;font-size:2em;"><?php echo ceil($user['rewards_collected'] / 15); ?></h3>
                <p style="color:#666;">Bottles Recycled</p>
            </div>
            <div style="padding:20px;background:#e3f2fd;border-radius:10px;text-align:center;">
                <h3 style="color:#1565c0;font-size:2em;"><?php echo number_format($user['rewards_collected'] * 0.05, 1); ?></h3>
                <p style="color:#666;">kg CO₂ Saved</p>
            </div>
            <div style="padding:20px;background:#fff3e0;border-radius:10px;text-align:center;">
                <h3 style="color:#e65100;font-size:2em;"><?php echo number_format($user['rewards_collected'] * 0.03, 1); ?></h3>
                <p style="color:#666;">Litres Oil Saved</p>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     CLAIM REWARD CODE MODAL
════════════════════════════════════════════ -->
<div id="rewardModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Claim Reward Points</h2>
            <button class="close-btn" onclick="closeModal('rewardModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="form-group">
                <label>Reward Code (from the bin screen)</label>
                <input type="text" name="reward_code" required maxlength="6"
                       placeholder="e.g. A3BX7Z" style="text-transform:uppercase;letter-spacing:4px;font-size:1.2em;">
            </div>
            <button type="submit" name="add_reward" class="btn">Add Points</button>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     DISPOSE NOW MODAL  (multi-step)
════════════════════════════════════════════ -->
<div id="disposeModal" class="modal">
    <div class="modal-content">

        <!-- Step dots -->
        <div class="step-indicator">
            <div class="step-dot active" id="dot1"></div>
            <div class="step-dot"        id="dot2"></div>
            <div class="step-dot"        id="dot3"></div>
            <div class="step-dot"        id="dot4"></div>
        </div>

        <!-- ── STEP 1: Enter Sync Code ───────────────────────────── -->
        <div id="step1">
            <div class="modal-header">
                <h2>Step 1 – Enter Sync Code</h2>
                <button class="close-btn" onclick="closeDispose()">&times;</button>
            </div>
            <p style="color:#666;margin-bottom:20px;">
                Press the <strong>START</strong> button on the Green Loop bin.
                A 6-character code will appear on the bin's screen. Enter it below.
            </p>
            <div class="form-group">
                <label>Sync Code</label>
                <input type="text" id="syncCodeInput" maxlength="6" required
                       placeholder="e.g. A3BX7Z"
                       style="text-transform:uppercase;letter-spacing:6px;font-size:1.4em;text-align:center;">
            </div>
            <div id="syncError" class="message error" style="display:none;"></div>
            <button class="btn" onclick="validateSync()">Continue →</button>
        </div>

        <!-- ── STEP 2: QR Scanner ───────────────────────────────── -->
        <div id="step2" style="display:none;">
            <div class="modal-header">
                <h2>Step 2 – Scan QR Code</h2>
                <button class="close-btn" onclick="closeDispose()">&times;</button>
            </div>
            <p style="color:#666;margin-bottom:12px;">
                Point your camera at the QR code on the plastic bottle/item.
            </p>

            <div id="qr-video-container">
                <video id="qr-video" autoplay playsinline muted></video>
                <canvas id="qr-canvas"></canvas>
                <div class="scan-overlay"><div class="scan-line"></div></div>
            </div>

            <div id="scanStatus" class="message info" style="text-align:center;">
                Initialising camera…
            </div>

            <div id="scanResult" style="display:none;">
                <div class="result-box info">
                    <strong id="scannedType"></strong>
                    <p id="scannedManufacturer" style="font-size:.9em;margin-top:4px;"></p>
                </div>
                <div id="binFullWarning" class="message warning" style="display:none;">
                    ⚠️ The bin chamber for this plastic type is currently <strong>full</strong>.
                    Please try a different bin or come back later.
                </div>
                <div style="display:flex;gap:10px;margin-top:10px;">
                    <button class="btn btn-secondary btn-small" onclick="rescan()">Re-scan</button>
                    <button class="btn btn-success btn-small"  id="confirmScanBtn" onclick="submitDisposal()">
                        Dispose This ♻️
                    </button>
                </div>
            </div>
        </div>

        <!-- ── STEP 3: Waiting for Hardware ────────────────────── -->
        <div id="step3" style="display:none;">
            <div class="modal-header">
                <h2>Step 3 – Insert Plastic</h2>
                <button class="close-btn" onclick="closeDispose()">&times;</button>
            </div>
            <div class="spinner-wrap">
                <div class="spinner"></div>
                <p style="color:#667eea;font-weight:600;font-size:1.1em;">Waiting for bin to open…</p>
                <p style="color:#888;font-size:.9em;margin-top:8px;">
                    The bin lid will open automatically. Insert your plastic when ready.
                </p>
            </div>
            <div id="hardwareStatus" class="message info" style="text-align:center;"></div>
        </div>

        <!-- ── STEP 4: Disposal Confirmed ──────────────────────── -->
        <div id="step4" style="display:none;">
            <div class="modal-header">
                <h2>Disposal Complete! 🎉</h2>
                <button class="close-btn" onclick="closeDispose()">&times;</button>
            </div>
            <div class="result-box success">
                <h3>✅ Success</h3>
                <p>Your plastic has been sorted and confirmed by the bin.</p>
            </div>
            <div class="sync-code-badge" id="activeCodeDisplay"></div>
            <p style="color:#666;text-align:center;margin-bottom:20px;">
                Want to dispose another item? Use the same code on the bin.
            </p>
            <div style="display:flex;gap:10px;">
                <button class="btn btn-success" onclick="scanMore()">Scan Another ♻️</button>
                <button class="btn btn-secondary btn-small" onclick="closeDispose()">I'm Done</button>
            </div>
        </div>

    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════════════════════ -->
<script>
"use strict";

// ── State ──────────────────────────────────────────────────────────────────────
let activeSyncCode   = '';
let activeStream     = null;
let scanInterval     = null;
let pollInterval     = null;
let currentDisposalId = null;
let scannedType      = '';
let isBinFull        = false;

// ── Modal helpers ──────────────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function startDispose() {
    resetDisposeState();
    showStep(1);
    openModal('disposeModal');
}

function closeDispose() {
    stopCamera();
    clearInterval(pollInterval);
    closeModal('disposeModal');
}

// ── Step navigation ────────────────────────────────────────────────────────────
function showStep(n) {
    [1,2,3,4].forEach(i => document.getElementById('step'+i).style.display = i===n ? '' : 'none');
    [1,2,3,4].forEach(i => {
        const dot = document.getElementById('dot'+i);
        dot.className = 'step-dot' + (i<n ? ' done' : i===n ? ' active' : '');
    });
}

// ── STEP 1: Validate sync code ─────────────────────────────────────────────────
async function validateSync() {
    const code = document.getElementById('syncCodeInput').value.toUpperCase().trim();
    const errDiv = document.getElementById('syncError');

    if (code.length !== 6) {
        errDiv.textContent = 'Please enter the full 6-character code.';
        errDiv.style.display = '';
        return;
    }

    errDiv.style.display = 'none';

    try {
        const res  = await fetch('api.php?action=validate_sync&sync_code=' + encodeURIComponent(code));
        const data = await res.json();

        if (data.success) {
            activeSyncCode = code;
            document.getElementById('activeCodeDisplay').textContent = code;
            showStep(2);
            startCamera();
        } else {
            errDiv.textContent = data.error || 'Invalid sync code. Make sure the bin is ready.';
            errDiv.style.display = '';
        }
    } catch {
        errDiv.textContent = 'Network error – check your connection.';
        errDiv.style.display = '';
    }
}

// ── STEP 2: Camera & QR scanning ──────────────────────────────────────────────
async function startCamera() {
    const statusEl = document.getElementById('scanStatus');
    statusEl.textContent = 'Requesting camera access…';
    statusEl.className   = 'message info';
    statusEl.style.display = '';

    document.getElementById('scanResult').style.display = 'none';

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        statusEl.textContent = 'Camera not supported. Use Chrome/Firefox over HTTPS or localhost.';
        statusEl.className   = 'message error';
        return;
    }

    try {
        activeStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: 640 }, height: { ideal: 480 } }
        });
        const video = document.getElementById('qr-video');
        video.srcObject = activeStream;
        video.play();

        statusEl.textContent = 'Scanning… hold QR code steady inside the frame.';
        statusEl.className   = 'message info';

        scanInterval = setInterval(scanFrame, 200);
    } catch (err) {
        statusEl.textContent = 'Camera access denied: ' + err.message;
        statusEl.className   = 'message error';
    }
}

function scanFrame() {
    const video  = document.getElementById('qr-video');
    const canvas = document.getElementById('qr-canvas');

    if (video.readyState !== video.HAVE_ENOUGH_DATA) return;

    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const code    = jsQR(imgData.data, imgData.width, imgData.height, {
        inversionAttempts: 'dontInvert'
    });

    if (code) {
        stopCamera();
        lookupProduct(code.data);
    }
}

function stopCamera() {
    clearInterval(scanInterval);
    scanInterval = null;
    if (activeStream) {
        activeStream.getTracks().forEach(t => t.stop());
        activeStream = null;
    }
}

async function lookupProduct(qrData) {
    const statusEl = document.getElementById('scanStatus');
    statusEl.textContent = 'QR detected – looking up product…';
    statusEl.className   = 'message info';

    try {
        const res  = await fetch('api.php?action=get_product&qr_id=' + encodeURIComponent(qrData));
        const data = await res.json();

        if (data.success) {
            scannedType = data.data.type;
            document.getElementById('scannedType').textContent        = '♻️ ' + scannedType;
            document.getElementById('scannedManufacturer').textContent = data.data.manufacturer;

            // Check bin fullness for this type
            await checkBinFull(scannedType);

            statusEl.style.display = 'none';
            document.getElementById('scanResult').style.display = '';
        } else {
            statusEl.textContent = '❌ Product not found. Is this a registered plastic item?';
            statusEl.className   = 'message error';
            document.getElementById('scanResult').style.display = 'none';
            // Re-start camera after 2 s
            setTimeout(startCamera, 2000);
        }
    } catch {
        statusEl.textContent = 'Network error during product lookup.';
        statusEl.className   = 'message error';
        setTimeout(startCamera, 2000);
    }
}

async function checkBinFull(type) {
    const warningEl   = document.getElementById('binFullWarning');
    const confirmBtn  = document.getElementById('confirmScanBtn');

    try {
        const res  = await fetch('api.php?action=check_bin_type&sync_code=' +
                                  encodeURIComponent(activeSyncCode) + '&type=' + encodeURIComponent(type));
        const data = await res.json();
        isBinFull  = data.full === true;
    } catch {
        isBinFull = false; // If check fails, allow disposal attempt
    }

    warningEl.style.display  = isBinFull  ? '' : 'none';
    confirmBtn.disabled      = isBinFull;
}

function rescan() {
    document.getElementById('scanResult').style.display = 'none';
    document.getElementById('scanStatus').style.display = '';
    document.getElementById('scanStatus').textContent   = 'Scanning…';
    document.getElementById('scanStatus').className     = 'message info';
    startCamera();
}

// ── STEP 3: Create disposal & wait for hardware ────────────────────────────────
async function submitDisposal() {
    if (isBinFull) return;

    showStep(3);
    document.getElementById('hardwareStatus').textContent = '';

    try {
        const res  = await fetch('api.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                action:    'create_disposal',
                sync_code: activeSyncCode,
                type:      scannedType
            })
        });
        const data = await res.json();

        if (data.success) {
            currentDisposalId = data.data.id;
            document.getElementById('hardwareStatus').textContent =
                'Disposal #' + currentDisposalId + ' sent to bin. Waiting for hardware…';
            startHardwarePoll();
        } else {
            document.getElementById('hardwareStatus').textContent =
                'Error: ' + (data.error || 'Could not create disposal request.');
            document.getElementById('hardwareStatus').className = 'message error';
        }
    } catch {
        document.getElementById('hardwareStatus').textContent = 'Network error – could not contact server.';
        document.getElementById('hardwareStatus').className   = 'message error';
    }
}

function startHardwarePoll() {
    clearInterval(pollInterval);
    let attempts = 0;
    const maxAttempts = 90; // 3 min at 2s interval

    pollInterval = setInterval(async () => {
        attempts++;
        try {
            const res  = await fetch('api.php?action=check_confirmed&id=' + currentDisposalId);
            const data = await res.json();

            if (data.confirmed) {
                clearInterval(pollInterval);
                showStep(4);
            } else if (attempts >= maxAttempts) {
                clearInterval(pollInterval);
                document.getElementById('hardwareStatus').textContent =
                    'Timed out waiting for hardware. Please try again.';
                document.getElementById('hardwareStatus').className = 'message error';
            }
        } catch { /* ignore transient errors */ }
    }, 2000);
}

// ── STEP 4: Scan more or done ──────────────────────────────────────────────────
function scanMore() {
    showStep(2);
    scannedType = '';
    currentDisposalId = null;
    clearInterval(pollInterval);
    startCamera();
}

// ── Reset all state ────────────────────────────────────────────────────────────
function resetDisposeState() {
    activeSyncCode    = '';
    scannedType       = '';
    isBinFull         = false;
    currentDisposalId = null;

    stopCamera();
    clearInterval(pollInterval);

    document.getElementById('syncCodeInput').value = '';
    document.getElementById('syncError').style.display   = 'none';
    document.getElementById('scanResult').style.display  = 'none';
    document.getElementById('scanStatus').textContent    = '';
    document.getElementById('hardwareStatus').textContent = '';
    document.getElementById('hardwareStatus').className  = 'message info';
    document.getElementById('activeCodeDisplay').textContent = '';
}

// ── Click outside modal to close ──────────────────────────────────────────────
window.addEventListener('click', function (e) {
    document.querySelectorAll('.modal').forEach(m => {
        if (e.target === m) {
            // For dispose modal, also clean up camera
            if (m.id === 'disposeModal') closeDispose();
            else closeModal(m.id);
        }
    });
});

// Auto-uppercase sync code input
document.getElementById('syncCodeInput').addEventListener('input', function () {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
});
</script>
</body>
</html>
