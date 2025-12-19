<?php
// filepath: /home/anix-sam/Desktop/work/stock_management/backend/src/api/categories/delete_categories.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database connection
require_once __DIR__ . '/../../../db/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow DELETE requests
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(array("status" => false, "message" => "Only DELETE method allowed"));
    exit();
}

// Get posted data
$data = json_decode(file_get_contents("php://input"));

// Check if required fields are provided
if (
    !empty($data->id) &&
    is_numeric($data->id) &&
    !empty($data->userId) &&
    is_numeric($data->userId)
) {
    
    // Create database instance
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db != null) {
        try {
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
                    "method" => "DELETE"
                ));
                exit();
            }
            
            $deleter_user_data = $user_check_stmt->fetch(PDO::FETCH_ASSOC);
            
            // First check if category exists and get its details
            $check_query = "SELECT c.*, 
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
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(1, $data->id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Category not found",
                    "method" => "DELETE"
                ));
                exit();
            }
            
            $category_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Build creator info for response
            $creator_info = null;
            if ($category_data['createdBy']) {
                $creator_info = array(
                    "id" => (int)$category_data['createdBy'],
                    "username" => $category_data['creator_username'],
                    "firstname" => $category_data['creator_firstname'],
                    "lastname" => $category_data['creator_lastname'],
                    "fullname" => trim($category_data['creator_firstname'] . ' ' . $category_data['creator_lastname'])
                );
            }
            
            // Build modifier info for response
            $modifier_info = null;
            if ($category_data['lastModifiedBy']) {
                $modifier_info = array(
                    "id" => (int)$category_data['lastModifiedBy'],
                    "username" => $category_data['modifier_username'],
                    "firstname" => $category_data['modifier_firstname'],
                    "lastname" => $category_data['modifier_lastname'],
                    "fullname" => trim($category_data['modifier_firstname'] . ' ' . $category_data['modifier_lastname'])
                );
            }
            
            // Build deleter info
            $deleter_info = array(
                "id" => (int)$deleter_user_data['id'],
                "username" => $deleter_user_data['username'],
                "firstname" => $deleter_user_data['firstname'],
                "lastname" => $deleter_user_data['lastname'],
                "fullname" => trim($deleter_user_data['firstname'] . ' ' . $deleter_user_data['lastname'])
            );
            
            // Check if there are any dependencies (you can add more checks here)
            // For example, check if any products use this category
            /*
            $dependency_check_query = "SELECT COUNT(*) as product_count FROM `products` WHERE category_id = ?";
            $dependency_check_stmt = $db->prepare($dependency_check_query);
            $dependency_check_stmt->bindParam(1, $data->id);
            $dependency_check_stmt->execute();
            $dependency_result = $dependency_check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($dependency_result['product_count'] > 0) {
                http_response_code(409);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Cannot delete category. It is being used by " . $dependency_result['product_count'] . " product(s).",
                    "method" => "DELETE",
                    "dependencies" => array(
                        "products" => (int)$dependency_result['product_count']
                    )
                ));
                exit();
            }
            */
            
            // Perform the deletion
            $delete_query = "DELETE FROM `categories` WHERE id = ?";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(1, $data->id);
            
            if ($delete_stmt->execute()) {
                http_response_code(200);
                echo json_encode(array(
                    "status" => true,
                    "message" => "Category deleted successfully",
                    "method" => "DELETE",
                    "deleted_category" => array(
                        "id" => (int)$category_data['id'],
                        "name" => $category_data['name'],
                        "description" => $category_data['description'],
                        "display_name" => $category_data['display_name'],
                        "was_active" => (bool)$category_data['is_active'],
                        "created_at" => $category_data['created_at'],
                        "updated_at" => $category_data['updated_at'],
                        "createdBy" => $category_data['createdBy'] ? (int)$category_data['createdBy'] : null,
                        "lastModifiedBy" => $category_data['lastModifiedBy'] ? (int)$category_data['lastModifiedBy'] : null,
                        "creator_info" => $creator_info,
                        "modifier_info" => $modifier_info
                    ),
                    "deletion_info" => array(
                        "deleted_by" => $deleter_info,
                        "deleted_at" => date('Y-m-d H:i:s'),
                        "deletion_timestamp" => time()
                    ),
                    "table" => "categories"
                ));
                
            } else {
                http_response_code(500);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Failed to delete category",
                    "method" => "DELETE"
                ));
            }
            
        } catch(PDOException $exception) {
            http_response_code(500);
            echo json_encode(array(
                "status" => false,
                "message" => "Database operation failed",
                "error" => $exception->getMessage(),
                "method" => "DELETE"
            ));
        }
        
    } else {
        http_response_code(500);
        echo json_encode(array(
            "status" => false,
            "message" => "Database connection failed",
            "method" => "DELETE"
        ));
    }
    
} else {
    http_response_code(400);
    echo json_encode(array(
        "status" => false,
        "message" => "Invalid data. Required fields: id, userId",
        "required_fields" => array(
            "id" => "Category ID to delete (required)",
            "userId" => "User ID who is performing the deletion (required)"
        ),
        "example_request" => array(
            "id" => 1,
            "userId" => 2
        ),
        "method" => "DELETE",
        "note" => "This will permanently delete the category. Make sure no products are using this category."
    ));
}
?>