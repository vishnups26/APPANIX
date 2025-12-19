<?php
// filepath: /home/anix-sam/Desktop/work/stock_management/backend/src/api/sales/create_sales.php
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
// CREATE SALE (POST METHOD) - Supports Single & Multiple Products
// ===========================
function createSale($db) {
    // Get posted data
    $data = json_decode(file_get_contents("php://input"));
    
    // Detect if it's single product or multiple products
    $is_multiple_products = isset($data->products) && is_array($data->products);
    
    if ($is_multiple_products) {
        createMultipleProductSale($db, $data);
    } else {
        createSingleProductSale($db, $data);
    }
}

// ===========================
// SINGLE PRODUCT SALE
// ===========================
function createSingleProductSale($db, $data) {
    // Check if all required fields are provided
    if (
        !empty($data->userId) &&
        !empty($data->product_name) &&
        !empty($data->quantity) &&
        isset($data->store_id)
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
        
        // Validate quantity is numeric and positive
        if (!is_numeric($data->quantity) || $data->quantity <= 0) {
            http_response_code(400);
            echo json_encode(array(
                "status" => false,
                "message" => "Quantity must be a positive number",
                "method" => "POST"
            ));
            return;
        }
        
        // Validate sales_type if provided
        $sales_type = isset($data->sales_type) ? strtolower($data->sales_type) : 'offline'; // Default to offline
        if (!in_array($sales_type, ['offline', 'online'])) {
            http_response_code(400);
            echo json_encode(array(
                "status" => false,
                "message" => "Invalid sales_type. Must be 'offline' or 'online'",
                "method" => "POST",
                "valid_types" => ["offline", "online"],
                "default" => "offline"
            ));
            return;
        }
        
        try {
            // Get user and store data
            $user_store_data = validateUserAndStore($db, $data->userId, $data->store_id);
            if (!$user_store_data['valid']) {
                echo json_encode($user_store_data['response']);
                return;
            }
            
            $user_data = $user_store_data['user_data'];
            $store_data = $user_store_data['store_data'];
            
            // Process single product
            $product_result = processSingleProduct($db, $data, $user_data, $store_data);
            if (!$product_result['valid']) {
                echo json_encode($product_result['response']);
                return;
            }
            
            // Create sale
            $sale_result = createSaleTransaction($db, [$product_result['sale_data']], $user_data, $store_data, $data);
            echo json_encode($sale_result);
            
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
            "message" => "Incomplete data. Required fields: userId, product_name, quantity, store_id",
            "method" => "POST",
            "single_product_example" => array(
                "userId" => 1,
                "product_name" => "Premium Basmati Rice",
                "quantity" => 10,
                "store_id" => 1,
                "sales_type" => "offline", // NEW FIELD
                "selling_price_per_unit" => 65.00,
                "notes" => "Single product sale"
            ),
            "multiple_products_example" => array(
                "userId" => 1,
                "store_id" => 1,
                "customer_name" => "John Doe",
                "sales_type" => "online", // NEW FIELD
                "notes" => "Multiple items purchase",
                "products" => array(
                    array(
                        "product_name" => "Premium Basmati Rice",
                        "quantity" => 5,
                        "selling_price_per_unit" => 65.00
                    ),
                    array(
                        "product_name" => "Fresh Eggs",
                        "quantity" => 12
                    ),
                    array(
                        "product_name" => "Organic Milk",
                        "quantity" => 2,
                        "selling_price_per_unit" => 85.00
                    )
                )
            ),
            "sales_type_info" => array(
                "valid_types" => ["offline", "online"],
                "default" => "offline",
                "description" => array(
                    "offline" => "In-store, cash, or direct sales",
                    "online" => "E-commerce, website, or app-based sales"
                )
            )
        ));
    }
}

