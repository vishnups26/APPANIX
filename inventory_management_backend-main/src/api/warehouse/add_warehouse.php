<?php
// filepath: /home/anix-sam/Desktop/work/stock_management/backend/src/api/warehouse/add_warehouse.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database connection
require_once __DIR__ . '/../../../db/connect.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array("status" => false, "message" => "Only POST method allowed"));
    exit();
}

// Create database instance
$database = new Database();
$db = $database->getConnection();

if ($db == null) {
    http_response_code(500);
    echo json_encode(array("status" => false, "message" => "Database connection failed"));
    exit();
}

$data = json_decode(file_get_contents("php://input"));

// Check if all required fields are provided (including userId)
if (
    isset($data->userId, $data->warehouse_name, $data->address, $data->longitude, $data->latitude, $data->city, $data->country, $data->state) 
)  {
    
    // Validate userId is numeric
    if (!is_numeric($data->userId)) {
        http_response_code(400);
        echo json_encode(array(
            "status" => false,
            "message" => "User ID must be numeric",
            "method" => "POST"
        ));
        return;
    }
    
    // Validate coordinates
    if (!is_numeric($data->longitude) || !is_numeric($data->latitude)) {
        http_response_code(400);
        echo json_encode(array(
            "status" => false,
            "message" => "Longitude and latitude must be numeric values",
            "method" => "POST"
        ));
        return;
    }
    
    
    try {
        
        // Check if user exists
        $user_check_query = "SELECT id, username, userRole FROM `users` WHERE id = ?";
        $user_check_stmt = $db->prepare($user_check_query);
        $user_check_stmt->bindParam(1, $data->userId);
        $user_check_stmt->execute();
        
        if ($user_check_stmt->rowCount() == 0) {
            http_response_code(404);
            echo json_encode(array(
                "status" => false,
                "message" => "User not found. Invalid userId.",
                "method" => "POST"
            ));
            return;
        }
        
        $user_data = $user_check_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Create warehouse table if it doesn't exist (with userId foreign key)
        $create_table_query = "CREATE TABLE IF NOT EXISTS `warehouse` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            userId INT NOT NULL,
            warehouse_name VARCHAR(100) NOT NULL,
            warehouse_address TEXT NOT NULL,
            longitude DECIMAL(11, 8) NOT NULL,
            latitude DECIMAL(10, 8) NOT NULL,
            city VARCHAR(100) NOT NULL,
            state VARCHAR(100) NOT NULL,
            country VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_warehouse_per_user (userId, warehouse_name)
        )";
        
        $create_stmt = $db->prepare($create_table_query);
        $create_stmt->execute();
        
        // Check if store name already exists for this user
        $check_query = "SELECT id FROM `warehouse` WHERE warehouse_name = ? AND userId = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(1, $data->warehouse_name);
        $check_stmt->bindParam(2, $data->userId);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            http_response_code(409);
            echo json_encode(array(
                "status" => false,
                "message" => "warehouse name already exists for this user",
                "method" => "POST"
            ));
            return;
        }
        
        // Insert warehouse data with userId
        $insert_query = "INSERT INTO `warehouse` (userId, warehouse_name, warehouse_address, longitude, latitude, city, state, country) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(1, $data->userId);
        $insert_stmt->bindParam(2, $data->warehouse_name);
        $insert_stmt->bindParam(3, $data->address);
        $insert_stmt->bindParam(4, $data->longitude);
        $insert_stmt->bindParam(5, $data->latitude);
        $insert_stmt->bindParam(6, $data->city);
        $insert_stmt->bindParam(7, $data->state);
        $insert_stmt->bindParam(8, $data->country);
        if ($insert_stmt->execute()) {
            $id = $db->lastInsertId();
            
            // Get user's total warehouse count
            $count_query = "SELECT COUNT(*) as user_warehouse FROM `warehouse` WHERE userId = ?";
            $count_stmt = $db->prepare($count_query);
            $count_stmt->bindParam(1, $data->userId);
            $count_stmt->execute();
            $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get total warehouse count
            $total_count_query = "SELECT COUNT(*) as total_warehouse FROM `warehouse`";
            $total_count_stmt = $db->prepare($total_count_query);
            $total_count_stmt->execute();
            $total_count_result = $total_count_stmt->fetch(PDO::FETCH_ASSOC);
            
            http_response_code(201);
            echo json_encode(array(
                "status" => true,
                "message" => "Warehouse created successfully",
                "method" => "POST",
                "warehouse_data" => array(
                    "id" => $id,
                    "userId" => $data->userId,
                    "owner_username" => $user_data['username'],
                    "owner_role" => $user_data['userRole'],
                    "warehouse" => $data->warehouse_name,
                    "warehouse_address" => $data->warehouse_address,
                    "longitude" => $data->longitude,
                    "latitude" => $data->latitude,
                    "ecommerce_enabled" => $data->is_online_store ? "Yes" : "No"
                ),
                "statistics" => array(
                    "user_total_warehouse" => $count_result['user_warehouse'],
                    "system_total_warehouse" => $total_count_result['total_warehouse']
                )
            ));
            
        } else {
            http_response_code(500);
            echo json_encode(array(
                "status" => false,
                "message" => "Failed to create warehouse",
                "method" => "POST"
            ));
        }
        
    } catch(PDOException $exception) {
        http_response_code(500);
        echo json_encode(array(
            "status" => false,
            "message" => "Database operation failed",
            "method" => "POST",
            "error" => $exception->getMessage()
        ));
    }
    
} else {
    http_response_code(400);
    echo json_encode(array(
        "status" => false,
        "message" => "Incomplete data. Required fields: userId, warehouse, warehouse_address, longitude, latitude",
        "method" => "POST",
        "example_request" => array(
            "userId" => 1,
            "warehouse_name" => "Test Warehouse",
            "address" => "123 Main Street, City, Country",
            "longitude" => -122.4194,
            "latitude" => 37.7749
        )
    ));
}

?>