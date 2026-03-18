<?php
/**
 * Green Loop API - QR-on-Website Version
 * Handles all firmware ↔ website communication.
 *
 * POST actions  (JSON body): register_sync | create_disposal | confirm_disposal | upload_data
 * GET  actions  (query str): get_product   | validate_sync   | get_disposal     |
 *                            check_confirmed | check_bin_type | get_bin_status
 */
require_once 'config.php';

header('Content-Type: application/json');

// ── Route ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!isset($data['action'])) { sendError("No action specified"); }

    switch ($data['action']) {
        case 'register_sync':    registerSync($data);    break;
        case 'create_disposal':  createDisposal($data);  break;
        case 'confirm_disposal': confirmDisposal($data); break;
        case 'upload_data':      uploadData($data);      break;
        default:                 sendError("Invalid action");
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['action'])) { sendError("No action specified"); }

    switch ($_GET['action']) {
        case 'get_product':      getProduct();      break;
        case 'validate_sync':    validateSync();    break;
        case 'get_disposal':     getDisposal();     break;
        case 'check_confirmed':  checkConfirmed();  break;
        case 'check_bin_type':   checkBinType();    break;
        case 'get_bin_status':   getBinStatus();    break;
        default:                 sendError("Invalid action");
    }
} else {
    sendError("Method not allowed");
}

// ═══════════════════════════════════════════════════════════════════════════════
// POST HANDLERS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Hardware calls this right after generating the SYNC CODE.
 * Registers the sync code → bin_id relationship.
 *
 * Body: { "action":"register_sync", "sync_code":"ABCXYZ", "bin_id":"1" }
 */
function registerSync($data) {
    if (empty($data['sync_code']) || empty($data['bin_id'])) {
        sendError("Missing sync_code or bin_id");
    }

    $conn      = getDBConnection();
    $sync_code = $conn->real_escape_string(strtoupper(trim($data['sync_code'])));
    $bin_id    = intval($data['bin_id']);

    // Delete stale sessions with same code (shouldn't happen, but safety first)
    $conn->query("DELETE FROM sync_sessions WHERE sync_code = '$sync_code'");

    $sql = "INSERT INTO sync_sessions (sync_code, bin_id, active)
            VALUES ('$sync_code', $bin_id, 1)";

    if (!$conn->query($sql)) {
        $conn->close();
        sendError("Failed to register sync: " . $conn->error);
    }
    $conn->close();
    sendSuccess("Sync code registered");
}

/**
 * Website calls this after the user scans a QR and the bin is not full.
 * Creates a pending disposal row for the hardware to pick up.
 *
 * Body: { "action":"create_disposal", "sync_code":"ABCXYZ", "type":"PET" }
 * Returns: { "success":true, "id":42 }
 */
