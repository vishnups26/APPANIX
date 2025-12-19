<?php
// filepath: /home/anix-sam/Desktop/work/stock_management/backend/src/api/categories/edit_categories.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, PATCH");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database connection
require_once __DIR__ . '/../../../db/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow PUT and PATCH requests
if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    http_response_code(405);
    echo json_encode(array("status" => false, "message" => "Only PUT and PATCH methods allowed"));
    exit();
}

// Get posted data
$data = json_decode(file_get_contents("php://input"));

// Check if required fields are provided
if (
    !empty($data->id) &&
    is_numeric($data->id) &&
    !empty($data->userId) &&
    is_numeric($data->userId) &&
    (
        !empty($data->name) ||
        !empty($data->description) ||
        !empty($data->display_name) ||
        isset($data->is_active)
    )
) {
    
    // Create database instance
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db != null) {
        try {
            // Create/Update categories table with createdBy and lastModifiedBy columns
            $create_table_query = "CREATE TABLE IF NOT EXISTS `categories` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                description TEXT NOT NULL,
                display_name VARCHAR(150) NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                createdBy INT NULL DEFAULT NULL,
                lastModifiedBy INT NULL DEFAULT NULL,
                FOREIGN KEY (createdBy) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (lastModifiedBy) REFERENCES users(id) ON DELETE SET NULL
            )";
            
            $create_stmt = $db->prepare($create_table_query);
            $create_stmt->execute();
            
            // Check if required columns exist, if not add them
            $check_created_column_query = "SHOW COLUMNS FROM `categories` LIKE 'createdBy'";
            $check_created_column_stmt = $db->prepare($check_created_column_query);
            $check_created_column_stmt->execute();
            
            if ($check_created_column_stmt->rowCount() === 0) {
                $add_created_column_query = "ALTER TABLE `categories` ADD COLUMN createdBy INT NULL DEFAULT NULL";
                $add_created_column_stmt = $db->prepare($add_created_column_query);
                $add_created_column_stmt->execute();
            }
            
            $check_modified_column_query = "SHOW COLUMNS FROM `categories` LIKE 'lastModifiedBy'";
            $check_modified_column_stmt = $db->prepare($check_modified_column_query);
            $check_modified_column_stmt->execute();
            
            if ($check_modified_column_stmt->rowCount() === 0) {
                $add_modified_column_query = "ALTER TABLE `categories` ADD COLUMN lastModifiedBy INT NULL DEFAULT NULL";
                $add_modified_column_stmt = $db->prepare($add_modified_column_query);
                $add_modified_column_stmt->execute();
            }
            
            // Add foreign key constraints if users table exists
            $check_users_table = "SHOW TABLES LIKE 'users'";
            $check_users_stmt = $db->prepare($check_users_table);
            $check_users_stmt->execute();
            
            if ($check_users_stmt->rowCount() > 0) {
                try {
                    // Try to add foreign keys (might fail if they already exist)
                    $add_fk_created_query = "ALTER TABLE `categories` ADD FOREIGN KEY (createdBy) REFERENCES users(id) ON DELETE SET NULL";
                    $add_fk_created_stmt = $db->prepare($add_fk_created_query);
                    $add_fk_created_stmt->execute();
                } catch(PDOException $fk_exception) {
                    // Foreign key might already exist
                }
                
                try {
                    $add_fk_modified_query = "ALTER TABLE `categories` ADD FOREIGN KEY (lastModifiedBy) REFERENCES users(id) ON DELETE SET NULL";
                    $add_fk_modified_stmt = $db->prepare($add_fk_modified_query);
                    $add_fk_modified_stmt->execute();
                } catch(PDOException $fk_exception) {
                    // Foreign key might already exist
                }
            }
            
            // Validate that the user exists
            $user_check_query = "SELECT id, username, firstname, lastname FROM `users` WHERE id = ?";
            $user_check_stmt = $db->prepare($user_check_query);
            $user_check_stmt->bindParam(1, $data->userId);
            $user_check_stmt->execute();
            
            if ($user_check_stmt->rowCount() === 0) {
                http_response_code(400);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Invalid userId. User does not exist.",
                    "method" => $_SERVER['REQUEST_METHOD']
                ));
                exit();
            }
            
            $modifier_user_data = $user_check_stmt->fetch(PDO::FETCH_ASSOC);
            
            // First check if category exists
            $check_query = "SELECT * FROM `categories` WHERE id = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(1, $data->id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Category not found",
                    "method" => $_SERVER['REQUEST_METHOD']
                ));
                exit();
            }
            
            $existing_category = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            // If name is being updated, check for duplicates
            if (!empty($data->name) && $data->name !== $existing_category['name']) {
                $duplicate_query = "SELECT id FROM `categories` WHERE name = ? AND id != ?";
                $duplicate_stmt = $db->prepare($duplicate_query);
                $duplicate_stmt->bindParam(1, $data->name);
                $duplicate_stmt->bindParam(2, $data->id);
                $duplicate_stmt->execute();
                
                if ($duplicate_stmt->rowCount() > 0) {
                    http_response_code(409);
                    echo json_encode(array(
                        "status" => false,
                        "message" => "Category name already exists",
                        "method" => $_SERVER['REQUEST_METHOD']
                    ));
                    exit();
                }
            }
            
            // Build dynamic update query based on provided fields
            $update_fields = array();
            $params = array();
            
            if (!empty($data->name)) {
                $update_fields[] = "name = ?";
                $params[] = $data->name;
            }
            
            if (!empty($data->description)) {
                $update_fields[] = "description = ?";
                $params[] = $data->description;
            }
            
            if (!empty($data->display_name)) {
                $update_fields[] = "display_name = ?";
                $params[] = $data->display_name;
            }
            
            if (isset($data->is_active)) {
                $update_fields[] = "is_active = ?";
                $params[] = (bool)$data->is_active;
            }
            
            // Always update lastModifiedBy and updated_at
            $update_fields[] = "lastModifiedBy = ?";
            $params[] = $data->userId;
            $update_fields[] = "updated_at = CURRENT_TIMESTAMP";
            
            // Add ID for WHERE clause
            $params[] = $data->id;
            
            // Build and execute update query
            $update_query = "UPDATE `categories` SET " . implode(", ", $update_fields) . " WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            
            // Bind parameters
            for ($i = 0; $i < count($params); $i++) {
                $update_stmt->bindParam($i + 1, $params[$i]);
            }
            
            if ($update_stmt->execute()) {
                // Get updated category data with creator and modifier info
                $get_updated_query = "SELECT c.*, 
                                            creator.username as creator_username, 
                                            creator.firstname as creator_firstname, 
                                            creator.lastname as creator_lastname,
                                            modifier.username as modifier_username,
                                            modifier.firstname as modifier_firstname,
                                            modifier.lastname as modifier_lastname
                                     FROM `categories` c 
                                     LEFT JOIN `users` creator ON c.createdBy = creator.id 
                                     LEFT JOIN `users` modifier ON c.lastModifiedBy = modifier.id
                                     WHERE c.id = ?";
                $get_updated_stmt = $db->prepare($get_updated_query);
                $get_updated_stmt->bindParam(1, $data->id);
                $get_updated_stmt->execute();
                $updated_category = $get_updated_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Build creator info
                $creator_info = null;
                if ($updated_category['createdBy']) {
                    $creator_info = array(
                        "id" => (int)$updated_category['createdBy'],
                        "username" => $updated_category['creator_username'],
                        "firstname" => $updated_category['creator_firstname'],
                        "lastname" => $updated_category['creator_lastname'],
                        "fullname" => trim($updated_category['creator_firstname'] . ' ' . $updated_category['creator_lastname'])
                    );
                }
                
                // Build modifier info
                $modifier_info = null;
                if ($updated_category['lastModifiedBy']) {
                    $modifier_info = array(
                        "id" => (int)$updated_category['lastModifiedBy'],
                        "username" => $updated_category['modifier_username'],
                        "firstname" => $updated_category['modifier_firstname'],
                        "lastname" => $updated_category['modifier_lastname'],
                        "fullname" => trim($updated_category['modifier_firstname'] . ' ' . $updated_category['modifier_lastname'])
                    );
                }
                
                http_response_code(200);
                echo json_encode(array(
                    "status" => true,
                    "message" => "Category updated successfully",
                    "method" => $_SERVER['REQUEST_METHOD'],
                    "category_data" => array(
                        "id" => (int)$updated_category['id'],
                        "name" => $updated_category['name'],
                        "description" => $updated_category['description'],
                        "display_name" => $updated_category['display_name'],
                        "is_active" => (bool)$updated_category['is_active'],
                        "created_at" => $updated_category['created_at'],
                        "updated_at" => $updated_category['updated_at'],
                        "createdBy" => $updated_category['createdBy'] ? (int)$updated_category['createdBy'] : null,
                        "lastModifiedBy" => $updated_category['lastModifiedBy'] ? (int)$updated_category['lastModifiedBy'] : null,
                        "creator_info" => $creator_info,
                        "modifier_info" => $modifier_info
                    ),
                    "changes_made" => array(
                        "name" => !empty($data->name) ? "Updated" : "Unchanged",
                        "description" => !empty($data->description) ? "Updated" : "Unchanged",
                        "display_name" => !empty($data->display_name) ? "Updated" : "Unchanged",
                        "is_active" => isset($data->is_active) ? "Updated" : "Unchanged"
                    ),
                    "table" => "categories"
                ));
                
            } else {
                http_response_code(500);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Failed to update category",
                    "method" => $_SERVER['REQUEST_METHOD']
                ));
            }
            
        } catch(PDOException $exception) {
            http_response_code(500);
            echo json_encode(array(
                "status" => false,
                "message" => "Database operation failed",
                "error" => $exception->getMessage(),
                "method" => $_SERVER['REQUEST_METHOD']
            ));
        }
        
    } else {
        http_response_code(500);
        echo json_encode(array(
            "status" => false,
            "message" => "Database connection failed",
            "method" => $_SERVER['REQUEST_METHOD']
        ));
    }
    
} else {
    http_response_code(400);
    echo json_encode(array(
        "status" => false,
        "message" => "Invalid data. Required: id, userId and at least one field to update",
        "required_fields" => array(
            "id" => "Category ID (required)",
            "userId" => "User ID who is making the edit (required)",
            "name" => "Category name (optional)",
            "description" => "Category description (optional)",
            "display_name" => "Display name (optional)",
            "is_active" => "Active status true/false (optional)"
        ),
        "example_requests" => array(
            "update_all_fields" => array(
                "id" => 1,
                "userId" => 2,
                "name" => "electronics_updated",
                "description" => "Updated description for electronics",
                "display_name" => "Electronics & Tech",
                "is_active" => true
            ),
            "update_single_field" => array(
                "id" => 1,
                "userId" => 2,
                "display_name" => "Electronics & Technology"
            ),
            "deactivate_category" => array(
                "id" => 1,
                "userId" => 2,
                "is_active" => false
            )
        ),
        "method" => $_SERVER['REQUEST_METHOD'],
        "supported_methods" => ["PUT", "PATCH"],
        "note" => "You can update one or multiple fields in a single request. The userId tracks who made the edit."
    ));
}
?>