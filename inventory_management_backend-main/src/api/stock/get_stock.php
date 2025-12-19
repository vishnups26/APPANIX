<?php
// filepath: /home/anix-sam/Desktop/work/stock_management/backend/src/api/stock/get_stock.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database connection
require_once __DIR__ . '/../../../db/connect.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(array("status" => false, "message" => "Only GET method allowed"));
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
// GET STOCK (GET METHOD)
// ===========================
function getStock($db) {
    
    // Get URL parameters
    $stock_id = isset($_GET['stock_id']) ? $_GET['stock_id'] : null;
    $user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
    $store_id = isset($_GET['store_id']) ? $_GET['store_id'] : null;
    $product_name = isset($_GET['product_name']) ? $_GET['product_name'] : null;
    $quantity_unit = isset($_GET['quantity_unit']) ? $_GET['quantity_unit'] : null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $order_by = isset($_GET['order_by']) ? $_GET['order_by'] : 'created_at';
    $order_dir = isset($_GET['order_dir']) && strtoupper($_GET['order_dir']) === 'ASC' ? 'ASC' : 'DESC';
    
    try {
        
        // Build base query
        $base_query = "SELECT s.*, st.storename, u.username 
                      FROM `stocks` s 
                      JOIN `stores` st ON s.store_id = st.store_id 
                      JOIN `users` u ON s.user_id = u.id";
        
        $where_conditions = array();
        $query_params = array();
        
        // Add where conditions based on parameters
        if ($stock_id) {
            if (!is_numeric($stock_id)) {
                http_response_code(400);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Stock ID must be numeric",
                    "method" => "GET"
                ));
                return;
            }
            $where_conditions[] = "s.stock_id = ?";
            $query_params[] = $stock_id;
        }
        
        if ($user_id) {
            if (!is_numeric($user_id)) {
                http_response_code(400);
                echo json_encode(array(
                    "status" => false,
                    "message" => "User ID must be numeric",
                    "method" => "GET"
                ));
                return;
            }
            $where_conditions[] = "s.user_id = ?";
            $query_params[] = $user_id;
        }
        
        if ($store_id) {
            if (!is_numeric($store_id)) {
                http_response_code(400);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Store ID must be numeric",
                    "method" => "GET"
                ));
                return;
            }
            $where_conditions[] = "s.store_id = ?";
            $query_params[] = $store_id;
        }
        
        if ($product_name) {
            $where_conditions[] = "s.product_name LIKE ?";
            $query_params[] = "%" . $product_name . "%";
        }
        
        if ($quantity_unit) {
            $where_conditions[] = "s.quantity_unit = ?";
            $query_params[] = $quantity_unit;
        }
        
        // Add WHERE clause if conditions exist
        if (!empty($where_conditions)) {
            $base_query .= " WHERE " . implode(" AND ", $where_conditions);
        }
        
        // Validate order_by field
        $allowed_order_fields = array('stock_id', 'product_name', 'quantity', 'purchase_bill_amount', 'purchase_unit_amount', 'selling_unit_price', 'profit_margin', 'created_at', 'updated_at');
        if (!in_array($order_by, $allowed_order_fields)) {
            $order_by = 'created_at';
        }
        
        // Add ORDER BY and LIMIT
        $base_query .= " ORDER BY s." . $order_by . " " . $order_dir . " LIMIT ? OFFSET ?";
        $query_params[] = $limit;
        $query_params[] = $offset;
        
        // Execute query
        $stmt = $db->prepare($base_query);
        $stmt->execute($query_params);
        $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count for pagination (remove LIMIT and OFFSET for count)
        $count_query = str_replace("SELECT s.*, st.storename, u.username", "SELECT COUNT(*)", $base_query);
        $count_query = preg_replace('/ORDER BY.*/', '', $count_query);
        $count_params = array_slice($query_params, 0, -2); // Remove limit and offset
        
        $count_stmt = $db->prepare($count_query);
        $count_stmt->execute($count_params);
        $total_count = $count_stmt->fetchColumn();
        
        // Process stock data
        $processed_stocks = array();
        $total_investment = 0;
        $total_potential_revenue = 0;
        $total_potential_profit = 0;
        
        foreach ($stocks as $stock) {
            
            // Calculate business metrics
            $potential_revenue = null;
            $potential_profit = null;
            if ($stock['selling_unit_price']) {
                $potential_revenue = round($stock['selling_unit_price'] * $stock['quantity'], 2);
                $potential_profit = $stock['profit_per_unit'] ? round($stock['profit_per_unit'] * $stock['quantity'], 2) : null;
                $total_potential_revenue += $potential_revenue;
                if ($potential_profit) $total_potential_profit += $potential_profit;
            }
            
            $total_investment += $stock['purchase_bill_amount'];
            
            $processed_stocks[] = array(
                "stock_id" => (int)$stock['stock_id'],
                "user_id" => (int)$stock['user_id'],
                "username" => $stock['username'],
                "store_id" => (int)$stock['store_id'],
                "store_name" => $stock['storename'],
                "product_name" => $stock['product_name'],
                "quantity" => (float)$stock['quantity'],
                "quantity_unit" => $stock['quantity_unit'],
                "unit_display" => $stock['quantity'] . " " . $stock['quantity_unit'],
                
                // PURCHASE INFORMATION
                "purchase_info" => array(
                    "purchase_bill_amount" => (float)$stock['purchase_bill_amount'],
                    "purchase_unit_amount" => (float)$stock['purchase_unit_amount'],
                    "unit_cost_display" => "$" . $stock['purchase_unit_amount'] . " per " . $stock['quantity_unit'],
                    "calculation" => $stock['quantity'] . " × $" . $stock['purchase_unit_amount'] . " = $" . ($stock['quantity'] * $stock['purchase_unit_amount'])
                ),
                
                // SELLING INFORMATION
                "selling_info" => array(
                    "selling_unit_price" => $stock['selling_unit_price'] ? (float)$stock['selling_unit_price'] : null,
                    "selling_price_display" => $stock['selling_unit_price'] ? "$" . $stock['selling_unit_price'] . " per " . $stock['quantity_unit'] : "Not set",
                    "profit_per_unit" => $stock['profit_per_unit'] ? (float)$stock['profit_per_unit'] : null,
                    "profit_margin" => $stock['profit_margin'] ? (float)$stock['profit_margin'] : null,
                    "profit_margin_display" => $stock['profit_margin'] ? $stock['profit_margin'] . "%" : null
                ),
                
                // BUSINESS ANALYSIS
                "business_analysis" => array(
                    "total_investment" => (float)$stock['purchase_bill_amount'],
                    "potential_revenue" => $potential_revenue,
                    "potential_profit" => $potential_profit,
                    "break_even_price" => (float)$stock['purchase_unit_amount'],
                    "profitability_status" => $stock['selling_unit_price'] ? 
                        ($stock['profit_margin'] > 0 ? "profitable" : ($stock['profit_margin'] < 0 ? "loss" : "break_even")) : 
                        "price_not_set",
                    "recommendation" => $stock['selling_unit_price'] ? 
                        ($stock['profit_margin'] > 0 ? "Good profit margin of " . $stock['profit_margin'] . "%" : 
                         ($stock['profit_margin'] < 0 ? "Loss - selling price below cost!" : "Break-even pricing")) : 
                        "Set selling unit price to calculate profitability"
                ),
                
                "timestamps" => array(
                    "created_at" => $stock['created_at'],
                    "updated_at" => $stock['updated_at']
                )
            );
        }
        
        // Calculate overall business metrics
        $overall_profit_margin = $total_investment > 0 ? round(($total_potential_profit / $total_investment) * 100, 2) : null;
        
        http_response_code(200);
        echo json_encode(array(
            "status" => true,
            "message" => "Stocks retrieved successfully",
            "method" => "GET",
            "data" => array(
                "stocks" => $processed_stocks,
                "pagination" => array(
                    "total_count" => (int)$total_count,
                    "current_count" => count($processed_stocks),
                    "limit" => $limit,
                    "offset" => $offset,
                    "has_more" => ($offset + $limit) < $total_count
                ),
                "summary" => array(
                    "total_investment" => $total_investment,
                    "total_potential_revenue" => $total_potential_revenue,
                    "total_potential_profit" => $total_potential_profit,
                    "overall_profit_margin" => $overall_profit_margin ? $overall_profit_margin . "%" : null,
                    "stocks_with_selling_price" => count(array_filter($stocks, function($s) { return $s['selling_unit_price']; })),
                    "stocks_without_selling_price" => count(array_filter($stocks, function($s) { return !$s['selling_unit_price']; })),
                    "profitable_stocks" => count(array_filter($stocks, function($s) { return $s['profit_margin'] > 0; })),
                    "loss_making_stocks" => count(array_filter($stocks, function($s) { return $s['profit_margin'] < 0; }))
                )
            ),
            "query_info" => array(
                "filters_applied" => array_filter(array(
                    "stock_id" => $stock_id,
                    "user_id" => $user_id,
                    "store_id" => $store_id,
                    "product_name" => $product_name,
                    "quantity_unit" => $quantity_unit
                )),
                "order_by" => $order_by . " " . $order_dir
            )
        ));
        
    } catch(PDOException $exception) {
        http_response_code(500);
        echo json_encode(array(
            "status" => false,
            "message" => "Database operation failed",
            "method" => "GET",
            "error" => $exception->getMessage()
        ));
    }
}

// Call the function
getStock($db);
?>