<?php   
// filepath: /home/anix-sam/Desktop/work/stock_management/backend/src/api/auth/logout.php
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
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sessionId = isset($_GET['sessionId']) ? $_GET['sessionId'] : null;
    if ($sessionId == null) {
        http_response_code(400);
        echo json_encode(array("status" => false, "message" => "sessionId parameter is required"));
        exit();
    }

} else {
    http_response_code(405);
    echo json_encode(array("status" => false, "message" => "Only POST method allowed"));
    exit();
}


// Create database instance

$database = new Database();
$db = $database->getConnection();


if ($db != null) {
    try {
        // Invalidate the session by deleting it from the sessions table
        $query = "DELETE FROM user_sessions WHERE session_id = ?";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $sessionId);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            http_response_code(200);
            echo json_encode(array("status" => true, "message" => "Logout successful"));
        } else {
            http_response_code(400);
            echo json_encode(array("status" => false, "message" => "Invalid sessionId or already logged out"));
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("status" => false, "message" => "Server error: " . $e->getMessage()));
    }
} else {
    http_response_code(500);
    echo json_encode(array("status" => false, "message" => "Database connection failed"));
}
?>




