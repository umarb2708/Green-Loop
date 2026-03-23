<?php
/**
 * Green Loop API - Update Bin Status
 * Called by hardware to update bin status and weight
 */

header('Content-Type: application/json');
require_once '../config.php';

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    sendError('Invalid JSON data');
}

// Validate required fields
if (empty($data['bin_id']) || !isset($data['weight']) || !isset($data['bin_status'])) {
    sendError('Missing required fields: bin_id, weight, bin_status');
}

$binId = intval($data['bin_id']);
$weight = floatval($data['weight']);
$binStatus = sanitize($data['bin_status']);

// Validate bin status format (must be 4 characters, each 0 or 1)
if (!preg_match('/^[01]{4}$/', $binStatus)) {
    sendError('Invalid bin_status format. Must be 4 digits of 0 or 1');
}

// Check if bin exists
$stmt = $conn->prepare("SELECT * FROM bin_data WHERE id = ?");
$stmt->execute([$binId]);
$bin = $stmt->fetch();

if (!$bin) {
    sendError('Bin not found', 404);
}

try {
    // Update bin status
    $stmt = $conn->prepare("UPDATE bin_data SET current_status = ?, weight = ?, last_updated = NOW() WHERE id = ?");
    $stmt->execute([$binStatus, $weight, $binId]);
    
    // Optional: Log to history
    $stmt = $conn->prepare("INSERT INTO bin_history (bin_id, status, weight) VALUES (?, ?, ?)");
    $stmt->execute([$binId, $binStatus, $weight]);
    
    sendSuccess('Bin status updated successfully', [
        'bin_id' => $binId,
        'status' => $binStatus,
        'weight' => $weight
    ]);
    
} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
?>
