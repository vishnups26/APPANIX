<?php
// filepath: /home/anix-sam/Desktop/work/stock_management/backend/src/api/categories/add_categories.php
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

// Get posted data
$data = json_decode(file_get_contents("php://input"));

// Check if all required fields are provided
if (
    !empty($data->name) &&
    !empty($data->description) &&
    !empty($data->display_name) &&
    !empty($data->createdBy) &&
    is_numeric($data->createdBy)
) {
    
    // Create database instance
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db != null) {
        try {
            // Create categories table with createdBy column
            $create_table_query = "CREATE TABLE IF NOT EXISTS `categories` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                description TEXT NOT NULL,
                display_name VARCHAR(150) NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                createdBy INT NULL DEFAULT NULL,
                FOREIGN KEY (createdBy) REFERENCES users(id) ON DELETE SET NULL
            )";
            
            $create_stmt = $db->prepare($create_table_query);
            $create_stmt->execute();
            
            // Check if createdBy column exists, if not add it (for existing tables)
            $check_column_query = "SHOW COLUMNS FROM `categories` LIKE 'createdBy'";
            $check_column_stmt = $db->prepare($check_column_query);
            $check_column_stmt->execute();
            
            if ($check_column_stmt->rowCount() === 0) {
                // Add createdBy column if it doesn't exist
                $add_column_query = "ALTER TABLE `categories` ADD COLUMN createdBy INT NULL DEFAULT NULL";
                $add_column_stmt = $db->prepare($add_column_query);
                $add_column_stmt->execute();
                
                // Add foreign key constraint if users table exists
                $check_users_table = "SHOW TABLES LIKE 'users'";
                $check_users_stmt = $db->prepare($check_users_table);
                $check_users_stmt->execute();
                
                if ($check_users_stmt->rowCount() > 0) {
                    try {
                        $add_fk_query = "ALTER TABLE `categories` ADD FOREIGN KEY (createdBy) REFERENCES users(id) ON DELETE SET NULL";
                        $add_fk_stmt = $db->prepare($add_fk_query);
                        $add_fk_stmt->execute();
                    } catch(PDOException $fk_exception) {
                        // Foreign key constraint might fail - continue without it
                    }
                }
            }
            
            // Validate that the user exists
            $user_check_query = "SELECT id, username, firstname, lastname FROM `users` WHERE id = ?";
            $user_check_stmt = $db->prepare($user_check_query);
            $user_check_stmt->bindParam(1, $data->createdBy);
            $user_check_stmt->execute();
            
            if ($user_check_stmt->rowCount() === 0) {
                http_response_code(400);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Invalid createdBy user ID. User does not exist.",
                    "method" => "POST"
                ));
                exit();
            }
            
            $user_data = $user_check_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if category name already exists
            $check_query = "SELECT id FROM `categories` WHERE name = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(1, $data->name);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                http_response_code(409);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Category name already exists",
                    "method" => "POST"
                ));
                exit();
            }
            
            // Insert category data with createdBy
            $insert_query = "INSERT INTO `categories` (name, description, display_name, createdBy) VALUES (?, ?, ?, ?)";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(1, $data->name);
            $insert_stmt->bindParam(2, $data->description);
            $insert_stmt->bindParam(3, $data->display_name);
            $insert_stmt->bindParam(4, $data->createdBy);
            
            if ($insert_stmt->execute()) {
                $category_id = $db->lastInsertId();
                
                http_response_code(201);
                echo json_encode(array(
                    "status" => true,
                    "message" => "Category created successfully",
                    "method" => "POST",
                    "category_data" => array(
                        "id" => $category_id,
                        "name" => $data->name,
                        "description" => $data->description,
                        "display_name" => $data->display_name,
                        "is_active" => true,
                        "created_at" => date('Y-m-d H:i:s'),
                        "createdBy" => (int)$data->createdBy,
                        "creator_info" => array(
                            "id" => (int)$user_data['id'],
                            "username" => $user_data['username'],
                            "firstname" => $user_data['firstname'],
                            "lastname" => $user_data['lastname'],
                            "fullname" => trim($user_data['firstname'] . ' ' . $user_data['lastname'])
                        )
                    ),
                    "table" => "categories"
                ));
                
            } else {
                http_response_code(500);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Failed to create category",
                    "method" => "POST"
                ));
            }
            
        } catch(PDOException $exception) {
            http_response_code(500);
            echo json_encode(array(
                "status" => false,
                "message" => "Database operation failed",
                "error" => $exception->getMessage(),
                "method" => "POST"
            ));
        }
        
    } else {
        http_response_code(500);
        echo json_encode(array(
            "status" => false,
            "message" => "Database connection failed",
            "method" => "POST"
        ));
    }
    
} else {
    http_response_code(400);
    echo json_encode(array(
        "status" => false,
        "message" => "Incomplete data. Required fields: name, description, display_name, createdBy",
        "required_fields" => array(
            "name" => "Category name (unique identifier)",
            "description" => "Category description",
            "display_name" => "Display name for the category",
            "createdBy" => "User ID of the creator (must be a valid user)"
        ),
        "example_request" => array(
            "name" => "electronics",
            "description" => "All electronic devices, gadgets, and accessories",
            "display_name" => "Electronics & Gadgets",
            "createdBy" => 1
        ),
        "method" => "POST",
        "note" => "Category name must be unique and createdBy must be a valid user ID"
    ));
}
?>