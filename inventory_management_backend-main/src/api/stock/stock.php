<?php 
header("Access-Control-Allow-Origin:*");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
// Include database connection
require_once_DIR__ .'/../../../db/Connect.php';
// Get request method
$request_method = $_SERVER['REQUEST_METHOD'];
// Create database instance
$database = new Database();
$db = $database->getConnection();
if ($db == null) {
    http_response_code(500);
    echo json_encode(array(
        "status" => false,
        "message" => "Database connection failed"
    ));
    exit();
}
// Route based on HTTP method - Include different function files
switch ($request_method) {
    case 'POST':
        require_once __DIR__ . '/add_stock.php';
        createStock($db);
        break;
        
    case 'GET':
        require_once __DIR__ . '/get_stock.php';
        getStock($db);
        break;
        
    case 'PUT':
        require_once __DIR__ . '/edit_stock.php';
        updateStock($db);
        break;
        
    case 'DELETE':
        require_once __DIR__ . '/delete_stock.php';
        deleteStock($db);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(array(
            "status" => false,
            "message" => "Method not allowed. Supported methods: GET, POST, PUT, DELETE"
        ));
        break;
}