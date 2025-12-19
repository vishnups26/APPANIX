<?php
// filepath: /home/anix-sam/Desktop/work/stock_management/backend/src/api/store/listofstores.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database connection
require_once __DIR__ . '/../../../db/connect.php';

// Get request method
$request_method = $_SERVER['REQUEST_METHOD'];

// OPTION request handling for CORS preflight

if ($request_method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if($request_method == 'GET') {
    $shopkeeperId = isset($_GET['shopkeeperId']) ? $_GET['shopkeeperId'] : null;
    if ($shopkeeperId == null) {
        http_response_code(400);
        echo json_encode(array("status" => false, "message" => "shopkeeperId parameter is required"));
        exit();
    }    
} else {
    http_response_code(405);
    echo json_encode(array("status" => false, "message" => "Only GET method is allowed"));
    exit();
}

// Create database instance

$database = new Database();
$db = $database->getConnection();

if($db != null) {
    try {
        $stores_query = "SELECT * FROM stores WHERE userId = ?";
        $stmt = $db->prepare($stores_query);
        $stmt->bindParam(1, $shopkeeperId);
        $stmt->execute();

        $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        http_response_code(200);
        echo json_encode(array(
            "status" => true,
            "message" => "Stores retrieved successfully",
            "stores" => $stores
        ));
        exit();

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array(
            "status" => false,
            "message" => "An error occurred: " . $e->getMessage()
        ));
        exit();
    }
    
} else {
    http_response_code(500);
    echo json_encode(array(
        "status" => false,
        "message" => "Database connection failed"
    ));
    exit();
}
 

?>