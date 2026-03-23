<?php
/**
 * Green Loop - Sample Data Generator
 * This script populates the database with test data
 * Run this file once to set up sample users, bins, and products
 */

require_once '../config.php';

// Set execution time limit
set_time_limit(300);

echo "<!DOCTYPE html><html><head><title>Green Loop - Sample Data</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#f0f0f0;}";
echo ".success{color:green;}.error{color:red;}.info{color:blue;}</style></head><body>";
echo "<h1>Green Loop - Sample Data Generator</h1>";

try {
    // Clear existing data (optional - comment out if you want to keep existing data)
    echo "<p class='info'>Clearing existing sample data...</p>";
    
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
    $conn->exec("TRUNCATE TABLE activity_log");
    $conn->exec("TRUNCATE TABLE bin_history");
    $conn->exec("TRUNCATE TABLE disposal_data");
    $conn->exec("TRUNCATE TABLE rewards_data");
    $conn->exec("TRUNCATE TABLE product_data");
    $conn->exec("TRUNCATE TABLE bin_data");
    $conn->exec("DELETE FROM users WHERE id > 0");
    $conn->exec("ALTER TABLE users AUTO_INCREMENT = 1");
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "<p class='success'>✓ Existing data cleared</p>";
    
    // ========== Insert Sample Users ==========
    echo "<h2>Creating Sample Users...</h2>";
    
    $users = [
        [
            'name' => 'System Administrator',
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'is_admin' => 1,
            'rewards' => 0
        ],
        [
            'name' => 'Coca Cola Company',
            'username' => 'cocacola',
            'password' => password_hash('manufacturer123', PASSWORD_DEFAULT),
            'is_admin' => 2,
            'rewards' => 0
        ],
        [
            'name' => 'PepsiCo',
            'username' => 'pepsi',
            'password' => password_hash('manufacturer123', PASSWORD_DEFAULT),
            'is_admin' => 2,
            'rewards' => 0
        ],
        [
            'name' => 'John Doe',
            'username' => 'johndoe',
            'password' => password_hash('user123', PASSWORD_DEFAULT),
            'is_admin' => 0,
            'rewards' => 150
        ],
        [
            'name' => 'Jane Smith',
            'username' => 'janesmith',
            'password' => password_hash('user123', PASSWORD_DEFAULT),
            'is_admin' => 0,
            'rewards' => 320
        ],
        [
            'name' => 'Mike Johnson',
            'username' => 'mikej',
            'password' => password_hash('user123', PASSWORD_DEFAULT),
            'is_admin' => 0,
            'rewards' => 85
        ],
        [
            'name' => 'Sarah Williams',
            'username' => 'sarahw',
            'password' => password_hash('user123', PASSWORD_DEFAULT),
            'is_admin' => 0,
            'rewards' => 420
        ]
    ];
    
    $stmt = $conn->prepare("INSERT INTO users (name, username, password, is_admin, rewards_collected) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($users as $user) {
        $stmt->execute([
            $user['name'],
            $user['username'],
            $user['password'],
            $user['is_admin'],
            $user['rewards']
        ]);
        
        $userType = $user['is_admin'] == 1 ? 'Admin' : ($user['is_admin'] == 2 ? 'Manufacturer' : 'User');
        echo "<p class='success'>✓ Created {$userType}: {$user['username']} (password: ";
        
        if ($user['is_admin'] == 1) echo "admin123";
        elseif ($user['is_admin'] == 2) echo "manufacturer123";
        else echo "user123";
        
        echo ")</p>";
    }
    
    // ========== Insert Sample Bins ==========
    echo "<h2>Creating Sample Bins...</h2>";
    
    $bins = [
        ['location' => 'Main Campus - Building A', 'status' => '0000', 'weight' => 0.0],
        ['location' => 'City Center Mall - Floor 1', 'status' => '0010', 'weight' => 12.5],
        ['location' => 'Green Park - East Entrance', 'status' => '0000', 'weight' => 5.3],
        ['location' => 'Central Station - Platform 2', 'status' => '1000', 'weight' => 18.7],
        ['location' => 'University Library', 'status' => '0000', 'weight' => 2.1]
    ];
    
    $stmt = $conn->prepare("INSERT INTO bin_data (location, current_status, weight) VALUES (?, ?, ?)");
    
    foreach ($bins as $bin) {
        $stmt->execute([$bin['location'], $bin['status'], $bin['weight']]);
        echo "<p class='success'>✓ Created bin at: {$bin['location']} (ID: " . $conn->lastInsertId() . ")</p>";
    }
    
    // ========== Insert Sample Products ==========
    echo "<h2>Creating Sample Products...</h2>";
    
    $products = [
        // Coca Cola Products
        ['manufacturer' => 'Coca Cola Company', 'type' => 'PET'],
        ['manufacturer' => 'Coca Cola Company', 'type' => 'PET'],
        ['manufacturer' => 'Coca Cola Company', 'type' => 'PET'],
        ['manufacturer' => 'Coca Cola Company', 'type' => 'HDPE'],
        
        // PepsiCo Products
        ['manufacturer' => 'PepsiCo', 'type' => 'PET'],
        ['manufacturer' => 'PepsiCo', 'type' => 'PET'],
        ['manufacturer' => 'PepsiCo', 'type' => 'HDPE'],
        
        // Other Manufacturers
        ['manufacturer' => 'Nestle', 'type' => 'PP'],
        ['manufacturer' => 'Nestle', 'type' => 'HDPE'],
        ['manufacturer' => 'Unilever', 'type' => 'PP'],
        ['manufacturer' => 'Unilever', 'type' => 'Others'],
        ['manufacturer' => 'Procter & Gamble', 'type' => 'HDPE'],
        ['manufacturer' => 'Procter & Gamble', 'type' => 'PP'],
        ['manufacturer' => 'Generic Brand', 'type' => 'Others'],
        ['manufacturer' => 'Generic Brand', 'type' => 'Others']
    ];
    
    $stmt = $conn->prepare("INSERT INTO product_data (qr_id, manufacturer, type) VALUES (?, ?, ?)");
    
    foreach ($products as $product) {
        // Generate unique 10-character alphanumeric QR ID
        do {
            $qr_id = generateQRCode(10);
            $check = $conn->query("SELECT COUNT(*) FROM product_data WHERE qr_id = '{$qr_id}'");
        } while ($check->fetchColumn() > 0);
        
        $stmt->execute([$qr_id, $product['manufacturer'], $product['type']]);
        echo "<p class='success'>✓ Created product: {$product['manufacturer']} - {$product['type']} (QR: {$qr_id})</p>";
    }
    
    // ========== Insert Sample Rewards ==========
    echo "<h2>Creating Sample Rewards...</h2>";
    
    $rewards = [
        ['code' => 'ABC123', 'points' => 50, 'bin_id' => 1, 'collected' => 1, 'collected_by' => 4],
        ['code' => 'XYZ789', 'points' => 100, 'bin_id' => 1, 'collected' => 1, 'collected_by' => 5],
        ['code' => 'DEF456', 'points' => 30, 'bin_id' => 2, 'collected' => 0, 'collected_by' => null],
        ['code' => 'GHI789', 'points' => 75, 'bin_id' => 3, 'collected' => 0, 'collected_by' => null],
        ['code' => 'JKL012', 'points' => 40, 'bin_id' => 1, 'collected' => 1, 'collected_by' => 6]
    ];
    
    $stmt = $conn->prepare("INSERT INTO rewards_data (unique_code, points, bin_id, collected, collected_by, collected_at) VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($rewards as $reward) {
        $collected_at = $reward['collected'] ? date('Y-m-d H:i:s') : null;
        $stmt->execute([
            $reward['code'],
            $reward['points'],
            $reward['bin_id'],
            $reward['collected'],
            $reward['collected_by'],
            $collected_at
        ]);
        
        $status = $reward['collected'] ? 'Collected' : 'Available';
        echo "<p class='success'>✓ Created reward: {$reward['code']} - {$reward['points']} points ({$status})</p>";
    }
    
    // ========== Insert Sample Disposal Data ==========
    echo "<h2>Creating Sample Disposal Entries...</h2>";
    
    // Get some product QR IDs
    $qr_codes = $conn->query("SELECT qr_id, type FROM product_data LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $conn->prepare("INSERT INTO disposal_data (bin_id, type, qr_id, user_id, confirmed, confirmed_at) VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($qr_codes as $index => $product) {
        $confirmed = $index < 3 ? 1 : 0;
        $confirmed_at = $confirmed ? date('Y-m-d H:i:s') : null;
        $user_id = 4 + ($index % 4); // Rotate through users 4-7
        
        $stmt->execute([
            1 + ($index % 5), // bin_id
            $product['type'],
            $product['qr_id'],
            $user_id,
            $confirmed,
            $confirmed_at
        ]);
        
        $status = $confirmed ? 'Confirmed' : 'Pending';
        echo "<p class='success'>✓ Created disposal: {$product['type']} ({$status})</p>";
    }
    
    // ========== Summary ==========
    echo "<h2>Summary</h2>";
    
    $stats = [
        'users' => $conn->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'bins' => $conn->query("SELECT COUNT(*) FROM bin_data")->fetchColumn(),
        'products' => $conn->query("SELECT COUNT(*) FROM product_data")->fetchColumn(),
        'rewards' => $conn->query("SELECT COUNT(*) FROM rewards_data")->fetchColumn(),
        'disposals' => $conn->query("SELECT COUNT(*) FROM disposal_data")->fetchColumn()
    ];
    
    echo "<div style='background:white;padding:15px;border-radius:5px;'>";
    echo "<p><strong>Total Users:</strong> {$stats['users']}</p>";
    echo "<p><strong>Total Bins:</strong> {$stats['bins']}</p>";
    echo "<p><strong>Total Products:</strong> {$stats['products']}</p>";
    echo "<p><strong>Total Rewards:</strong> {$stats['rewards']}</p>";
    echo "<p><strong>Total Disposals:</strong> {$stats['disposals']}</p>";
    echo "</div>";
    
    echo "<h2 class='success'>✓ Sample data created successfully!</h2>";
    
    echo "<h3>Test Accounts:</h3>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> username: admin | password: admin123</li>";
    echo "<li><strong>Manufacturer:</strong> username: cocacola | password: manufacturer123</li>";
    echo "<li><strong>Manufacturer:</strong> username: pepsi | password: manufacturer123</li>";
    echo "<li><strong>User:</strong> username: johndoe | password: user123</li>";
    echo "<li><strong>User:</strong> username: janesmith | password: user123</li>";
    echo "</ul>";
    
    echo "<p><a href='../index.php'>Go to Login Page</a></p>";
    
} catch (PDOException $e) {
    echo "<p class='error'>✗ Database Error: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
}

echo "</body></html>";

/**
 * Generate random alphanumeric code
 */
function generateQRCode($length = 10) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}
?>
