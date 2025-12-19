<?php
// filepath: /home/anix-sam/Desktop/work/stock_management/backend/src/api/store/delete_store.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database connection
require_once __DIR__ . '/../../../db/connect.php';

// Only allow DELETE requests
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(array("status" => false, "message" => "Only DELETE method allowed"));
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

// ============================
// DELETE STORE (DELETE METHOD)
// ============================
function deleteStore($db) {
    // Get store_id and userId from URL parameter or request body
    $store_id = isset($_GET['store_id']) ? $_GET['store_id'] : null;
    $userId = isset($_GET['userId']) ? $_GET['userId'] : null;
    
    // If not in URL, check request body
    if ($store_id === null || $userId === null) {
        $data = json_decode(file_get_contents("php://input"));
        $store_id = isset($data->store_id) ? $data->store_id : $store_id;
        $userId = isset($data->userId) ? $data->userId : $userId;
    }
    
    if ($store_id !== null && $userId !== null) {
        
        // Validate store_id and userId are numeric
        if (!is_numeric($store_id) || !is_numeric($userId)) {
            http_response_code(400);
            echo json_encode(array(
                "status" => false,
                "message" => "Store ID and User ID must be numeric",
                "method" => "DELETE"
            ));
            return;
        }
        
        try {
            
            // Check if store exists and get its data with owner info
            $check_query = "SELECT s.*, u.username, u.userRole 
                           FROM `stores` s 
                           JOIN `users` u ON s.userId = u.id 
                           WHERE s.store_id = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(1, $store_id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() == 0) {
                http_response_code(404);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Store not found",
                    "method" => "DELETE"
                ));
                return;
            }
            
            $store_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify ownership - only store owner can delete
            if ($store_data['userId'] != $userId) {
                http_response_code(403);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Access denied. You can only delete your own stores.",
                    "method" => "DELETE",
                    "store_owner" => array(
                        "userId" => $store_data['userId'],
                        "username" => $store_data['username']
                    ),
                    "attempted_by_userId" => $userId
                ));
                return;
            }
            
            // Delete the store (ownership verified)
            $delete_query = "DELETE FROM `stores` WHERE store_id = ? AND userId = ?";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(1, $store_id);
            $delete_stmt->bindParam(2, $userId);
            
            if ($delete_stmt->execute()) {
                
                // Get remaining stores count for user
                $user_count_query = "SELECT COUNT(*) as user_remaining_stores FROM `stores` WHERE userId = ?";
                $user_count_stmt = $db->prepare($user_count_query);
                $user_count_stmt->bindParam(1, $userId);
                $user_count_stmt->execute();
                $user_count_result = $user_count_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get total remaining stores count
                $total_count_query = "SELECT COUNT(*) as total_remaining_stores FROM `stores`";
                $total_count_stmt = $db->prepare($total_count_query);
                $total_count_stmt->execute();
                $total_count_result = $total_count_stmt->fetch(PDO::FETCH_ASSOC);
                
                http_response_code(200);
                echo json_encode(array(
                    "status" => true,
                    "message" => "Store deleted successfully",
                    "method" => "DELETE",
                    "ownership_verified" => true,
                    "deleted_store" => array(
                        "store_id" => $store_data['store_id'],
                        "storename" => $store_data['storename'],
                        "store_address" => $store_data['store_address'],
                        "was_online" => (bool)$store_data['is_online'],
                        "owner" => array(
                            "userId" => $store_data['userId'],
                            "username" => $store_data['username'],
                            "userRole" => $store_data['userRole']
                        )
                    ),
                    "statistics" => array(
                        "user_remaining_stores" => $user_count_result['user_remaining_stores'],
                        "total_remaining_stores" => $total_count_result['total_remaining_stores']
                    )
                ));
                
            } else {
                http_response_code(500);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Failed to delete store",
                    "method" => "DELETE"
                ));
            }
            
        } catch(PDOException $exception) {
            http_response_code(500);
            echo json_encode(array(
                "status" => false,
                "message" => "Database operation failed",
                "method" => "DELETE",
                "error" => $exception->getMessage()
            ));
        }
        
    } else {
        http_response_code(400);
        echo json_encode(array(
            "status" => false,
            "message" => "Store ID and User ID are required for ownership verification",
            "method" => "DELETE",
            "usage" => array(
                "url_parameters" => "delete_store.php?store_id=1&userId=1",
                "request_body" => array(
                    "store_id" => 1,
                    "userId" => 1
                )
            )
        ));
    }
}
?>