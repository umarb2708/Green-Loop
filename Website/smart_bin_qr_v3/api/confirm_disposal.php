<?php
/**
 * Green Loop API - Confirm Disposal
 * Called by hardware to confirm disposal was successful
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
if (!isset($data['chamber']) || !isset($data['confirmed'])) {
    sendError('Missing required fields: chamber, confirmed');
}

$chamber = intval($data['chamber']);
$confirmed = intval($data['confirmed']);

try {
    // We need to update the most recent unconfirmed disposal
    // Since we don't have disposal_id in the request, we'll update based on chamber type
    // This assumes chamber maps to plastic type as in firmware
    $types = ['PET', 'HDPE', 'PP', 'Others'];
    $type = $types[$chamber] ?? null;
    
    if (!$type) {
        sendError('Invalid chamber number');
    }
    
    // Update most recent unconfirmed disposal of this type
    $stmt = $conn->prepare("UPDATE disposal_data 
                            SET confirmed = ?, confirmed_at = NOW() 
                            WHERE confirmed = 0 AND type = ? 
                            ORDER BY created_at ASC 
                            LIMIT 1");
    $stmt->execute([$confirmed, $type]);
    
    if ($stmt->rowCount() > 0) {
        sendSuccess('Disposal confirmed successfully');
    } else {
        sendError('No matching disposal found to confirm');
    }
    
} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
?>
