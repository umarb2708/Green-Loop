<?php
/**
 * Green Loop API - Add Disposal
 * Called by user dashboard to add disposal request
 */

header('Content-Type: application/json');
require_once '../config.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    sendError('Invalid JSON data');
}

// Validate required fields
if (empty($data['qr_id']) || empty($data['bin_id'])) {
    sendError('Missing required fields: qr_id, bin_id');
}

$qrId = sanitize(strtoupper($data['qr_id']));
$binId = intval($data['bin_id']);
$userId = $_SESSION['user_id'] ?? null;

// Get product info
$stmt = $conn->prepare("SELECT * FROM product_data WHERE qr_id = ?");
$stmt->execute([$qrId]);
$product = $stmt->fetch();

if (!$product) {
    sendError('Invalid QR code. Product not found.');
}

// Check if bin exists
$stmt = $conn->prepare("SELECT * FROM bin_data WHERE id = ?");
$stmt->execute([$binId]);
$bin = $stmt->fetch();

if (!$bin) {
    sendError('Bin not found', 404);
}

// Check if chamber is available
$chamberIndex = getChamberIndex($product['type']);
if ($chamberIndex === -1) {
    sendError('Invalid plastic type');
}

if (!isChamberAvailable($bin['current_status'], $chamberIndex)) {
    sendError('The ' . $product['type'] . ' chamber is currently full. Please try another bin.');
}

try {
    // Add disposal request
    $stmt = $conn->prepare("INSERT INTO disposal_data (bin_id, type, qr_id, user_id, confirmed) VALUES (?, ?, ?, ?, 0)");
    $stmt->execute([$binId, $product['type'], $qrId, $userId]);
    
    if ($userId) {
        logActivity($userId, 'add_disposal', "Scanned QR {$qrId} for disposal at bin {$binId}");
    }
    
    sendSuccess('Disposal request added. Please dispose the plastic in the ' . $product['type'] . ' chamber.', [
        'type' => $product['type'],
        'manufacturer' => $product['manufacturer'],
        'bin_id' => $binId,
        'location' => $bin['location']
    ]);
    
} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
?>