function createDisposal($data) {
    if (!isset($_SESSION['user_id'])) { sendError("Not authenticated"); }
    if (empty($data['sync_code']) || empty($data['type'])) {
        sendError("Missing sync_code or type");
    }

    $allowed_types = ['PET','HDPE','PP','Others'];
    $type = strtoupper(trim($data['type']));
    // Normalise 'others' → 'Others'
    if (strtolower($type) === 'others') $type = 'Others';

    if (!in_array($type, $allowed_types)) {
        sendError("Invalid plastic type");
    }

    $conn      = getDBConnection();
    $sync_code = $conn->real_escape_string(strtoupper(trim($data['sync_code'])));

    // Make sure sync code is registered and active
    $check = $conn->query("SELECT id FROM sync_sessions
                           WHERE sync_code = '$sync_code' AND active = 1 LIMIT 1");
    if ($check->num_rows === 0) {
        $conn->close();
        sendError("Invalid or expired sync code");
    }

    $sql = "INSERT INTO disposal (sync_code, type, confirmed)
            VALUES ('$sync_code', '$type', 0)";
    if (!$conn->query($sql)) {
        $conn->close();
        sendError("Failed to create disposal");
    }
    $insert_id = $conn->insert_id;
    $conn->close();
    sendSuccess("Disposal created", ['id' => $insert_id]);
}

/**
 * Hardware calls this after physically completing the disposal.
 * Sets confirmed = 1 for the given disposal row.
 *
 * Body: { "action":"confirm_disposal", "id":42 }
 */
function confirmDisposal($data) {
    if (empty($data['id'])) { sendError("Missing disposal id"); }

    $conn = getDBConnection();
    $id   = intval($data['id']);

    $sql = "UPDATE disposal SET confirmed = 1 WHERE id = $id AND confirmed = 0";
    if (!$conn->query($sql) || $conn->affected_rows === 0) {
        $conn->close();
        sendError("Confirm failed or already confirmed");
    }
    $conn->close();
    sendSuccess("Disposal confirmed");
}

/**
 * Hardware calls this at the end of the session with accumulated totals.
 *
 * Body: { "action":"upload_data", "points":45, "unique_code":"ABC123",
 *         "bin_weight":320.5, "bin_status":"0010", "bin_id":"1" }
 */
function uploadData($data) {
    if (!isset($data['points'], $data['unique_code'],
                $data['bin_weight'], $data['bin_status'], $data['bin_id'])) {
        sendError("Missing required fields");
    }

    $conn        = getDBConnection();
    $points      = intval($data['points']);
    $unique_code = $conn->real_escape_string(strtoupper(trim($data['unique_code'])));
    $bin_weight  = floatval($data['bin_weight']);
    $bin_status  = $conn->real_escape_string($data['bin_status']);
    $bin_id      = intval($data['bin_id']);

    // Insert reward record
    $sql = "INSERT INTO rewards_data (unique_code, points, bin_id)
            VALUES ('$unique_code', $points, $bin_id)";
    if (!$conn->query($sql)) {
        $conn->close();
        sendError("Failed to insert reward: " . $conn->error);
    }

    // Update bin data
    $sql = "UPDATE bin_data SET current_status='$bin_status', weight=$bin_weight
            WHERE id = $bin_id";
    $conn->query($sql);

    // Mark sync sessions for this bin as inactive
    $conn->query("UPDATE sync_sessions SET active=0 WHERE bin_id=$bin_id AND active=1");

    $conn->close();
    sendSuccess("Data uploaded");
}

// ═══════════════════════════════════════════════════════════════════════════════
// GET HANDLERS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Website uses this to look up plastic type from a scanned QR code.
 * GET ?action=get_product&qr_id=ABC1234567
 * Returns: { "success":true, "type":"PET", "manufacturer":"Coca Cola" }
 */
function getProduct() {
    if (empty($_GET['qr_id'])) { sendError("qr_id required"); }

    $conn   = getDBConnection();
    $qr_id  = $conn->real_escape_string(trim($_GET['qr_id']));
    $result = $conn->query("SELECT type, manufacturer FROM product_data
                            WHERE qr_id = '$qr_id' LIMIT 1");

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $conn->close();
        sendSuccess("Product found", [
            'type'         => $row['type'],
            'manufacturer' => $row['manufacturer']
        ]);
    } else {
        $conn->close();
        sendError("Product not found");
    }
}

/**
 * Website uses this to confirm a sync code is active before proceeding.
 * GET ?action=validate_sync&sync_code=ABCXYZ
 * Returns: { "success":true, "bin_id":1 }
 */
function validateSync() {
    if (empty($_GET['sync_code'])) { sendError("sync_code required"); }

    $conn      = getDBConnection();
    $sync_code = $conn->real_escape_string(strtoupper(trim($_GET['sync_code'])));
    $result    = $conn->query("SELECT bin_id FROM sync_sessions
                               WHERE sync_code='$sync_code' AND active=1 LIMIT 1");

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $conn->close();
        sendSuccess("Valid sync code", ['bin_id' => intval($row['bin_id'])]);
    } else {
        $conn->close();
        sendError("Invalid or expired sync code");
    }
}

/**
 * Hardware polls this to find a pending (unconfirmed) disposal for its sync code.
 * GET ?action=get_disposal&sync_code=ABCXYZ
 * Returns: { "found":true,  "id":5, "type":"PET" }
 *       or { "found":false }
 */
function getDisposal() {
    if (empty($_GET['sync_code'])) { sendError("sync_code required"); }

    $conn      = getDBConnection();
    $sync_code = $conn->real_escape_string(strtoupper(trim($_GET['sync_code'])));
    $result    = $conn->query("SELECT id, type FROM disposal
                               WHERE sync_code='$sync_code' AND confirmed=0
                               ORDER BY created_at ASC LIMIT 1");

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $conn->close();
        echo json_encode(['found' => true, 'id' => intval($row['id']), 'type' => $row['type']]);
    } else {
        $conn->close();
        echo json_encode(['found' => false]);
    }
    exit();
}

/**
 * Website polls this to know when hardware has physically completed a disposal.
 * GET ?action=check_confirmed&id=42
 * Returns: { "confirmed":true } or { "confirmed":false }
 */
function checkConfirmed() {
    if (empty($_GET['id'])) { sendError("id required"); }

    $conn   = getDBConnection();
    $id     = intval($_GET['id']);
    $result = $conn->query("SELECT confirmed FROM disposal WHERE id=$id LIMIT 1");

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $conn->close();
        echo json_encode(['confirmed' => (bool)$row['confirmed']]);
    } else {
        $conn->close();
        echo json_encode(['confirmed' => false]);
    }
    exit();
}

