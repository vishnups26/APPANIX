<?php
// filepath: /home/anix-sam/Desktop/work/stock_management/backend/src/api/store/edit_store.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database connection
require_once __DIR__ . '/../../../db/connect.php';

// Only allow PUT requests
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(array("status" => false, "message" => "Only PUT method allowed"));
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

// ==========================
// UPDATE STORE (PUT METHOD)
// ==========================
function updateStore($db) {
    // Get posted data
    $data = json_decode(file_get_contents("php://input"));
    
    // Check if store_id, userId and required fields are provided
    if (
        !empty($data->store_id) &&
        !empty($data->userId) &&
        !empty($data->storename) &&
        !empty($data->store_address) &&
        isset($data->longitude) &&
        isset($data->latitude) &&
        isset($data->is_online)
    ) {
        
        // Validate store_id and userId are numeric
        if (!is_numeric($data->store_id) || !is_numeric($data->userId)) {
            http_response_code(400);
            echo json_encode(array(
                "status" => false,
                "message" => "Store ID and User ID must be numeric",
                "method" => "PUT"
            ));
            return;
        }
        
        // Validate coordinates
        if (!is_numeric($data->longitude) || !is_numeric($data->latitude)) {
            http_response_code(400);
            echo json_encode(array(
                "status" => false,
                "message" => "Longitude and latitude must be numeric values",
                "method" => "PUT"
            ));
            return;
        }
        
        // Validate is_online
        if (!is_bool($data->is_online)) {
            http_response_code(400);
            echo json_encode(array(
                "status" => false,
                "message" => "is_online must be boolean (true or false)",
                "method" => "PUT"
            ));
            return;
        }
        
        try {
            
            // Check if store exists and verify ownership
            $check_query = "SELECT s.*, u.username, u.userRole 
                           FROM `stores` s 
                           JOIN `users` u ON s.userId = u.id 
                           WHERE s.store_id = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(1, $data->store_id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() == 0) {
                http_response_code(404);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Store not found",
                    "method" => "PUT"
                ));
                return;
            }
            
            $old_store = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify ownership - only store owner can edit
            if ($old_store['userId'] != $data->userId) {
                http_response_code(403);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Access denied. You can only edit your own stores.",
                    "method" => "PUT",
                    "store_owner" => $old_store['username'],
                    "attempted_by_userId" => $data->userId
                ));
                return;
            }
            
            // Check if new store name conflicts with user's existing stores (excluding current store)
            $name_check_query = "SELECT store_id FROM `stores` WHERE storename = ? AND userId = ? AND store_id != ?";
            $name_check_stmt = $db->prepare($name_check_query);
            $name_check_stmt->bindParam(1, $data->storename);
            $name_check_stmt->bindParam(2, $data->userId);
            $name_check_stmt->bindParam(3, $data->store_id);
            $name_check_stmt->execute();
            
            if ($name_check_stmt->rowCount() > 0) {
                http_response_code(409);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Store name already exists for this user",
                    "method" => "PUT"
                ));
                return;
            }
            
            // Update store data
            $update_query = "UPDATE `stores` SET 
                storename = ?, 
                store_address = ?, 
                longitude = ?, 
                latitude = ?, 
                is_online = ?,
                updated_at = CURRENT_TIMESTAMP 
                WHERE store_id = ? AND userId = ?";
            
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(1, $data->storename);
            $update_stmt->bindParam(2, $data->store_address);
            $update_stmt->bindParam(3, $data->longitude);
            $update_stmt->bindParam(4, $data->latitude);
            $update_stmt->bindParam(5, $data->is_online, PDO::PARAM_BOOL);
            $update_stmt->bindParam(6, $data->store_id);
            $update_stmt->bindParam(7, $data->userId);
            
            if ($update_stmt->execute()) {
                
                // Get updated store data
                $updated_query = "SELECT s.*, u.username, u.userRole 
                                 FROM `stores` s 
                                 JOIN `users` u ON s.userId = u.id 
                                 WHERE s.store_id = ?";
                $updated_stmt = $db->prepare($updated_query);
                $updated_stmt->bindParam(1, $data->store_id);
                $updated_stmt->execute();
                $updated_store = $updated_stmt->fetch(PDO::FETCH_ASSOC);
                
                http_response_code(200);
                echo json_encode(array(
                    "status" => true,
                    "message" => "Store updated successfully",
                    "method" => "PUT",
                    "ownership_verified" => true,
                    "old_data" => array(
                        "storename" => $old_store['storename'],
                        "store_address" => $old_store['store_address'],
                        "longitude" => $old_store['longitude'],
                        "latitude" => $old_store['latitude'],
                        "is_online" => (bool)$old_store['is_online']
                    ),
                    "updated_data" => array(
                        "store_id" => $updated_store['store_id'],
                        "userId" => $updated_store['userId'],
                        "owner_username" => $updated_store['username'],
                        "storename" => $updated_store['storename'],
                        "store_address" => $updated_store['store_address'],
                        "longitude" => $updated_store['longitude'],
                        "latitude" => $updated_store['latitude'],
                        "is_online" => (bool)$updated_store['is_online'],
                        "ecommerce_enabled" => $updated_store['is_online'] ? "Yes" : "No",
                        "updated_at" => $updated_store['updated_at']
                    )
                ));
                
            } else {
                http_response_code(500);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Failed to update store",
                    "method" => "PUT"
                ));
            }
            
        } catch(PDOException $exception) {
            http_response_code(500);
            echo json_encode(array(
                "status" => false,
                "message" => "Database operation failed",
                "method" => "PUT",
                "error" => $exception->getMessage()
            ));
        }
        
    } else {
        http_response_code(400);
        echo json_encode(array(
            "status" => false,
            "message" => "Incomplete data. Required fields: store_id, userId, storename, store_address, longitude, latitude, is_online",
            "method" => "PUT",
            "example_request" => array(
                "store_id" => 1,
                "userId" => 1,
                "storename" => "Updated Tech Store",
                "store_address" => "456 Updated Street, City, Country",
                "longitude" => -122.4194,
                "latitude" => 37.7749,
                "is_online" => false
            )
        ));
    }
}
?>