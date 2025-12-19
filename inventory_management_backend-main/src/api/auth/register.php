<?php
// filepath: /home/anix-sam/Desktop/work/stock_management/backend/src/api/auth/register.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database connection
require_once __DIR__ . '/../../../db/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array("status" => false, "message" => "Only POST method allowed"));
    exit();
}

// Function to call add_warehouse.php API for suppliers
function callAddWarehouseAPI($user_id, $user_data) {
    // Prepare warehouse data matching add_warehouse.php requirements EXACTLY
    $warehouse_data = array(
        "userId" => $user_id,
        "warehouse_name" => $user_data['firstname'] . "'s Default Warehouse",
        "address" => $user_data['address'], // Changed back to "address" as per add_warehouse.php
        "longitude" => 0.0,
        "latitude" => 0.0,
        "city" => "To be updated",
        "state" => "To be updated", 
        "country" => "To be updated"
        // Removed is_online_store as it's not in the validation check
    );
    
    // Get current server info
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $warehouse_api_url = $protocol . "://" . $host . "/backend/src/api/warehouse/add_warehouse.php";
    
    // Call add_warehouse.php API using cURL
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $warehouse_api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($warehouse_data),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        ),
    ));
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);
    
    if ($curl_error) {
        return array('success' => false, 'error' => 'cURL Error: ' . $curl_error);
    }
    
    $response_data = json_decode($response, true);
    
    if ($http_code === 201 && $response_data && $response_data['status'] === true) {
        return array('success' => true, 'data' => $response_data);
    } else {
        return array(
            'success' => false, 
            'error' => $response_data['message'] ?? 'Unknown error',
            'http_code' => $http_code,
            'full_response' => $response_data
        );
    }
}

// Get posted data
$data = json_decode(file_get_contents("php://input"));

