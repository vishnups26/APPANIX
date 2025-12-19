<?php
// filepath: /home/anix-sam/Desktop/work/stock_management/backend/src/api/s/lubscription/list_subscription.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database connection
require_once __DIR__ . '/../../../db/connect.php';

// Allow both GET and POST requests
$request_method = $_SERVER['REQUEST_METHOD'];

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get role parameter from different request methods
if ($request_method === 'POST') {
} else {
    http_response_code(405);
    echo json_encode(array("status" => false, "message" => "Only POST method
    allowed"));
    exit();
}

// Create database instance
$database = new Database();
$db = $database->getConnection();

if ($db != null) {
    try {
        // Create subscriptions table if it doesn't exist
        $createTableQuery = "CREATE TABLE IF NOT EXISTS `subscriptions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL FOREIGN KEY REFERENCES users(id),
            `admin_id` INT NOT NULL FOREIGN KEY REFERENCES users(id),
            `subscription_type` ENUM('free', 'basic', 'premium') NOT NULL,
            `subscription_amount` DECIMAL(10,2) NOT NULL,
            `start_date` DATETIME NOT NULL,
            `end_date` DATETIME NOT NULL,
            `renewed_date` DATETIME,
            `is_active` BOOLEAN DEFAULT TRUE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;";
        $db->exec($createTableQuery);

        // Getting headers for authentication

        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : (
            isset($headers['authorization']) ? $headers['authorization'] : null
        );

        if ($authHeader === null) {
            http_response_code(401);
            echo json_encode(array("status" => false, "message" => "Authorization header missing"));
            exit();
        }

        // Session id is sent in the Authorization header

        $session_id = trim(str_replace("Session", "", $authHeader));

        // Validate session
        $session_query = "SELECT user_id, username, userRole, is_active FROM `user_sessions` WHERE session_id = ?";
        $session_stmt = $db->prepare($session_query);
        $session_stmt->bindParam(1, $session_id);
        $session_stmt->execute();

        if ($session_stmt->rowCount() == 0) {
            http_response_code(401);
            echo json_encode(array("status" => false, "message" => "Invalid session. Please log in again."));
            exit();
        } else {
            $session_data = $session_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$session_data['is_active']) {
                http_response_code(403);
                echo json_encode(array("status" => false, "message" => "Session is inactive. Please log in again."));
                exit();
            }

            // Check if user is admin
            if ($session_data['userRole'] !== 'admin') {
                http_response_code(403);
                echo json_encode(array("status" => false, "message" => "Access denied. Admins only."));
                exit();
            } 

            // Check if admin_id in query matches session user_id

            $user_id = $session_data['user_id'];

            // Fetch subscriptions created by this admin

            $subscriptions_query = "SELECT s.id, s.user_id, u.username AS user_name, s.admin_id, a.username AS admin_name, s.subscription_type, s.subscription_amount, s.start_date, s.end_date, s.renewed_date, s.is_active, s.created_at, s.updated_at
                                    FROM `subscriptions` s
                                    JOIN `users` u ON s.user_id = u.id
                                    JOIN `users` a ON s.admin_id = a.id
                                    WHERE s.admin_id = ?
                                    ORDER BY s.created_at DESC";

            $subscriptions_stmt = $db->prepare($subscriptions_query);
            $subscriptions_stmt->bindParam(1, $user_id);
            $subscriptions_stmt->execute();

            $subscriptions = $subscriptions_stmt->fetchAll(PDO::FETCH_ASSOC);

            http_response_code(200);
            echo json_encode(array(
                "status" => true,
                "message" => "Subscriptions fetched successfully",
                "data" => $subscriptions,
                "total" => count($subscriptions)
            ));
        }



        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("status" => false, "message" => "An error occurred: " . $e->getMessage()));
        exit();
    }

} else {
    http_response_code(500);
    echo json_encode(array("status" => false, "message" => "Database connection failed"));
    exit();
}

?>