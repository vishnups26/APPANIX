<?php
// filepath: /home/anix-sam/Desktop/work/stock_management/backend/src/api/category/list_all_categories.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database connection
require_once __DIR__ . '/../../../db/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(array("status" => false, "message" => "Only GET method allowed"));
    exit();
}

// Get createdBy parameter
$createdBy = isset($_GET['createdBy']) ? (int)$_GET['createdBy'] : null;

// Check if required parameter is provided
if (!empty($createdBy) && is_numeric($createdBy) && $createdBy > 0) {
    
    // Create database instance
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db != null) {
        try {
            // Validate that the creator user exists
            $user_check_query = "SELECT id, username, firstname, lastname FROM `users` WHERE id = ?";
            $user_check_stmt = $db->prepare($user_check_query);
            $user_check_stmt->bindParam(1, $createdBy);
            $user_check_stmt->execute();
            
            if ($user_check_stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Creator not found. No user exists with ID: " . $createdBy,
                    "method" => "GET"
                ));
                exit();
            }
                        
            // Get all categories created by this user
            $query = "SELECT c.*, 
                    creator.username as creator_username, 
                    creator.firstname as creator_firstname, 
                    creator.lastname as creator_lastname,
                    modifier.username as modifier_username,
                    modifier.firstname as modifier_firstname,
                    modifier.lastname as modifier_lastname
                    FROM `categories` c 
                    LEFT JOIN `users` creator ON c.createdBy = creator.id 
                    LEFT JOIN `users` modifier ON c.lastModifiedBy = modifier.id
                    WHERE c.createdBy = ?
                    ORDER BY c.created_at DESC";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $createdBy);
            $stmt->execute();
            
            $categories = array();
            $active_count = 0;
            $inactive_count = 0;
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Count active/inactive categories
                if ($row['is_active']) {
                    $active_count++;
                } else {
                    $inactive_count++;
                }
                
                // Build modifier info
                $modifier_info = null;
                if ($row['lastModifiedBy']) {
                    $modifier_info = array(
                        "id" => (int)$row['lastModifiedBy'],
                        "username" => $row['modifier_username'],
                        "firstname" => $row['modifier_firstname'],
                        "lastname" => $row['modifier_lastname'],
                        "fullname" => trim($row['modifier_firstname'] . ' ' . $row['modifier_lastname'])
                    );
                }
                
                $categories[] = array(
                    "id" => (int)$row['id'],
                    "name" => $row['name'],
                    "description" => $row['description'],
                    "display_name" => $row['display_name'],
                    "is_active" => (bool)$row['is_active'],
                    "created_at" => $row['created_at'],
                    "updated_at" => $row['updated_at'],
                    "createdBy" => (int)$row['createdBy'],
                    "lastModifiedBy" => $row['lastModifiedBy'] ? (int)$row['lastModifiedBy'] : null,
                    "modifier_info" => $modifier_info
                );
            }
            
            $total_categories = count($categories);
            
            // Get first and last category dates
            $first_category_date = null;
            $latest_category_date = null;
            
            if ($total_categories > 0) {
                $dates_query = "SELECT 
                                   MIN(created_at) as first_category_date,
                                   MAX(created_at) as latest_category_date
                               FROM `categories` 
                               WHERE createdBy = ?";
                $dates_stmt = $db->prepare($dates_query);
                $dates_stmt->bindParam(1, $createdBy);
                $dates_stmt->execute();
                $dates_result = $dates_stmt->fetch(PDO::FETCH_ASSOC);
                
            }
            
            http_response_code(200);
            echo json_encode(array(
                "status" => true,
                "message" => "Categories retrieved successfully",
                "method" => "GET",
                "categories_data" => $categories,
                "summary" => array(
                    "total_categories" => $total_categories,
                    "active_categories" => $active_count,
                    "inactive_categories" => $inactive_count,
                ),
                "table" => "categories"
            ));
            
        } catch(PDOException $exception) {
            http_response_code(500);
            echo json_encode(array(
                "status" => false,
                "message" => "Database operation failed",
                "error" => $exception->getMessage(),
                "method" => "GET"
            ));
        }
        
    } else {
        http_response_code(500);
        echo json_encode(array(
            "status" => false,
            "message" => "Database connection failed",
            "method" => "GET"
        ));
    }
    
} else {
    http_response_code(400);
    echo json_encode(array(
        "status" => false,
        "message" => "Incomplete data. Required parameter: createdBy",
        "required_parameter" => array(
            "createdBy" => "User ID who created the categories (must be a valid positive integer)"
        ),
        "example_request" => array(
            "url" => "?createdBy=1"
        ),
        "method" => "GET",
        "note" => "createdBy parameter is required and must be greater than 0"
    ));
}
?>