// ===========================
// MULTIPLE PRODUCTS SALE
// ===========================
function createMultipleProductSale($db, $data) {
    // Check if all required fields are provided
    if (
        !empty($data->userId) &&
        isset($data->store_id) &&
        !empty($data->products) &&
        is_array($data->products)
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
        
        // Validate products array
        if (count($data->products) == 0) {
            http_response_code(400);
            echo json_encode(array(
                "status" => false,
                "message" => "Products array cannot be empty",
                "method" => "POST"
            ));
            return;
        }
        
        // Validate sales_type if provided
        $sales_type = isset($data->sales_type) ? strtolower($data->sales_type) : 'offline'; // Default to offline
        if (!in_array($sales_type, ['offline', 'online'])) {
            http_response_code(400);
            echo json_encode(array(
                "status" => false,
                "message" => "Invalid sales_type. Must be 'offline' or 'online'",
                "method" => "POST",
                "valid_types" => ["offline", "online"],
                "default" => "offline"
            ));
            return;
        }
        
        try {
            // Get user and store data
            $user_store_data = validateUserAndStore($db, $data->userId, $data->store_id);
            if (!$user_store_data['valid']) {
                echo json_encode($user_store_data['response']);
                return;
            }
            
            $user_data = $user_store_data['user_data'];
            $store_data = $user_store_data['store_data'];
            
            // Validate and process all products
            $validated_products = array();
            $validation_errors = array();
            
            foreach ($data->products as $index => $product) {
                $product_result = processMultipleProduct($db, $product, $index, $data, $user_data, $store_data);
                
                if ($product_result['valid']) {
                    $validated_products[] = $product_result['sale_data'];
                } else {
                    $validation_errors = array_merge($validation_errors, $product_result['errors']);
                }
            }
            
            // If there are validation errors, return them
            if (!empty($validation_errors)) {
                http_response_code(400);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Validation failed for some products",
                    "method" => "POST",
                    "validation_errors" => $validation_errors,
                    "products_validated" => count($validated_products),
                    "products_failed" => count($validation_errors)
                ));
                return;
            }
            
            // Create bulk sale transaction
            $sale_result = createSaleTransaction($db, $validated_products, $user_data, $store_data, $data);
            echo json_encode($sale_result);
            
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
            "message" => "Incomplete data for multiple products. Required fields: userId, store_id, products[]",
            "method" => "POST",
            "multiple_products_example" => array(
                "userId" => 1,
                "store_id" => 1,
                "customer_name" => "John Doe",
                "sales_type" => "online", // NEW FIELD
                "notes" => "Bulk purchase order",
                "products" => array(
                    array(
                        "product_name" => "Premium Basmati Rice",
                        "quantity" => 5,
                        "selling_price_per_unit" => 65.00
                    ),
                    array(
                        "product_name" => "Fresh Eggs",
                        "quantity" => 24
                    )
                )
            ),
            "sales_type_info" => array(
                "valid_types" => ["offline", "online"],
                "default" => "offline",
                "description" => array(
                    "offline" => "In-store, cash, or direct sales",
                    "online" => "E-commerce, website, or app-based sales"
                )
            )
        ));
    }
}

// ===========================
// HELPER FUNCTIONS
// ===========================
function validateUserAndStore($db, $userId, $storeId) {
    // Check if user exists
    $user_check_query = "SELECT id, username, userRole FROM `users` WHERE id = ?";
    $user_check_stmt = $db->prepare($user_check_query);
    $user_check_stmt->bindParam(1, $userId);
    $user_check_stmt->execute();
    
    if ($user_check_stmt->rowCount() == 0) {
        return array(
            'valid' => false,
            'response' => array(
                "status" => false,
                "message" => "User not found. Invalid userId.",
                "method" => "POST"
            )
        );
    }
    
    $user_data = $user_check_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if store exists and belongs to user
    $store_check_query = "SELECT store_id, storename, userId FROM `stores` WHERE store_id = ?";
    $store_check_stmt = $db->prepare($store_check_query);
    $store_check_stmt->bindParam(1, $storeId);
    $store_check_stmt->execute();
    
    if ($store_check_stmt->rowCount() == 0) {
        return array(
            'valid' => false,
            'response' => array(
                "status" => false,
                "message" => "Store not found. Invalid store_id.",
                "method" => "POST"
            )
        );
    }
    
    $store_data = $store_check_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verify store ownership
    if ($store_data['userId'] != $userId) {
        return array(
            'valid' => false,
            'response' => array(
                "status" => false,
                "message" => "Access denied. You can only create sales for your own stores.",
                "method" => "POST"
            )
        );
    }
    
    return array(
        'valid' => true,
        'user_data' => $user_data,
        'store_data' => $store_data
    );
}

