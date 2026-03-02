<?php
// Helper file to get bin status via AJAX
require_once 'config.php';
requireAdmin();

header('Content-Type: application/json');

if (isset($_GET['bin_id'])) {
    $bin_id = intval($_GET['bin_id']);
    
    $conn = getDBConnection();
    $sql = "SELECT current_status, weight FROM bin_data WHERE id = $bin_id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'status' => $data['current_status'],
            'weight' => floatval($data['weight'])
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Bin not found'
        ]);
    }
    
    $conn->close();
} else {
    echo json_encode([
        'success' => false,
        'error' => 'No bin ID provided'
    ]);
}
?>