// Check if all required fields are provided
if (
    !empty($data->username) &&
    !empty($data->password) &&
    !empty($data->email) &&
    !empty($data->firstname) &&
    !empty($data->lastname) &&
    !empty($data->address) &&
    !empty($data->role)
    ) {
    
    // Define valid roles
    $valid_roles = array(1, 2, 3, 4, 5);

    // Handle created_by_id properly
    if(empty($data->created_by_id) || !is_numeric($data->created_by_id) || intval($data->created_by_id) < 1) {
        $data->created_by_id = null;
    }
    
    // Define role names for ENUM
    $role_names = array(
        1 => 'admin',
        2 => 'shopkeeper',
        3 => 'worker',
        4 => 'supplier',
        5 => 'normal_user'
    );
    
    // Define role display names
    $role_display_names = array(
        1 => 'Admin',
        2 => 'Shopkeeper',
        3 => 'Worker',
        4 => 'Supplier',
        5 => 'Normal User'
    );
    
    // Validate role
    if (!in_array($data->role, $valid_roles)) {
        http_response_code(400);
        echo json_encode(array(
            "status" => false,
            "message" => "Invalid role. Valid roles are: 1-Admin, 2-Shopkeeper, 3-Worker, 4-Supplier, 5-Normal User"
        ));
        exit();
    }
    
    // Get role name for database
    $user_role = $role_names[$data->role];
    
    // Create database instance
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db != null) {
        try {
            // Create users table
            $create_table_query = "CREATE TABLE IF NOT EXISTS `users` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                firstname VARCHAR(50) NOT NULL,
                lastname VARCHAR(50) NOT NULL,
                address TEXT NOT NULL,
                userRole ENUM('admin', 'shopkeeper', 'worker', 'supplier', 'normal_user') NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NULL DEFAULT NULL
            )";
            
            // Execute table creation query
            $stmt = $db->prepare($create_table_query);
            $stmt->execute();
            
            // Check if username or email already exists
            $check_query = "SELECT id FROM `users` WHERE username = ? OR email = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(1, $data->username);
            $check_stmt->bindParam(2, $data->email);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                http_response_code(409);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Username or email already exists"
                ));
                exit();
            }
            
            // Hash the password
            $hashed_password = password_hash($data->password, PASSWORD_DEFAULT);
            
            // Insert user data
            $insert_query = "INSERT INTO `users` (username, password, email, firstname, lastname, address, userRole, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(1, $data->username);
            $insert_stmt->bindParam(2, $hashed_password);
            $insert_stmt->bindParam(3, $data->email);
            $insert_stmt->bindParam(4, $data->firstname);
            $insert_stmt->bindParam(5, $data->lastname);
            $insert_stmt->bindParam(6, $data->address);
            $insert_stmt->bindParam(7, $user_role);
            $insert_stmt->bindParam(8, $data->created_by_id);
            
            if ($insert_stmt->execute()) {
                $user_id = $db->lastInsertId();
                
                // AUTO-CREATE WAREHOUSE FOR SUPPLIERS using add_warehouse.php API
                $warehouse_result = null;
                if ($user_role === 'supplier') {
                    $warehouse_result = callAddWarehouseAPI($user_id, array(
                        'firstname' => $data->firstname,
                        'address' => $data->address
                    ));
                }
                
                // Get count of users by role
                $count_query = "SELECT COUNT(*) as role_count FROM `users` WHERE userRole = ?";
                $count_stmt = $db->prepare($count_query);
                $count_stmt->bindParam(1, $user_role);
                $count_stmt->execute();
                $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get total users count
                $total_query = "SELECT COUNT(*) as total_users FROM `users`";
                $total_stmt = $db->prepare($total_query);
                $total_stmt->execute();
                $total_result = $total_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Build response data
                $response_data = array(
                    "status" => true,
                    "message" => "User registered successfully",
                    "user_details" => array(
                        "id" => $user_id,
                        "username" => $data->username,
                        "email" => $data->email,
                        "firstname" => $data->firstname,
                        "lastname" => $data->lastname,
                        "userRole" => $user_role,
                        "role_display" => $role_display_names[$data->role]
                    ),
                    "statistics" => array(
                        "total_users" => (int)$total_result['total_users'],
                        "users_with_this_role" => (int)$count_result['role_count']
                    ),
                    "table" => "users"
                );
                
                // Add warehouse information for suppliers
                if ($user_role === 'supplier') {
                    if ($warehouse_result && $warehouse_result['success']) {
                        $response_data["warehouse_auto_created"] = true;
                        $response_data["warehouse_details"] = array(
                            "warehouse_id" => $warehouse_result['data']['warehouse_data']['id'],
                            "warehouse_name" => $warehouse_result['data']['warehouse_data']['warehouse'],
                            "warehouse_address" => $warehouse_result['data']['warehouse_data']['warehouse_address'],
                            "owner_username" => $warehouse_result['data']['warehouse_data']['owner_username'],
                            "owner_role" => $warehouse_result['data']['warehouse_data']['owner_role'],
                            "coordinates" => array(
                                "longitude" => (float)$warehouse_result['data']['warehouse_data']['longitude'],
                                "latitude" => (float)$warehouse_result['data']['warehouse_data']['latitude']
                            ),
                            "ecommerce_enabled" => $warehouse_result['data']['warehouse_data']['ecommerce_enabled'] ?? "No",
                            "status" => "Default warehouse created automatically via add_warehouse.php API",
                            "api_used" => "add_warehouse.php",
                            "note" => "Warehouse table and default entry created successfully"
                        );
                        $response_data["warehouse_statistics"] = $warehouse_result['data']['statistics'];
                        $response_data["message"] = "Supplier registered and default warehouse created successfully";
                    } else {
                        $response_data["warehouse_auto_created"] = false;
                        $response_data["warehouse_error"] = array(
                            "message" => "Default warehouse creation failed",
                            "error_details" => $warehouse_result['error'] ?? 'Unknown error',
                            "http_code" => $warehouse_result['http_code'] ?? 'N/A',
                            "api_used" => "add_warehouse.php",
                            "note" => "User created successfully but warehouse creation failed. Please create warehouse manually.",
                            "manual_creation_endpoint" => "/api/warehouse/add_warehouse.php"
                        );
                        if (isset($warehouse_result['full_response'])) {
                            $response_data["warehouse_error"]["api_response"] = $warehouse_result['full_response'];
                        }
                    }
                }
                
                http_response_code(201);
                echo json_encode($response_data);
                
            } else {
                http_response_code(500);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Failed to register user"
                ));
            }
            
        } catch(PDOException $exception) {
            http_response_code(500);
            echo json_encode(array(
                "status" => false,
                "message" => "Database operation failed",
                "error" => $exception->getMessage()
            ));
        }
        
    } else {
        http_response_code(500);
        echo json_encode(array(
            "status" => false,
            "message" => "Database connection failed"
        ));
    }
    
} else {
    http_response_code(400);
    echo json_encode(array(
        "status" => false,
        "message" => "Incomplete data. Required fields: username, password, email, firstname, lastname, address, role",
        "required_roles" => array(
            "1" => "Admin",
            "2" => "Shopkeeper", 
            "3" => "Worker",
            "4" => "Supplier",
            "5" => "Normal User"
        ),
        "example_request" => array(
            "username" => "john_supplier",
            "password" => "securePassword123",
            "email" => "john@supplier.com",
            "firstname" => "John",
            "lastname" => "Supplier",
            "address" => "123 Main St, City, Country",
            "role" => 4
        ),
        "supplier_note" => "When registering a supplier (role: 4), a default warehouse will be automatically created using add_warehouse.php API"
    ));
}
?>