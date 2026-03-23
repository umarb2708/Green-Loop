<?php
/**
 * Green Loop API - Upload Rewards
 * Called by hardware to upload reward code and points
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
if (empty($data['unique_code']) || !isset($data['points']) || empty($data['bin_id'])) {
    sendError('Missing required fields: unique_code, points, bin_id');
}

$uniqueCode = sanitize(strtoupper($data['unique_code']));
$points = intval($data['points']);
$binId = intval($data['bin_id']);

// Validate unique code format (6 characters alphanumeric)
if (!preg_match('/^[A-Z0-9]{6}$/', $uniqueCode)) {
    sendError('Invalid unique_code format. Must be 6 alphanumeric characters');
}

// Check if bin exists
$stmt = $conn->prepare("SELECT * FROM bin_data WHERE id = ?");
$stmt->execute([$binId]);
$bin = $stmt->fetch();

if (!$bin) {
    sendError('Bin not found', 404);
}

// Check if code already exists
$stmt = $conn->prepare("SELECT * FROM rewards_data WHERE unique_code = ?");
$stmt->execute([$uniqueCode]);
if ($stmt->fetch()) {
    sendError('Reward code already exists');
}

try {
    // Insert reward data
    $stmt = $conn->prepare("INSERT INTO rewards_data (unique_code, points, bin_id, collected) VALUES (?, ?, ?, 0)");
    $stmt->execute([$uniqueCode, $points, $binId]);
    
    sendSuccess('Reward uploaded successfully', [
        'unique_code' => $uniqueCode,
        'points' => $points,
        'bin_id' => $binId
    ]);
    
} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
?>
