<?php
// filepath: /home/anix-sam/Desktop/work/stock_management/backend/src/api/users/listofusers.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// // Include database connection
require_once __DIR__ . '/../../../db/connect.php';

// // Allow both GET and POST requests
$request_method = $_SERVER['REQUEST_METHOD'];

// Get shopkeeper id parameter from different request methods

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($request_method === 'GET') {
    $shopkeeper_id = isset($_GET['shopkeeper_id']) ? $_GET['shopkeeper_id'] : null;
}
// Create database instance
$database = new Database();
$db = $database->getConnection();

if($db != null) {
    try {
        // Check if shopkeeper_id is provided
        if ($shopkeeper_id != null) {
            // Validate shopkeeper_id
            if (!is_numeric($shopkeeper_id) || !intval($shopkeeper_id) < 0) {
                http_response_code(400);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Invalid shopkeeper_id. It must be a positive integer."
                ));
                exit();
            }
            
            // Query workers by specific shopkeeper_id
            // $query = "SELECT id, username, email, firstname, lastname, address, role, created_at FROM users WHERE role = 'worker' AND shopkeeper_id = ?";
            $query = "SELECT id, username, email, firstname, lastname, address, userRole, created_at 
                    FROM users 
                    WHERE userRole = 'worker' 
                    AND id IN (
                        SELECT user_id 
                        FROM workers_mapping 
                        WHERE shopkeeper_id = ?
                    )
                    ORDER BY created_at DESC";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $shopkeeper_id, PDO::PARAM_INT);

           } else {
          http_response_code(400);
            echo json_encode(array(
                "status" => false,
                "message" => "shopkeeper_id parameter is required"
            ));
            exit();
        }
        
        $stmt->execute();
        $num = $stmt->rowCount();
        
        if ($num > 0) {
            $users_arr = array();
            $users_arr["status"] = true;
            $users_arr["message"] = "Users found";
            $users_arr["data"] = array();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                $user_item = array(
                    "id" => $id,
                    "username" => $username,
                    "email" => $email,
                    "firstname" => $firstname,
                    "lastname" => $lastname,
                    "address" => $address,
                    "role" => $role,
                    "created_at" => $created_at
                );
                array_push($users_arr["data"], $user_item);
            }
            
            http_response_code(200);
            echo json_encode($users_arr);
        } else {
            http_response_code(404);
            echo json_encode(array("status" => false, "message" => "No users found"));
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(array("status" => false, "message" => "Database error: " . $e->getMessage())); 
    }
} else {
    http_response_code(500);
    echo json_encode(array("status" => false, "message" => "Database connection failed"));
}

?>


