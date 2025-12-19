<?php
// filepath: /home/anix-sam/Desktop/work/stock_management/backend/src/api/users/user.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE, GET, PATCH");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database connection
require_once __DIR__ . '/../../../db/connect.php';


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] == 'PATCH') {
    $userId = isset($_GET['userId']) ? $_GET['userId'] : null;
    if ($userId == null) {
        http_response_code(400);
        echo json_encode(array("status" => false, "message" => "userId parameter is required"));
        exit();
    }
    $data = json_decode(file_get_contents("php://input"));
} else if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $userId = isset($_GET['userId']) ? $_GET['userId'] : null;
    if ($userId == null) {
        http_response_code(400);
        echo json_encode(array("status" => false, "message" => "userId parameter is required"));
        exit();
    }
} else if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    $userId = isset($_GET['userId']) ? $_GET['userId'] : null;
    if ($userId == null) {
        http_response_code(400);
        echo json_encode(array("status" => false, "message" => "userId parameter is required"));
        exit();
    }
} else {
    http_response_code(405);
    echo json_encode(array("status" => false, "message" => "Only PATCH, GET and DELETE methods are allowed"));
    exit();
}

// Create database instance
$database = new Database();
$db = $database->getConnection();


if ($db != null) {
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

    if ($session_stmt->rowCount() == 1) {
        $session = $session_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session['is_active']) {
            http_response_code(403);
            echo json_encode(array("status" => false, "message" => "Session is inactive. Please login again."));
            exit();
        }

        $userRole = $session['userRole'];
        $requesterId = $session['user_id'];

        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            // Fetch user details
            $user_query = "SELECT id, username, email, firstname, lastname, address, userRole, is_active, created_at 
                           FROM `users` 
                           WHERE id = ?";
            $user_stmt = $db->prepare($user_query);
            $user_stmt->bindParam(1, $userId);
            $user_stmt->execute();

            if ($user_stmt->rowCount() == 1) {
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                http_response_code(200);
                echo json_encode(array("status" => true, "user" => $user));
            } else {
                http_response_code(404);
                echo json_encode(array("status" => false, "message" => "User not found"));
            }
        } else if ($_SERVER['REQUEST_METHOD'] == 'PATCH') {
        }   else if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
            // Prevent users from deleting themselves
            if ($requesterId == $userId) {
                http_response_code(400);
                echo json_encode(array("status" => false, "message" => "You cannot delete your own account."));
                exit();
            }

            if($user->created_by_id != $requesterId) {
                http_response_code(403);
                echo json_encode(array("status" => false, "message" => "You do not have permission to delete this user."));
                exit();
            }

            // Delete user
            $delete_query = "DELETE FROM `users` WHERE id = ?";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(1, $userId);
            $delete_stmt->execute();

            if ($delete_stmt->rowCount() > 0) {
                http_response_code(200);
                echo json_encode(array("status" => true, "message" => "User deleted successfully"));
            } else {
                http_response_code(404);
                echo json_encode(array("status" => false, "message" => "User not found or already deleted"));
            }
        }
    } else {
        http_response_code(401);
        echo json_encode(array("status" => false, "message" => "Invalid session. Please log in again."));
        exit();
    }

} else {
    http_response_code(500);
    echo json_encode(array("status" => false, "message" => "Database connection failed"));
}