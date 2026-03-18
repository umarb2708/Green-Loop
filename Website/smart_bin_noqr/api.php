<?php
// API for Green Loop - Handles all backend requests
require_once 'config.php';

header('Content-Type: application/json');

// Get request data
$request_body = file_get_contents('php://input');
$data = json_decode($request_body, true);

// Handle different actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($data['action'])) {
        switch ($data['action']) {
            case 'upload_data':
                uploadData($data);
                break;
            default:
                sendError("Invalid action");
        }
    } else {
        sendError("No action specified");
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'get_product':
                getProductData($_GET['qr_id']);
                break;
            default:
                sendError("Invalid action");
        }
    }
}

// Upload data from hardware
function uploadData($data) {
    $conn = getDBConnection();
    
    // Validate required fields
    if (!isset($data['points']) || !isset($data['unique_code']) || 
        !isset($data['bin_weight']) || !isset($data['bin_status']) || 
        !isset($data['bin_id'])) {
        sendError("Missing required fields");
        return;
    }
    
    $points = intval($data['points']);
    $unique_code = $conn->real_escape_string($data['unique_code']);
    $bin_weight = floatval($data['bin_weight']);
    $bin_status = $conn->real_escape_string($data['bin_status']);
    $bin_id = intval($data['bin_id']);
    
    // Insert into rewards_data
    $sql = "INSERT INTO rewards_data (unique_code, points, bin_id) VALUES ('$unique_code', $points, $bin_id)";
    
    if (!$conn->query($sql)) {
        sendError("Failed to insert reward data: " . $conn->error);
        $conn->close();
        return;
    }
    
    // Update bin_data
    $sql = "UPDATE bin_data SET current_status = '$bin_status', weight = $bin_weight WHERE id = $bin_id";
    
    if (!$conn->query($sql)) {
        sendError("Failed to update bin data: " . $conn->error);
        $conn->close();
        return;
    }
    
    $conn->close();
    sendSuccess("Data uploaded successfully");
}

// Get product data by QR ID
function getProductData($qr_id) {
    $conn = getDBConnection();
    
    if (!isset($qr_id)) {
        sendError("QR ID not provided");
        return;
    }
    
    $qr_id = $conn->real_escape_string($qr_id);
    $sql = "SELECT type FROM product_data WHERE qr_id = '$qr_id'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $conn->close();
        echo $row['type']; // Return just the type
    } else {
        $conn->close();
        echo ""; // Return empty string if not found
    }
}

// Helper functions
function sendSuccess($message, $data = null) {
    $response = ["success" => true, "message" => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit();
}

function sendError($message) {
    echo json_encode(["success" => false, "error" => $message]);
    exit();
}
?>
