<?php
// filepath: /home/anix-sam/Desktop/work/stock_management/backend/src/api/store/get_store.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database connection
require_once __DIR__ . '/../../../db/connect.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(array("status" => false, "message" => "Only GET method allowed"));
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

// ========================
// GET STORE (GET METHOD)
// ========================
function getStore($db) {
    // Get parameters
    $store_id = isset($_GET['store_id']) ? $_GET['store_id'] : null;
    $userId = isset($_GET['userId']) ? $_GET['userId'] : null;
    
    try {
        
        if ($store_id !== null) {
            
            // Validate store_id is numeric
            if (!is_numeric($store_id)) {
                http_response_code(400);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Store ID must be numeric",
                    "method" => "GET"
                ));
                return;
            }
            
            // Get specific store by ID (with owner info)
            $store_query = "SELECT s.*, u.username, u.userRole 
                           FROM `stores` s 
                           JOIN `users` u ON s.userId = u.id 
                           WHERE s.store_id = ?";
            $store_stmt = $db->prepare($store_query);
            $store_stmt->bindParam(1, $store_id);
            $store_stmt->execute();
            
            if ($store_stmt->rowCount() == 1) {
                $store = $store_stmt->fetch(PDO::FETCH_ASSOC);
                
                http_response_code(200);
                echo json_encode(array(
                    "status" => true,
                    "message" => "Store details retrieved successfully",
                    "method" => "GET",
                    "store_data" => array(
                        "store_id" => $store['store_id'],
                        "userId" => $store['userId'],
                        "owner_username" => $store['username'],
                        "owner_role" => $store['userRole'],
                        "storename" => $store['storename'],
                        "store_address" => $store['store_address'],
                        "longitude" => $store['longitude'],
                        "latitude" => $store['latitude'],
                        "is_online" => (bool)$store['is_online'],
                        "ecommerce_enabled" => $store['is_online'] ? "Yes" : "No",
                        "created_at" => $store['created_at'],
                        "updated_at" => $store['updated_at']
                    )
                ));
                
            } else {
                http_response_code(404);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Store not found",
                    "method" => "GET"
                ));
            }
            
        } elseif ($userId !== null) {          
            // Validate userId is numeric
            if (!is_numeric($userId)) {
                http_response_code(400);
                echo json_encode(array(
                    "status" => false,
                    "message" => "User ID must be numeric",
                    "method" => "GET"
                ));
                return;
            }
            
            // Check if user exists
            $user_check_query = "SELECT username, userRole FROM `users` WHERE id = ?";
            $user_check_stmt = $db->prepare($user_check_query);
            $user_check_stmt->bindParam(1, $userId);
            $user_check_stmt->execute();
            
            if ($user_check_stmt->rowCount() == 0) {
                http_response_code(404);
                echo json_encode(array(
                    "status" => false,
                    "message" => "User not found",
                    "method" => "GET"
                ));
                return;
            }
            
            $user_data = $user_check_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get stores for specific user
            $user_stores_query = "SELECT s.*, u.username, u.userRole 
                                 FROM `stores` s 
                                 JOIN `users` u ON s.userId = u.id 
                                 WHERE s.userId = ? 
                                 ORDER BY s.created_at DESC";
            $user_stores_stmt = $db->prepare($user_stores_query);
            $user_stores_stmt->bindParam(1, $userId);
            $user_stores_stmt->execute();
            
            $stores = $user_stores_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert is_online to boolean and add ecommerce status
            foreach ($stores as &$store) {
                $store['is_online'] = (bool)$store['is_online'];
                $store['ecommerce_enabled'] = $store['is_online'] ? "Yes" : "No";
            }
            
            // Get user statistics
            $user_stats_query = "SELECT 
                COUNT(*) as user_total_stores,
                COUNT(CASE WHEN is_online = TRUE THEN 1 END) as user_online_stores,
                COUNT(CASE WHEN is_online = FALSE THEN 1 END) as user_offline_stores
                FROM `stores` WHERE userId = ?";
            $user_stats_stmt = $db->prepare($user_stats_query);
            $user_stats_stmt->bindParam(1, $userId);
            $user_stats_stmt->execute();
            $user_stats = $user_stats_stmt->fetch(PDO::FETCH_ASSOC);
            
            http_response_code(200);
            echo json_encode(array(
                "status" => true,
                "message" => "User stores retrieved successfully",
                "method" => "GET",
                "user_info" => array(
                    "userId" => $userId,
                    "username" => $user_data['username'],
                    "userRole" => $user_data['userRole']
                ),
                "statistics" => $user_stats,
                "stores" => $stores
            ));
            
        } else {
            
            // Get all stores (with owner info)
            $all_stores_query = "SELECT s.*, u.username, u.userRole 
                                FROM `stores` s 
                                JOIN `users` u ON s.userId = u.id 
                                ORDER BY s.created_at DESC";
            $all_stores_stmt = $db->prepare($all_stores_query);
            $all_stores_stmt->execute();
            
            $stores = $all_stores_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert is_online to boolean and add ecommerce status
            foreach ($stores as &$store) {
                $store['is_online'] = (bool)$store['is_online'];
                $store['ecommerce_enabled'] = $store['is_online'] ? "Yes" : "No";
            }
            
            // Get overall statistics
            $stats_query = "SELECT 
                COUNT(*) as total_stores,
                COUNT(CASE WHEN is_online = TRUE THEN 1 END) as online_stores,
                COUNT(CASE WHEN is_online = FALSE THEN 1 END) as offline_stores,
                COUNT(DISTINCT userId) as total_store_owners
                FROM `stores`";
            $stats_stmt = $db->prepare($stats_query);
            $stats_stmt->execute();
            $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
            
            http_response_code(200);
            echo json_encode(array(
                "status" => true,
                "message" => "All stores retrieved successfully user Id",
                "method" => "GET",
                "statistics" => $stats,
                "stores" => $stores
            ));
        }
        
    } catch(PDOException $exception) {
        http_response_code(500);
        echo json_encode(array(
            "status" => false,
            "message" => "Database operation failed",
            "method" => "GET",
            "error" => $exception->getMessage()
        ));
    }
}
?>