<?php
/**
 * Green Loop API - Get Disposal Data
 * Called by hardware to poll for pending disposal requests
 */

header('Content-Type: application/json');
require_once '../config.php';

// Get bin_id from query parameter
$binId = isset($_GET['bin_id']) ? intval($_GET['bin_id']) : 0;

if ($binId <= 0) {
    sendError('Invalid bin_id');
}

// Check if bin exists
$stmt = $conn->prepare("SELECT * FROM bin_data WHERE id = ?");
$stmt->execute([$binId]);
$bin = $stmt->fetch();

if (!$bin) {
    sendError('Bin not found', 404);
}

try {
    // Get oldest unconfirmed disposal for this bin
    $stmt = $conn->prepare("SELECT d.*, p.manufacturer 
                            FROM disposal_data d 
                            LEFT JOIN product_data p ON d.qr_id = p.qr_id 
                            WHERE d.bin_id = ? AND d.confirmed = 0 
                            ORDER BY d.created_at ASC 
                            LIMIT 1");
    $stmt->execute([$binId]);
    $disposal = $stmt->fetch();
    
    if ($disposal) {
        sendSuccess('Disposal request found', [
            'id' => $disposal['id'],
            'type' => $disposal['type'],
            'qr_id' => $disposal['qr_id'],
            'manufacturer' => $disposal['manufacturer']
        ]);
    } else {
        // No pending disposals
        sendJSON(['success' => false, 'message' => 'No pending disposals']);
    }
    
} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
?>
