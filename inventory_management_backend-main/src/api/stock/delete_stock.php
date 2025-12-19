<?php
// filepath: /home/anix-sam/Desktop/work/stock_management/backend/src/api/stock/delete_stock.php
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

// ===========================
// DELETE STOCK (DELETE METHOD)
// ===========================
function deleteStock($db) {
    // Get data from request body or URL parameters
    $data = json_decode(file_get_contents("php://input"));
    
    // If no JSON data, check URL parameters
    if (!$data) {
        $stock_id = isset($_GET['stock_id']) ? $_GET['stock_id'] : null;
        $user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
    } else {
        $stock_id = isset($data->stock_id) ? $data->stock_id : null;
        $user_id = isset($data->user_id) ? $data->user_id : null;
    }
    
    // Check if stock_id is provided
    if (empty($stock_id)) {
        http_response_code(400);
        echo json_encode(array(
            "status" => false,
            "message" => "Stock ID is required for deletion",
            "method" => "DELETE",
            "usage" => array(
                "url_parameter" => "DELETE /delete_stock.php?stock_id=123&user_id=1",
                "json_body" => '{"stock_id": 123, "user_id": 1}'
            )
        ));
        return;
    }
    
    // Validate stock_id is numeric
    if (!is_numeric($stock_id)) {
        http_response_code(400);
        echo json_encode(array(
            "status" => false,
            "message" => "Stock ID must be numeric",
            "method" => "DELETE"
        ));
        return;
    }
    
    try {
        
        // Check if stock exists and get its details
        $stock_check_query = "SELECT s.*, st.storename, u.username 
                             FROM `stocks` s 
                             JOIN `stores` st ON s.store_id = st.store_id 
                             JOIN `users` u ON s.user_id = u.id 
                             WHERE s.stock_id = ?";
        $stock_check_stmt = $db->prepare($stock_check_query);
        $stock_check_stmt->bindParam(1, $stock_id);
        $stock_check_stmt->execute();
        
        if ($stock_check_stmt->rowCount() == 0) {
            http_response_code(404);
            echo json_encode(array(
                "status" => false,
                "message" => "Stock not found. Invalid stock_id: " . $stock_id,
                "method" => "DELETE"
            ));
            return;
        }
        
        $stock_to_delete = $stock_check_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify ownership if user_id is provided
        if ($user_id && $stock_to_delete['user_id'] != $user_id) {
            http_response_code(403);
            echo json_encode(array(
                "status" => false,
                "message" => "Access denied. You can only delete your own stock.",
                "method" => "DELETE"
            ));
            return;
        }
        
        // Calculate business impact before deletion
        $deletion_impact = array();
        $potential_revenue_loss = null;
        $potential_profit_loss = null;
        
        if ($stock_to_delete['selling_unit_price']) {
            $potential_revenue_loss = round($stock_to_delete['selling_unit_price'] * $stock_to_delete['quantity'], 2);
            $potential_profit_loss = $stock_to_delete['profit_per_unit'] ? round($stock_to_delete['profit_per_unit'] * $stock_to_delete['quantity'], 2) : null;
        }
        
        $deletion_impact = array(
            "investment_loss" => (float)$stock_to_delete['purchase_bill_amount'],
            "potential_revenue_loss" => $potential_revenue_loss,
            "potential_profit_loss" => $potential_profit_loss,
            "quantity_removed" => (float)$stock_to_delete['quantity'] . " " . $stock_to_delete['quantity_unit']
        );
        
        // Store the stock data before deletion for response
        $deleted_stock_data = array(
            "stock_id" => (int)$stock_to_delete['stock_id'],
            "user_id" => (int)$stock_to_delete['user_id'],
            "username" => $stock_to_delete['username'],
            "store_id" => (int)$stock_to_delete['store_id'],
            "store_name" => $stock_to_delete['storename'],
            "product_name" => $stock_to_delete['product_name'],
            "quantity" => (float)$stock_to_delete['quantity'],
            "quantity_unit" => $stock_to_delete['quantity_unit'],
            "unit_display" => $stock_to_delete['quantity'] . " " . $stock_to_delete['quantity_unit'],
            
            // PURCHASE INFORMATION
            "purchase_info" => array(
                "purchase_bill_amount" => (float)$stock_to_delete['purchase_bill_amount'],
                "purchase_unit_amount" => (float)$stock_to_delete['purchase_unit_amount'],
                "unit_cost_display" => "$" . $stock_to_delete['purchase_unit_amount'] . " per " . $stock_to_delete['quantity_unit']
            ),
            
            // SELLING INFORMATION
            "selling_info" => array(
                "selling_unit_price" => $stock_to_delete['selling_unit_price'] ? (float)$stock_to_delete['selling_unit_price'] : null,
                "selling_price_display" => $stock_to_delete['selling_unit_price'] ? "$" . $stock_to_delete['selling_unit_price'] . " per " . $stock_to_delete['quantity_unit'] : "Not set",
                "profit_per_unit" => $stock_to_delete['profit_per_unit'] ? (float)$stock_to_delete['profit_per_unit'] : null,
                "profit_margin" => $stock_to_delete['profit_margin'] ? $stock_to_delete['profit_margin'] . "%" : null
            ),
            
            "timestamps" => array(
                "created_at" => $stock_to_delete['created_at'],
                "updated_at" => $stock_to_delete['updated_at'],
                "deleted_at" => date('Y-m-d H:i:s')
            )
        );
        
        // Delete the stock
        $delete_query = "DELETE FROM `stocks` WHERE stock_id = ?";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(1, $stock_id);
        
        if ($delete_stmt->execute()) {
            
            // Get remaining stock count for user
            $remaining_count_query = "SELECT COUNT(*) as remaining_stocks FROM `stocks` WHERE user_id = ?";
            $remaining_count_stmt = $db->prepare($remaining_count_query);
            $remaining_count_stmt->bindParam(1, $stock_to_delete['user_id']);
            $remaining_count_stmt->execute();
            $remaining_count = $remaining_count_stmt->fetchColumn();
            
            // Get remaining stocks for the same product (different units)
            $same_product_query = "SELECT COUNT(*) as same_product_stocks FROM `stocks` WHERE user_id = ? AND store_id = ? AND product_name = ?";
            $same_product_stmt = $db->prepare($same_product_query);
            $same_product_stmt->bindParam(1, $stock_to_delete['user_id']);
            $same_product_stmt->bindParam(2, $stock_to_delete['store_id']);
            $same_product_stmt->bindParam(3, $stock_to_delete['product_name']);
            $same_product_stmt->execute();
            $same_product_count = $same_product_stmt->fetchColumn();
            
            http_response_code(200);
            echo json_encode(array(
                "status" => true,
                "message" => "Stock deleted successfully",
                "method" => "DELETE",
                "action" => "deleted",
                "deleted_stock" => $deleted_stock_data,
                "deletion_impact" => $deletion_impact,
                "warnings" => array_filter(array(
                    $same_product_count == 0 ? "This was the last stock entry for product '" . $stock_to_delete['product_name'] . "' in store '" . $stock_to_delete['storename'] . "'" : null,
                    $remaining_count == 0 ? "This was your last stock entry. Consider adding new stock." : null,
                    $potential_profit_loss && $potential_profit_loss > 0 ? "Potential profit loss of $" . $potential_profit_loss . " if this stock was sold." : null
                )),
                "statistics" => array(
                    "remaining_user_stocks" => (int)$remaining_count,
                    "remaining_same_product_stocks" => (int)$same_product_count
                ),
                "recommendations" => array_filter(array(
                    $same_product_count == 0 ? "Consider restocking '" . $stock_to_delete['product_name'] . "' if it's a popular item." : null,
                    $remaining_count < 5 ? "Low stock count. Consider adding more products to your inventory." : null,
                    $potential_profit_loss && $potential_profit_loss > 1000 ? "High value stock deleted. Ensure this was intentional." : null
                ))
            ));
            
        } else {
            http_response_code(500);
            echo json_encode(array(
                "status" => false,
                "message" => "Failed to delete stock",
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
}

// Call the function
deleteStock($db);
?>