function processSingleProduct($db, $data, $user_data, $store_data) {
    // Check if product exists in stock
    $stock_check_query = "SELECT stock_id, quantity, quantity_unit, purchase_unit_amount, selling_unit_price 
                         FROM `stocks` 
                         WHERE product_name = ? AND store_id = ? AND user_id = ?";
    $stock_check_stmt = $db->prepare($stock_check_query);
    $stock_check_stmt->bindParam(1, $data->product_name);
    $stock_check_stmt->bindParam(2, $data->store_id);
    $stock_check_stmt->bindParam(3, $data->userId);
    $stock_check_stmt->execute();
    
    if ($stock_check_stmt->rowCount() == 0) {
        return array(
            'valid' => false,
            'response' => array(
                "status" => false,
                "message" => "Product not found in stock",
                "method" => "POST",
                "error_details" => array(
                    "product_name" => $data->product_name,
                    "store_id" => $data->store_id,
                    "store_name" => $store_data['storename']
                ),
                "suggestion" => "Add this product to stock first before creating a sale"
            )
        );
    }
    
    $stock_data = $stock_check_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check sufficient quantity
    if ($stock_data['quantity'] < $data->quantity) {
        return array(
            'valid' => false,
            'response' => array(
                "status" => false,
                "message" => "Insufficient stock quantity",
                "method" => "POST",
                "error_details" => array(
                    "product_name" => $data->product_name,
                    "requested_quantity" => (float)$data->quantity,
                    "available_quantity" => (float)$stock_data['quantity'],
                    "shortage" => (float)($data->quantity - $stock_data['quantity']),
                    "unit" => $stock_data['quantity_unit']
                ),
                "suggestion" => "Reduce quantity to " . $stock_data['quantity'] . " " . $stock_data['quantity_unit'] . " or restock the product"
            )
        );
    }
    
    // Validate or get selling price
    $selling_price_per_unit = null;
    if (isset($data->selling_price_per_unit) && is_numeric($data->selling_price_per_unit)) {
        $selling_price_per_unit = $data->selling_price_per_unit;
    } elseif ($stock_data['selling_unit_price']) {
        $selling_price_per_unit = $stock_data['selling_unit_price'];
    } else {
        return array(
            'valid' => false,
            'response' => array(
                "status" => false,
                "message" => "Selling price not set for this product and not provided in sale",
                "method" => "POST",
                "error_details" => array(
                    "product_name" => $data->product_name,
                    "cost_per_unit" => (float)$stock_data['purchase_unit_amount'],
                    "unit" => $stock_data['quantity_unit']
                ),
                "suggestion" => "Either set selling_unit_price in stock or provide selling_price_per_unit in sale data"
            )
        );
    }
    
    // Calculate sale metrics
    $total_sale_amount = round($selling_price_per_unit * $data->quantity, 2);
    $cost_per_unit = $stock_data['purchase_unit_amount'];
    $total_cost = round($cost_per_unit * $data->quantity, 2);
    $profit_per_unit = round($selling_price_per_unit - $cost_per_unit, 4);
    $total_profit = round($profit_per_unit * $data->quantity, 2);
    $profit_margin = $cost_per_unit > 0 ? round(($profit_per_unit / $cost_per_unit) * 100, 2) : 0;
    
    return array(
        'valid' => true,
        'sale_data' => array(
            'stock_data' => $stock_data,
            'product_name' => $data->product_name,
            'quantity' => $data->quantity,
            'selling_price_per_unit' => $selling_price_per_unit,
            'total_sale_amount' => $total_sale_amount,
            'cost_per_unit' => $cost_per_unit,
            'total_cost' => $total_cost,
            'profit_per_unit' => $profit_per_unit,
            'total_profit' => $total_profit,
            'profit_margin' => $profit_margin
        )
    );
}

