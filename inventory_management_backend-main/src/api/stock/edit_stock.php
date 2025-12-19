]<?php
// filepath: /home/anix-sam/Desktop/work/stock_management/backend/src/api/stock/edit_stock.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, PATCH");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database connection
require_once __DIR__ . '/../../../db/connect.php';

// Only allow PUT and PATCH requests
if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH'])) {
    http_response_code(405);
    echo json_encode(array("status" => false, "message" => "Only PUT and PATCH methods allowed"));
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
// EDIT STOCK (PUT/PATCH METHOD)
// ===========================
function editStock($db) {
    // Get posted data
    $data = json_decode(file_get_contents("php://input"));
    
    // Check if stock_id is provided
    if (empty($data->stock_id)) {
        http_response_code(400);
        echo json_encode(array(
            "status" => false,
            "message" => "Stock ID is required for editing",
            "method" => $_SERVER['REQUEST_METHOD']
        ));
        return;
    }
    
    // Validate stock_id is numeric
    if (!is_numeric($data->stock_id)) {
        http_response_code(400);
        echo json_encode(array(
            "status" => false,
            "message" => "Stock ID must be numeric",
            "method" => $_SERVER['REQUEST_METHOD']
        ));
        return;
    }
    
    try {
        
        // Check if stock exists
        $stock_check_query = "SELECT s.*, st.storename, u.username 
                             FROM `stocks` s 
                             JOIN `stores` st ON s.store_id = st.store_id 
                             JOIN `users` u ON s.user_id = u.id 
                             WHERE s.stock_id = ?";
        $stock_check_stmt = $db->prepare($stock_check_query);
        $stock_check_stmt->bindParam(1, $data->stock_id);
        $stock_check_stmt->execute();
        
        if ($stock_check_stmt->rowCount() == 0) {
            http_response_code(404);
            echo json_encode(array(
                "status" => false,
                "message" => "Stock not found. Invalid stock_id.",
                "method" => $_SERVER['REQUEST_METHOD']
            ));
            return;
        }
        
        $existing_stock = $stock_check_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify ownership if userId is provided
        if (isset($data->userId) && $existing_stock['user_id'] != $data->userId) {
            http_response_code(403);
            echo json_encode(array(
                "status" => false,
                "message" => "Access denied. You can only edit your own stock.",
                "method" => $_SERVER['REQUEST_METHOD']
            ));
            return;
        }
        
        // Prepare update fields
        $update_fields = array();
        $update_values = array();
        
        // Check and validate each field that can be updated
        if (isset($data->product_name) && !empty($data->product_name)) {
            $update_fields[] = "product_name = ?";
            $update_values[] = $data->product_name;
        }
        
        if (isset($data->quantity) && is_numeric($data->quantity) && $data->quantity > 0) {
            $update_fields[] = "quantity = ?";
            $update_values[] = $data->quantity;
        }
        
        if (isset($data->quantity_unit) && !empty($data->quantity_unit)) {
            $allowed_units = array('pieces', 'kg', 'grams', 'liters', 'ml', 'meters', 'cm', 'boxes', 'packs', 'tons', 'pounds', 'ounces');
            if (in_array(strtolower($data->quantity_unit), $allowed_units)) {
                $update_fields[] = "quantity_unit = ?";
                $update_values[] = $data->quantity_unit;
            } else {
                http_response_code(400);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Invalid quantity unit. Allowed units: " . implode(', ', $allowed_units),
                    "method" => $_SERVER['REQUEST_METHOD']
                ));
                return;
            }
        }
        
        if (isset($data->purchase_bill_amount) && is_numeric($data->purchase_bill_amount) && $data->purchase_bill_amount >= 0) {
            $update_fields[] = "purchase_bill_amount = ?";
            $update_values[] = $data->purchase_bill_amount;
        }
        
        if (isset($data->purchase_unit_amount) && is_numeric($data->purchase_unit_amount) && $data->purchase_unit_amount >= 0) {
            $update_fields[] = "purchase_unit_amount = ?";
            $update_values[] = $data->purchase_unit_amount;
        }
        
        if (isset($data->selling_unit_price)) {
            if ($data->selling_unit_price === null || $data->selling_unit_price === "") {
                $update_fields[] = "selling_unit_price = NULL";
            } elseif (is_numeric($data->selling_unit_price) && $data->selling_unit_price >= 0) {
                $update_fields[] = "selling_unit_price = ?";
                $update_values[] = $data->selling_unit_price;
            } else {
                http_response_code(400);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Selling unit price must be numeric and non-negative",
                    "method" => $_SERVER['REQUEST_METHOD']
                ));
                return;
            }
        }
        
        // If no fields to update
        if (empty($update_fields)) {
            http_response_code(400);
            echo json_encode(array(
                "status" => false,
                "message" => "No valid fields provided for update",
                "method" => $_SERVER['REQUEST_METHOD'],
                "updatable_fields" => array(
                    "product_name", "quantity", "quantity_unit", 
                    "purchase_bill_amount", "purchase_unit_amount", "selling_unit_price"
                )
            ));
            return;
        }
        
        // Get current values for calculation
        $current_quantity = isset($data->quantity) ? $data->quantity : $existing_stock['quantity'];
        $current_purchase_unit_amount = isset($data->purchase_unit_amount) ? $data->purchase_unit_amount : $existing_stock['purchase_unit_amount'];
        $current_selling_unit_price = isset($data->selling_unit_price) ? $data->selling_unit_price : $existing_stock['selling_unit_price'];
        
        // Validate purchase bill consistency if both quantity and unit amount are being updated
        if (isset($data->quantity) && isset($data->purchase_unit_amount) && isset($data->purchase_bill_amount)) {
            $calculated_total = round($data->quantity * $data->purchase_unit_amount, 2);
            if (abs($calculated_total - $data->purchase_bill_amount) > 0.01) {
                http_response_code(400);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Purchase bill amount inconsistency. Calculated: $" . $calculated_total . ", Provided: $" . $data->purchase_bill_amount,
                    "method" => $_SERVER['REQUEST_METHOD'],
                    "calculation" => $data->quantity . " × $" . $data->purchase_unit_amount . " = $" . $calculated_total
                ));
                return;
            }
        }
        
        // Calculate profit metrics
        $profit_per_unit = null;
        $profit_margin_percent = null;
        if ($current_selling_unit_price && $current_purchase_unit_amount > 0) {
            $profit_per_unit = round($current_selling_unit_price - $current_purchase_unit_amount, 4);
            $profit_margin_percent = round(($profit_per_unit / $current_purchase_unit_amount) * 100, 2);
            
            $update_fields[] = "profit_per_unit = ?";
            $update_values[] = $profit_per_unit;
            $update_fields[] = "profit_margin = ?";
            $update_values[] = $profit_margin_percent;
        } else {
            $update_fields[] = "profit_per_unit = NULL";
            $update_fields[] = "profit_margin = NULL";
        }
        
        // Add updated_at field
        $update_fields[] = "updated_at = CURRENT_TIMESTAMP";
        
        // Build and execute update query
        $update_query = "UPDATE `stocks` SET " . implode(", ", $update_fields) . " WHERE stock_id = ?";
        $update_values[] = $data->stock_id;
        
        $update_stmt = $db->prepare($update_query);
        
        if ($update_stmt->execute($update_values)) {
            
            // Get updated stock data
            $updated_stock_query = "SELECT s.*, st.storename, u.username 
                                   FROM `stocks` s 
                                   JOIN `stores` st ON s.store_id = st.store_id 
                                   JOIN `users` u ON s.user_id = u.id 
                                   WHERE s.stock_id = ?";
            $updated_stock_stmt = $db->prepare($updated_stock_query);
            $updated_stock_stmt->bindParam(1, $data->stock_id);
            $updated_stock_stmt->execute();
            $updated_stock = $updated_stock_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Calculate potential revenue and profit
            $potential_revenue = null;
            $potential_profit = null;
            if ($updated_stock['selling_unit_price']) {
                $potential_revenue = round($updated_stock['selling_unit_price'] * $updated_stock['quantity'], 2);
                $potential_profit = $updated_stock['profit_per_unit'] ? round($updated_stock['profit_per_unit'] * $updated_stock['quantity'], 2) : null;
            }
            
            http_response_code(200);
            echo json_encode(array(
                "status" => true,
                "message" => "Stock updated successfully",
                "method" => $_SERVER['REQUEST_METHOD'],
                "action" => "updated",
                "stock_data" => array(
                    "stock_id" => (int)$updated_stock['stock_id'],
                    "user_id" => (int)$updated_stock['user_id'],
                    "username" => $updated_stock['username'],
                    "store_id" => (int)$updated_stock['store_id'],
                    "store_name" => $updated_stock['storename'],
                    "product_name" => $updated_stock['product_name'],
                    "quantity" => (float)$updated_stock['quantity'],
                    "quantity_unit" => $updated_stock['quantity_unit'],
                    "unit_display" => $updated_stock['quantity'] . " " . $updated_stock['quantity_unit'],
                    
                    // PURCHASE INFORMATION
                    "purchase_info" => array(
                        "purchase_bill_amount" => (float)$updated_stock['purchase_bill_amount'],
                        "purchase_unit_amount" => (float)$updated_stock['purchase_unit_amount'],
                        "unit_cost_display" => "$" . $updated_stock['purchase_unit_amount'] . " per " . $updated_stock['quantity_unit'],
                        "calculation" => $updated_stock['quantity'] . " × $" . $updated_stock['purchase_unit_amount'] . " = $" . ($updated_stock['quantity'] * $updated_stock['purchase_unit_amount'])
                    ),
                    
                    // SELLING INFORMATION
                    "selling_info" => array(
                        "selling_unit_price" => $updated_stock['selling_unit_price'] ? (float)$updated_stock['selling_unit_price'] : null,
                        "selling_price_display" => $updated_stock['selling_unit_price'] ? "$" . $updated_stock['selling_unit_price'] . " per " . $updated_stock['quantity_unit'] : "Not set",
                        "profit_per_unit" => $updated_stock['profit_per_unit'] ? (float)$updated_stock['profit_per_unit'] : null,
                        "profit_margin" => $updated_stock['profit_margin'] ? $updated_stock['profit_margin'] . "%" : null
                    ),
                    
                    // BUSINESS ANALYSIS
                    "business_analysis" => array(
                        "total_investment" => (float)$updated_stock['purchase_bill_amount'],
                        "potential_revenue" => $potential_revenue,
                        "potential_profit" => $potential_profit,
                        "break_even_price" => (float)$updated_stock['purchase_unit_amount'],
                        "recommendation" => $updated_stock['selling_unit_price'] ? 
                            ($updated_stock['profit_margin'] > 0 ? "Good profit margin of " . $updated_stock['profit_margin'] . "%" : "Loss - selling price below cost!") : 
                            "Set selling unit price to calculate profitability"
                    ),
                    
                    "timestamps" => array(
                        "created_at" => $updated_stock['created_at'],
                        "updated_at" => $updated_stock['updated_at']
                    )
                ),
                "changes_made" => array_keys($data)
            ));
            
        } else {
            http_response_code(500);
            echo json_encode(array(
                "status" => false,
                "message" => "Failed to update stock",
                "method" => $_SERVER['REQUEST_METHOD']
            ));
        }
        
    } catch(PDOException $exception) {
        http_response_code(500);
        echo json_encode(array(
            "status" => false,
            "message" => "Database operation failed",
            "method" => $_SERVER['REQUEST_METHOD'],
            "error" => $exception->getMessage()
        ));
    }
}

// Call the function
editStock($db);
?>