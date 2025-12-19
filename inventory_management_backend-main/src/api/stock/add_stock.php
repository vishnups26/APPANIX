<?php
// filepath: /home/anix-sam/Desktop/work/stock_management/backend/src/api/stock/add_stock.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database connection
require_once __DIR__ . '/../../../db/connect.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array("status" => false, "message" => "Only POST method allowed"));
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
// CREATE STOCK (POST METHOD)
// ===========================
function createStock($db) {
    // Get posted data
    $data = json_decode(file_get_contents("php://input"));
    
    // Check if all required fields are provided (with new field names)
    if (
        !empty($data->userId) &&
        !empty($data->product_name) &&
        !empty($data->quantity) &&
        isset($data->purchase_bill_amount) &&
        isset($data->purchase_unit_amount) &&
        isset($data->store_id) &&
        !empty($data->quantity_unit)
    ) {
        
        // Validate userId and store_id are numeric
        if (!is_numeric($data->userId) || !is_numeric($data->store_id)) {
            http_response_code(400);
            echo json_encode(array(
                "status" => false,
                "message" => "User ID and Store ID must be numeric",
                "method" => "POST"
            ));
            return;
        }
        
        // Validate numeric fields
        if (!is_numeric($data->quantity) || !is_numeric($data->purchase_bill_amount) || !is_numeric($data->purchase_unit_amount)) {
            http_response_code(400);
            echo json_encode(array(
                "status" => false,
                "message" => "Quantity, purchase bill amount, and purchase unit amount must be numeric values",
                "method" => "POST"
            ));
            return;
        }
        
        // Validate positive values
        if ($data->quantity <= 0 || $data->purchase_bill_amount < 0 || $data->purchase_unit_amount < 0) {
            http_response_code(400);
            echo json_encode(array(
                "status" => false,
                "message" => "Quantity must be greater than 0, purchase bill amount and purchase unit amount cannot be negative",
                "method" => "POST"
            ));
            return;
        }
        
        // Calculate total purchase price if not provided
        $calculated_total = round($data->quantity * $data->purchase_unit_amount, 2);
        
        // Validate calculation consistency
        if (abs($calculated_total - $data->purchase_bill_amount) > 0.01) {
            http_response_code(400);
            echo json_encode(array(
                "status" => false,
                "message" => "Purchase bill amount inconsistency. Calculated: $" . $calculated_total . ", Provided: $" . $data->purchase_bill_amount,
                "method" => "POST",
                "calculation" => $data->quantity . " × $" . $data->purchase_unit_amount . " = $" . $calculated_total
            ));
            return;
        }
        
        // Validate selling_unit_price if provided
        $selling_unit_price = isset($data->selling_unit_price) ? $data->selling_unit_price : null;
        if ($selling_unit_price !== null && !is_numeric($selling_unit_price)) {
            http_response_code(400);
            echo json_encode(array(
                "status" => false,
                "message" => "Selling unit price must be numeric if provided",
                "method" => "POST"
            ));
            return;
        }
        
        // Validate quantity_unit
        $allowed_units = array('pieces', 'kg', 'grams', 'liters', 'ml', 'meters', 'cm', 'boxes', 'packs', 'tons', 'pounds', 'ounces');
        if (!in_array(strtolower($data->quantity_unit), $allowed_units)) {
            http_response_code(400);
            echo json_encode(array(
                "status" => false,
                "message" => "Invalid quantity unit. Allowed units: " . implode(', ', $allowed_units),
                "method" => "POST",
                "allowed_units" => $allowed_units
            ));
            return;
        }
        
        // Calculate profit metrics if selling price is provided
        $profit_per_unit = null;
        $profit_margin_percent = null;
        $potential_revenue = null;
        $potential_profit = null;
        
        if ($selling_unit_price && $data->purchase_unit_amount > 0) {
            $profit_per_unit = round($selling_unit_price - $data->purchase_unit_amount, 4);
            $profit_margin_percent = round(($profit_per_unit / $data->purchase_unit_amount) * 100, 2);
            $potential_revenue = round($selling_unit_price * $data->quantity, 2);
            $potential_profit = round($profit_per_unit * $data->quantity, 2);
        }
        
        try {
            
            // Check if user exists
            $user_check_query = "SELECT id, username, userRole FROM `users` WHERE id = ?";
            $user_check_stmt = $db->prepare($user_check_query);
            $user_check_stmt->bindParam(1, $data->userId);
            $user_check_stmt->execute();
            
            if ($user_check_stmt->rowCount() == 0) {
                http_response_code(404);
                echo json_encode(array(
                    "status" => false,
                    "message" => "User not found. Invalid userId.",
                    "method" => "POST"
                ));
                return;
            }
            
            $user_data = $user_check_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if store exists and belongs to user
            $store_check_query = "SELECT store_id, storename, userId FROM `stores` WHERE store_id = ?";
            $store_check_stmt = $db->prepare($store_check_query);
            $store_check_stmt->bindParam(1, $data->store_id);
            $store_check_stmt->execute();
            
            if ($store_check_stmt->rowCount() == 0) {
                http_response_code(404);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Store not found. Invalid store_id.",
                    "method" => "POST"
                ));
                return;
            }
            
            $store_data = $store_check_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify store ownership
            if ($store_data['userId'] != $data->userId) {
                http_response_code(403);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Access denied. You can only add stock to your own stores.",
                    "method" => "POST"
                ));
                return;
            }
            
            // Create stocks table with updated field names
            $create_table_query = "CREATE TABLE IF NOT EXISTS `stocks` (
                stock_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                store_id INT NOT NULL,
                product_name VARCHAR(255) NOT NULL,
                quantity DECIMAL(10, 3) NOT NULL,
                quantity_unit VARCHAR(20) NOT NULL DEFAULT 'pieces',
                purchase_bill_amount DECIMAL(10, 2) NOT NULL,
                purchase_unit_amount DECIMAL(10, 4) NOT NULL,
                selling_unit_price DECIMAL(10, 4) DEFAULT NULL,
                profit_per_unit DECIMAL(10, 4) DEFAULT NULL,
                profit_margin DECIMAL(5, 2) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (store_id) REFERENCES stores(store_id) ON DELETE CASCADE,
                INDEX idx_product_store (product_name, store_id, user_id)
            )";
            
            $create_stmt = $db->prepare($create_table_query);
            $create_stmt->execute();
            
            // Check if product already exists in this store with same unit
            $product_check_query = "SELECT stock_id, quantity, purchase_bill_amount, purchase_unit_amount FROM `stocks` 
                                   WHERE product_name = ? AND store_id = ? AND user_id = ? AND quantity_unit = ?";
            $product_check_stmt = $db->prepare($product_check_query);
            $product_check_stmt->bindParam(1, $data->product_name);
            $product_check_stmt->bindParam(2, $data->store_id);
            $product_check_stmt->bindParam(3, $data->userId);
            $product_check_stmt->bindParam(4, $data->quantity_unit);
            $product_check_stmt->execute();
            
            if ($product_check_stmt->rowCount() > 0) {
                // Product exists with same unit, update quantities and recalculate averages
                $existing_product = $product_check_stmt->fetch(PDO::FETCH_ASSOC);
                $new_quantity = $existing_product['quantity'] + $data->quantity;
                $new_total_bill_amount = $existing_product['purchase_bill_amount'] + $data->purchase_bill_amount;
                $new_average_unit_amount = round($new_total_bill_amount / $new_quantity, 4);
                
                // Recalculate profit if selling price is provided
                $updated_profit_per_unit = null;
                $updated_profit_margin = null;
                if ($selling_unit_price) {
                    $updated_profit_per_unit = round($selling_unit_price - $new_average_unit_amount, 4);
                    $updated_profit_margin = round(($updated_profit_per_unit / $new_average_unit_amount) * 100, 2);
                }
                
                $update_query = "UPDATE `stocks` SET 
                                quantity = ?, 
                                purchase_bill_amount = ?, 
                                purchase_unit_amount = ?,
                                selling_unit_price = ?,
                                profit_per_unit = ?,
                                profit_margin = ?,
                                updated_at = CURRENT_TIMESTAMP 
                                WHERE stock_id = ?";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(1, $new_quantity);
                $update_stmt->bindParam(2, $new_total_bill_amount);
                $update_stmt->bindParam(3, $new_average_unit_amount);
                $update_stmt->bindParam(4, $selling_unit_price);
                $update_stmt->bindParam(5, $updated_profit_per_unit);
                $update_stmt->bindParam(6, $updated_profit_margin);
                $update_stmt->bindParam(7, $existing_product['stock_id']);
                
                if ($update_stmt->execute()) {
                    http_response_code(200);
                    echo json_encode(array(
                        "status" => true,
                        "message" => "Stock updated successfully (product already existed with same unit)",
                        "method" => "POST",
                        "action" => "updated",
                        "stock_data" => array(
                            "stock_id" => $existing_product['stock_id'],
                            "user_id" => $data->userId,
                            "username" => $user_data['username'],
                            "store_id" => $data->store_id,
                            "store_name" => $store_data['storename'],
                            "product_name" => $data->product_name,
                            "previous_quantity" => (float)$existing_product['quantity'],
                            "added_quantity" => (float)$data->quantity,
                            "new_total_quantity" => (float)$new_quantity,
                            "quantity_unit" => $data->quantity_unit,
                            "unit_display" => $new_quantity . " " . $data->quantity_unit,
                            "previous_bill_amount" => (float)$existing_product['purchase_bill_amount'],
                            "added_bill_amount" => (float)$data->purchase_bill_amount,
                            "new_total_bill_amount" => (float)$new_total_bill_amount,
                            "previous_unit_amount" => (float)$existing_product['purchase_unit_amount'],
                            "added_unit_amount" => (float)$data->purchase_unit_amount,
                            "new_average_unit_amount" => $new_average_unit_amount,
                            "unit_cost_display" => "$" . $new_average_unit_amount . " per " . $data->quantity_unit,
                            "selling_unit_price" => $selling_unit_price,
                            "profit_per_unit" => $updated_profit_per_unit,
                            "profit_margin" => $updated_profit_margin ? $updated_profit_margin . "%" : null
                        )
                    ));
                } else {
                    http_response_code(500);
                    echo json_encode(array(
                        "status" => false,
                        "message" => "Failed to update existing stock",
                        "method" => "POST"
                    ));
                }
            } else {
                // Check if product exists with different unit (warn user)
                $different_unit_check = "SELECT stock_id, quantity, quantity_unit, purchase_unit_amount FROM `stocks` 
                                        WHERE product_name = ? AND store_id = ? AND user_id = ? AND quantity_unit != ?";
                $different_unit_stmt = $db->prepare($different_unit_check);
                $different_unit_stmt->bindParam(1, $data->product_name);
                $different_unit_stmt->bindParam(2, $data->store_id);
                $different_unit_stmt->bindParam(3, $data->userId);
                $different_unit_stmt->bindParam(4, $data->quantity_unit);
                $different_unit_stmt->execute();
                
                $existing_different_unit = null;
                if ($different_unit_stmt->rowCount() > 0) {
                    $existing_different_unit = $different_unit_stmt->fetch(PDO::FETCH_ASSOC);
                }
                
                // New product or new unit, insert new record
                $insert_query = "INSERT INTO `stocks` (user_id, store_id, product_name, quantity, quantity_unit, purchase_bill_amount, purchase_unit_amount, selling_unit_price, profit_per_unit, profit_margin) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->bindParam(1, $data->userId);
                $insert_stmt->bindParam(2, $data->store_id);
                $insert_stmt->bindParam(3, $data->product_name);
                $insert_stmt->bindParam(4, $data->quantity);
                $insert_stmt->bindParam(5, $data->quantity_unit);
                $insert_stmt->bindParam(6, $data->purchase_bill_amount);
                $insert_stmt->bindParam(7, $data->purchase_unit_amount);
                $insert_stmt->bindParam(8, $selling_unit_price);
                $insert_stmt->bindParam(9, $profit_per_unit);
                $insert_stmt->bindParam(10, $profit_margin_percent);
                
                if ($insert_stmt->execute()) {
                    $stock_id = $db->lastInsertId();
                    
                    // Get total stock count for user
                    $count_query = "SELECT COUNT(*) as user_total_stocks FROM `stocks` WHERE user_id = ?";
                    $count_stmt = $db->prepare($count_query);
                    $count_stmt->bindParam(1, $data->userId);
                    $count_stmt->execute();
                    $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $response_data = array(
                        "status" => true,
                        "message" => "Stock created successfully",
                        "method" => "POST",
                        "action" => "created",
                        "stock_data" => array(
                            "stock_id" => $stock_id,
                            "user_id" => $data->userId,
                            "username" => $user_data['username'],
                            "store_id" => $data->store_id,
                            "store_name" => $store_data['storename'],
                            "product_name" => $data->product_name,
                            "quantity" => (float)$data->quantity,
                            "quantity_unit" => $data->quantity_unit,
                            "unit_display" => $data->quantity . " " . $data->quantity_unit,
                            
                            // PURCHASE INFORMATION (What you paid)
                            "purchase_info" => array(
                                "purchase_bill_amount" => (float)$data->purchase_bill_amount,
                                "purchase_unit_amount" => (float)$data->purchase_unit_amount,
                                "unit_cost_display" => "$" . $data->purchase_unit_amount . " per " . $data->quantity_unit,
                                "calculation" => $data->quantity . " × $" . $data->purchase_unit_amount . " = $" . $data->purchase_bill_amount
                            ),
                            
                            // SELLING INFORMATION (What you charge customers)
                            "selling_info" => array(
                                "selling_unit_price" => $selling_unit_price,
                                "selling_price_display" => $selling_unit_price ? "$" . $selling_unit_price . " per " . $data->quantity_unit : "Not set",
                                "profit_per_unit" => $profit_per_unit,
                                "profit_margin" => $profit_margin_percent ? $profit_margin_percent . "%" : null
                            ),
                            
                            // BUSINESS ANALYSIS
                            "business_analysis" => array(
                                "total_investment" => (float)$data->purchase_bill_amount,
                                "potential_revenue" => $potential_revenue,
                                "potential_profit" => $potential_profit,
                                "break_even_price" => (float)$data->purchase_unit_amount,
                                "recommendation" => $selling_unit_price ? 
                                    ($profit_margin_percent > 0 ? "Good profit margin of " . $profit_margin_percent . "%" : "Loss - selling price below cost!") : 
                                    "Set selling unit price to calculate profitability"
                            )
                        ),
                        "statistics" => array(
                            "user_total_stocks" => $count_result['user_total_stocks']
                        )
                    );
                    
                    // Add warning if same product exists with different unit
                    if ($existing_different_unit) {
                        $response_data["warning"] = array(
                            "message" => "This product already exists with a different unit",
                            "existing_stock" => array(
                                "stock_id" => $existing_different_unit['stock_id'],
                                "quantity" => (float)$existing_different_unit['quantity'],
                                "unit" => $existing_different_unit['quantity_unit'],
                                "display" => $existing_different_unit['quantity'] . " " . $existing_different_unit['quantity_unit'],
                                "purchase_unit_amount" => (float)$existing_different_unit['purchase_unit_amount'],
                                "cost_display" => "$" . $existing_different_unit['purchase_unit_amount'] . " per " . $existing_different_unit['quantity_unit']
                            ),
                            "suggestion" => "Consider using the same unit for better inventory management and cost comparison"
                        );
                    }
                    
                    http_response_code(201);
                    echo json_encode($response_data);
                } else {
                    http_response_code(500);
                    echo json_encode(array(
                        "status" => false,
                        "message" => "Failed to create stock",
                        "method" => "POST"
                    ));
                }
            }
            
        } catch(PDOException $exception) {
            http_response_code(500);
            echo json_encode(array(
                "status" => false,
                "message" => "Database operation failed",
                "method" => "POST",
                "error" => $exception->getMessage()
            ));
        }
        
    } else {
        http_response_code(400);
        echo json_encode(array(
            "status" => false,
            "message" => "Incomplete data. Required fields: userId, product_name, quantity, quantity_unit, purchase_bill_amount, purchase_unit_amount, store_id",
            "method" => "POST",
            "allowed_quantity_units" => array('pieces', 'kg', 'grams', 'liters', 'ml', 'meters', 'cm', 'boxes', 'packs', 'tons', 'pounds', 'ounces'),
            "field_descriptions" => array(
                "purchase_bill_amount" => "Total amount on your purchase bill/invoice",
                "purchase_unit_amount" => "Cost per single unit (per kg, per piece, per liter, etc.) - what you paid",
                "selling_unit_price" => "Optional - Price per unit you plan to sell to customers",
                "profit_calculation" => "Profit per unit = selling_unit_price - purchase_unit_amount"
            ),
            "example_requests" => array(
                "rice_example" => array(
                    "userId" => 1,
                    "product_name" => "Basmati Rice",
                    "quantity" => 50,
                    "quantity_unit" => "kg",
                    "purchase_bill_amount" => 2500.00,
                    "purchase_unit_amount" => 50.00,
                    "selling_unit_price" => 65.00,
                    "store_id" => 1,
                    "note" => "Bill total: $2500 (50kg × $50/kg), Selling at $65/kg = $15 profit per kg (30%)"
                ),
                "eggs_example" => array(
                    "userId" => 1,
                    "product_name" => "Fresh Eggs",
                    "quantity" => 144,
                    "quantity_unit" => "pieces",
                    "purchase_bill_amount" => 720.00,
                    "purchase_unit_amount" => 5.00,
                    "selling_unit_price" => 7.00,
                    "store_id" => 1,
                    "note" => "Bill total: $720 (144pcs × $5/pc), Selling at $7/pc = $2 profit per piece (40%)"
                ),
                "milk_example" => array(
                    "userId" => 1,
                    "product_name" => "Fresh Milk",
                    "quantity" => 20,
                    "quantity_unit" => "liters",
                    "purchase_bill_amount" => 1200.00,
                    "purchase_unit_amount" => 60.00,
                    "store_id" => 1,
                    "note" => "Bill total: $1200 (20L × $60/L), No selling price set yet"
                )
            )
        ));
    }
}

// Call the function
createStock($db);
?>