function processMultipleProduct($db, $product, $index, $data, $user_data, $store_data) {
    $errors = array();
    
    // Validate required fields for each product
    if (empty($product->product_name) || empty($product->quantity)) {
        $errors[] = "Product at index $index: missing product_name or quantity";
        return array('valid' => false, 'errors' => $errors);
    }
    
    if (!is_numeric($product->quantity) || $product->quantity <= 0) {
        $errors[] = "Product '$product->product_name': quantity must be a positive number";
        return array('valid' => false, 'errors' => $errors);
    }
    
    // Create a mock single product data object
    $single_product_data = (object) array(
        'userId' => $data->userId,
        'store_id' => $data->store_id,
        'product_name' => $product->product_name,
        'quantity' => $product->quantity,
        'selling_price_per_unit' => isset($product->selling_price_per_unit) ? $product->selling_price_per_unit : null
    );
    
    $result = processSingleProduct($db, $single_product_data, $user_data, $store_data);
    
    if (!$result['valid']) {
        // Extract error message for multiple products context
        $error_msg = $result['response']['message'];
        $errors[] = "Product '$product->product_name': $error_msg";
        return array('valid' => false, 'errors' => $errors);
    }
    
    return array('valid' => true, 'sale_data' => $result['sale_data']);
}

function createSaleTransaction($db, $validated_products, $user_data, $store_data, $original_data) {
    // Create sales table with sales_type column
    $create_sales_table_query = "CREATE TABLE IF NOT EXISTS `sales` (
        sale_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        store_id INT NOT NULL,
        stock_id INT NOT NULL,
        product_name VARCHAR(255) NOT NULL,
        quantity_sold DECIMAL(10, 3) NOT NULL,
        quantity_unit VARCHAR(20) NOT NULL,
        selling_price_per_unit DECIMAL(10, 4) NOT NULL,
        total_sale_amount DECIMAL(12, 2) NOT NULL,
        cost_per_unit DECIMAL(10, 4) NOT NULL,
        total_cost DECIMAL(12, 2) NOT NULL,
        profit_per_unit DECIMAL(10, 4) NOT NULL,
        total_profit DECIMAL(12, 2) NOT NULL,
        profit_margin DECIMAL(5, 2) NOT NULL,
        sales_type ENUM('offline', 'online') DEFAULT 'offline',
        sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        transaction_id VARCHAR(50) DEFAULT NULL,
        customer_name VARCHAR(255) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (store_id) REFERENCES stores(store_id) ON DELETE CASCADE,
        FOREIGN KEY (stock_id) REFERENCES stocks(stock_id) ON DELETE CASCADE,
        INDEX idx_sale_date (sale_date),
        INDEX idx_transaction (transaction_id),
        INDEX idx_sales_type (sales_type),
        INDEX idx_product_store (product_name, store_id, user_id)
    )";
    
    $create_sales_stmt = $db->prepare($create_sales_table_query);
    $create_sales_stmt->execute();
    
    // Generate transaction ID with sales type prefix
    $sales_type = isset($original_data->sales_type) ? strtolower($original_data->sales_type) : 'offline';
    $type_prefix = $sales_type === 'online' ? 'ON' : 'OFF';
    $transaction_id = 'TXN_' . $type_prefix . '_' . $user_data['id'] . '_' . time() . '_' . rand(1000, 9999);
    
    $customer_name = isset($original_data->customer_name) ? $original_data->customer_name : null;
    $notes = isset($original_data->notes) ? $original_data->notes : null;
    
    // Determine sale type for messaging
    $is_single_product = count($validated_products) === 1;
    $product_type = $is_single_product ? "single" : "multiple";
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        $sale_records = array();
        $total_sale_amount = 0;
        $total_cost = 0;
        $total_profit = 0;
        
        foreach ($validated_products as $product_data) {
            // Insert sale record with sales_type
            $insert_sale_query = "INSERT INTO `sales` 
                (user_id, store_id, stock_id, product_name, quantity_sold, quantity_unit, 
                 selling_price_per_unit, total_sale_amount, cost_per_unit, total_cost, 
                 profit_per_unit, total_profit, profit_margin, sales_type, transaction_id, customer_name, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $insert_sale_stmt = $db->prepare($insert_sale_query);
            
            $insert_sale_stmt->bindParam(1, $original_data->userId);
            $insert_sale_stmt->bindParam(2, $original_data->store_id);
            $insert_sale_stmt->bindParam(3, $product_data['stock_data']['stock_id']);
            $insert_sale_stmt->bindParam(4, $product_data['product_name']);
            $insert_sale_stmt->bindParam(5, $product_data['quantity']);
            $insert_sale_stmt->bindParam(6, $product_data['stock_data']['quantity_unit']);
            $insert_sale_stmt->bindParam(7, $product_data['selling_price_per_unit']);
            $insert_sale_stmt->bindParam(8, $product_data['total_sale_amount']);
            $insert_sale_stmt->bindParam(9, $product_data['cost_per_unit']);
            $insert_sale_stmt->bindParam(10, $product_data['total_cost']);
            $insert_sale_stmt->bindParam(11, $product_data['profit_per_unit']);
            $insert_sale_stmt->bindParam(12, $product_data['total_profit']);
            $insert_sale_stmt->bindParam(13, $product_data['profit_margin']);
            $insert_sale_stmt->bindParam(14, $sales_type); // NEW: Sales type
            $insert_sale_stmt->bindParam(15, $transaction_id);
            $insert_sale_stmt->bindParam(16, $customer_name);
            $insert_sale_stmt->bindParam(17, $notes);
            
            $insert_sale_stmt->execute();
            $sale_id = $db->lastInsertId();
            
            // Update stock quantity
            $new_stock_quantity = $product_data['stock_data']['quantity'] - $product_data['quantity'];
            $update_stock_query = "UPDATE `stocks` SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE stock_id = ?";
            $update_stock_stmt = $db->prepare($update_stock_query);
            $update_stock_stmt->bindParam(1, $new_stock_quantity);
            $update_stock_stmt->bindParam(2, $product_data['stock_data']['stock_id']);
            $update_stock_stmt->execute();
            
            // Add to totals
            $total_sale_amount += $product_data['total_sale_amount'];
            $total_cost += $product_data['total_cost'];
            $total_profit += $product_data['total_profit'];
            
            // Enhanced stock status with sales type consideration
            $stock_status = "adequate";
            $stock_warning = null;
            
            if ($new_stock_quantity <= 0) {
                $stock_status = "out_of_stock";
                $stock_warning = "Product is out of stock - immediate restock required";
            } elseif ($new_stock_quantity <= 5) {
                $stock_status = "critical_low";
                $stock_warning = "Critical stock level - urgent restock needed";
            } elseif ($new_stock_quantity <= 10) {
                $stock_status = "low_stock";
                $stock_warning = $sales_type === 'online' ? "Low stock for online sales - consider restocking" : "Low stock - consider restocking";
            }
            
            // Store sale record for response
            $sale_records[] = array(
                "sale_id" => (int)$sale_id,
                "product_name" => $product_data['product_name'],
                "quantity_sold" => (float)$product_data['quantity'],
                "quantity_unit" => $product_data['stock_data']['quantity_unit'],
                "unit_display" => $product_data['quantity'] . " " . $product_data['stock_data']['quantity_unit'],
                "selling_price_per_unit" => (float)$product_data['selling_price_per_unit'],
                "total_sale_amount" => (float)$product_data['total_sale_amount'],
                "total_cost" => (float)$product_data['total_cost'],
                "total_profit" => (float)$product_data['total_profit'],
                "profit_margin" => (float)$product_data['profit_margin'],
                "profit_margin_display" => $product_data['profit_margin'] . "%",
                "remaining_stock" => (float)$new_stock_quantity,
                "stock_status" => $stock_status,
                "stock_warning" => $stock_warning,
                "sales_channel" => $sales_type === 'online' ? "Online Sale" : "Offline Sale"
            );
        }
        
        // Commit transaction
        $db->commit();
        
        // Calculate overall profit margin
        $overall_profit_margin = $total_cost > 0 ? round(($total_profit / $total_cost) * 100, 2) : 0;
        
        // Get total sales count for user (by sales type)
        $sales_count_query = "SELECT 
            COUNT(DISTINCT transaction_id) as total_transactions,
            COUNT(DISTINCT CASE WHEN sales_type = 'offline' THEN transaction_id END) as offline_transactions,
            COUNT(DISTINCT CASE WHEN sales_type = 'online' THEN transaction_id END) as online_transactions
            FROM `sales` WHERE user_id = ?";
        $sales_count_stmt = $db->prepare($sales_count_query);
        $sales_count_stmt->bindParam(1, $original_data->userId);
        $sales_count_stmt->execute();
        $transaction_stats = $sales_count_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Enhanced success message
        $sales_channel_display = $sales_type === 'online' ? 'Online' : 'Offline';
        $success_message = $is_single_product 
            ? "Single product {$sales_channel_display} sale created successfully" 
            : "Multiple products {$sales_channel_display} sale created successfully";
        
        http_response_code(201);
        return array(
            "status" => true,
            "message" => $success_message,
            "method" => "POST",
            "action" => "sale_created",
            "sale_type" => $product_type, // single or multiple
            "sales_channel" => $sales_type, // offline or online
            "transaction_data" => array(
                "transaction_id" => $transaction_id,
                "user_id" => (int)$original_data->userId,
                "username" => $user_data['username'],
                "store_id" => (int)$original_data->store_id,
                "store_name" => $store_data['storename'],
                "customer_name" => $customer_name,
                "sales_type" => $sales_type, // NEW: offline or online
                "sales_channel_display" => $sales_channel_display, // NEW: Offline or Online
                "products_count" => count($validated_products),
                "sale_records" => $sale_records,
                
                // ENHANCED TRANSACTION SUMMARY
                "transaction_summary" => array(
                    "total_items" => count($validated_products),
                    "total_sale_amount" => (float)$total_sale_amount,
                    "total_cost" => (float)$total_cost,
                    "total_profit" => (float)$total_profit,
                    "overall_profit_margin" => (float)$overall_profit_margin,
                    "overall_profit_margin_display" => $overall_profit_margin . "%",
                    "average_profit_per_item" => count($validated_products) > 0 ? round($total_profit / count($validated_products), 2) : 0,
                    "sales_channel" => $sales_channel_display,
                    "channel_benefits" => $sales_type === 'online' 
                        ? array("wider_reach", "24x7_availability", "digital_tracking") 
                        : array("personal_interaction", "immediate_delivery", "cash_transactions")
                ),
                
                // STOCK ALERTS with sales type context
                "stock_alerts" => array_values(array_filter($sale_records, function($record) {
                    return $record['stock_status'] !== 'adequate';
                })),
                
                "notes" => $notes,
                "sale_date" => date('Y-m-d H:i:s'),
                "sale_timestamp" => time()
            ),
            "statistics" => array(
                "total_user_transactions" => (int)$transaction_stats['total_transactions'],
                "offline_transactions" => (int)$transaction_stats['offline_transactions'],
                "online_transactions" => (int)$transaction_stats['online_transactions'],
                "current_transaction_type" => $product_type,
                "current_sales_channel" => $sales_type
            )
        );
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        throw $e;
    }
}

// Call the function
createSale($db);
?>