/**
 * Website uses this to check if a specific bin chamber is full before creating disposal.
 * GET ?action=check_bin_type&sync_code=ABCXYZ&type=PET
 * Returns: { "full":false }
 */
function checkBinType() {
    if (empty($_GET['sync_code']) || empty($_GET['type'])) {
        sendError("sync_code and type required");
    }

    $type_index = ['PET' => 0, 'HDPE' => 1, 'PP' => 2, 'Others' => 3];
    $type = strtoupper(trim($_GET['type']));
    if (strtolower($type) === 'others') $type = 'Others';

    if (!array_key_exists($type, $type_index)) {
        sendError("Invalid type");
    }

    $conn      = getDBConnection();
    $sync_code = $conn->real_escape_string(strtoupper(trim($_GET['sync_code'])));

    $result = $conn->query("SELECT b.current_status FROM sync_sessions s
                            JOIN bin_data b ON s.bin_id = b.id
                            WHERE s.sync_code='$sync_code' AND s.active=1 LIMIT 1");

    if ($result->num_rows > 0) {
        $row    = $result->fetch_assoc();
        $status = $row['current_status'];
        $idx    = $type_index[$type];
        $is_full = strlen($status) > $idx && $status[$idx] === '1';
        $conn->close();
        echo json_encode(['full' => $is_full]);
    } else {
        $conn->close();
        // Unknown bin - treat as not full so disposal can proceed
        echo json_encode(['full' => false]);
    }
    exit();
}

/**
 * Admin/dashboard uses this to get bin status.
 * GET ?action=get_bin_status&bin_id=1
 */
function getBinStatus() {
    if (empty($_GET['bin_id'])) { sendError("bin_id required"); }

    $conn   = getDBConnection();
    $bin_id = intval($_GET['bin_id']);
    $result = $conn->query("SELECT current_status, weight, location
                            FROM bin_data WHERE id=$bin_id LIMIT 1");

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $conn->close();
        sendSuccess("Bin found", [
            'status'   => $row['current_status'],
            'weight'   => floatval($row['weight']),
            'location' => $row['location']
        ]);
    } else {
        $conn->close();
        sendError("Bin not found");
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════════════════════════════════════════
function sendSuccess($message, $data = null) {
    $res = ['success' => true, 'message' => $message];
    if ($data !== null) $res['data'] = $data;
    echo json_encode($res);
    exit();
}

function sendError($message) {
    echo json_encode(['success' => false, 'error' => $message]);
    exit();
}
?>
