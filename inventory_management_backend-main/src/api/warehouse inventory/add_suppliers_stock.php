<?php
// filepath: /home/anix-sam/Desktop/work/stock_management/backend/src/api/warehouse_inventory/add_suppliers_stock.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database connection
require_once __DIR__ . '/../../../db/connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array("status" => false, "message" => "Only POST method allowed"));
    exit();
}

$database = new Database();
$db = $database->getConnection();

if ($db == null) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Database connection failed"]);
    exit();
}

function createStock($db) {
    $data = json_decode(file_get_contents("php://input"));

    // === 1. CREATE TABLES IF NOT EXISTS ===
    try {
        // BRANDS
        $db->exec("
            CREATE TABLE IF NOT EXISTS brands (
                id INT AUTO_INCREMENT PRIMARY KEY,
                brand_name VARCHAR(100) NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");

        // PRODUCTS
        $db->exec("
            CREATE TABLE IF NOT EXISTS products (
                product_id INT AUTO_INCREMENT PRIMARY KEY,
                product_name VARCHAR(255) NOT NULL,
                brand_id INT NOT NULL,
                category_id INT NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_product (product_name, brand_id, category_id),
                FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
            )
        ");

        // SUPPLIERS STOCKS
        $db->exec("
            CREATE TABLE IF NOT EXISTS suppliers_stocks (
                stock_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                product_id INT NOT NULL,
                brand_id INT NOT NULL,
                warehouse_id INT NOT NULL,
                category_id INT NOT NULL,
                quantity DECIMAL(10,3) NOT NULL,
                quantity_unit VARCHAR(20) NOT NULL,
                purchase_bill_amount DECIMAL(10,2) NOT NULL,
                purchase_unit_amount DECIMAL(10,4) NOT NULL,
                selling_unit_price DECIMAL(10,4) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_stock (product_id, warehouse_id, quantity_unit),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (warehouse_id) REFERENCES warehouse(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
                FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
            )
        ");
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => false, "message" => "Error creating tables", "error" => $e->getMessage()]);
        return;
    }

    // === 2. VALIDATIONS ===
    if (empty($data->userId) || empty($data->warehouse_id)) {
        http_response_code(400);
        echo json_encode(["status" => false, "message" => "Required: userId, warehouse_id"]);
        return;
    }
    if (!is_numeric($data->userId) || !is_numeric($data->warehouse_id)) {
        http_response_code(400);
        echo json_encode(["status" => false, "message" => "userId and warehouse_id must be numeric"]);
        return;
    }
    if (!isset($data->quantity) || !is_numeric($data->quantity) || $data->quantity <= 0) {
        http_response_code(400);
        echo json_encode(["status" => false, "message" => "Quantity is required and must be > 0"]);
        return;
    }
    if (!isset($data->purchase_bill_amount) || !is_numeric($data->purchase_bill_amount) || $data->purchase_bill_amount < 0) {
        http_response_code(400);
        echo json_encode(["status" => false, "message" => "purchase_bill_amount is required and cannot be negative"]);
        return;
    }
    if (!isset($data->purchase_unit_amount) || !is_numeric($data->purchase_unit_amount) || $data->purchase_unit_amount < 0) {
        http_response_code(400);
        echo json_encode(["status" => false, "message" => "purchase_unit_amount is required and cannot be negative"]);
        return;
    }

    // Optional selling price
    $selling_unit_price = null;
    if (isset($data->selling_unit_price)) {
        if (!is_numeric($data->selling_unit_price) || $data->selling_unit_price < 0) {
            http_response_code(400);
            echo json_encode(["status" => false, "message" => "selling_unit_price must be numeric and >= 0"]);
            return;
        }
        $selling_unit_price = $data->selling_unit_price;
    }

    $allowed_units = ['pieces', 'kg', 'grams', 'liters', 'ml', 'meters', 'cm', 'boxes', 'packs', 'tons', 'pounds', 'ounces'];
    if (empty($data->quantity_unit) || !in_array(strtolower($data->quantity_unit), $allowed_units)) {
        http_response_code(400);
        echo json_encode(["status" => false, "message" => "Invalid quantity unit", "allowed" => $allowed_units]);
        return;
    }

    // === 3. REMAINING API LOGIC ===
    try {
        // Verify user
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$data->userId]);
        if ($stmt->rowCount() == 0) {
            http_response_code(404);
            echo json_encode(["status" => false, "message" => "Invalid userId"]);
            return;
        }

        // Verify warehouse
        $stmt = $db->prepare("SELECT id, userId FROM warehouse WHERE id = ?");
        $stmt->execute([$data->warehouse_id]);
        if ($stmt->rowCount() == 0) {
            http_response_code(404);
            echo json_encode(["status" => false, "message" => "Invalid warehouse id"]);
            return;
        }
        $warehouse = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($warehouse['userId'] != $data->userId) {
            http_response_code(403);
            echo json_encode(["status" => false, "message" => "You can only add stock to your own warehouse"]);
            return;
        }

        // Handle brand
        if (!empty($data->brand_id)) {
            $stmt = $db->prepare("SELECT id FROM brands WHERE id = ?");
            $stmt->execute([$data->brand_id]);
            if ($stmt->rowCount() == 0) {
                http_response_code(404);
                echo json_encode(["status" => false, "message" => "Invalid brand id"]);
                return;
            }
        } else {
            if (empty($data->brand_name)) {
                http_response_code(400);
                echo json_encode(["status" => false, "message" => "brand_name is required when brand_id is not provided"]);
                return;
            }
            $stmt = $db->prepare("SELECT id FROM brands WHERE brand_name = ?");
            $stmt->execute([$data->brand_name]);
            if ($stmt->rowCount() == 0) {
                $insertBrand = $db->prepare("INSERT INTO brands (brand_name) VALUES (?)");
                $insertBrand->execute([$data->brand_name]);
                $data->brand_id = $db->lastInsertId();
            } else {
                $brand = $stmt->fetch(PDO::FETCH_ASSOC);
                $data->brand_id = $brand['id'];
            }
        }

        // Handle product
        if (!empty($data->product_id)) {
            $stmt = $db->prepare("SELECT product_id, category_id FROM products WHERE product_id = ?");
            $stmt->execute([$data->product_id]);
            if ($stmt->rowCount() == 0) {
                http_response_code(404);
                echo json_encode(["status" => false, "message" => "Invalid product_id"]);
                return;
            }
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            $data->category_id = $product['category_id'];
        } else {
            if (empty($data->product_name) || empty($data->category_id)) {
                http_response_code(400);
                echo json_encode(["status" => false, "message" => "product_name and category_id are required when product_id is not provided"]);
                return;
            }
            $stmt = $db->prepare("SELECT product_id FROM products WHERE product_name = ? AND brand_id = ? AND category_id = ?");
            $stmt->execute([$data->product_name, $data->brand_id, $data->category_id]);
            if ($stmt->rowCount() == 0) {
                $insertProduct = $db->prepare("INSERT INTO products (product_name, brand_id, category_id) VALUES (?, ?, ?)");
                $insertProduct->execute([$data->product_name, $data->brand_id, $data->category_id]);
                $data->product_id = $db->lastInsertId();
            } else {
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                $data->product_id = $product['product_id'];
            }
        }

        // Check stock
        $stmt = $db->prepare("SELECT * FROM suppliers_stocks WHERE product_id = ? AND warehouse_id = ? AND user_id = ? AND quantity_unit = ?");
        $stmt->execute([$data->product_id, $data->warehouse_id, $data->userId, $data->quantity_unit]);

        $calculateProfit = function($unitCost, $sellingPrice) {
            if ($sellingPrice === null) return [null, null];
            $profit = round($sellingPrice - $unitCost, 4);
            $margin = round(($profit / $sellingPrice) * 100, 2);
            return [$profit, $margin];
        };

        if ($stmt->rowCount() > 0) {
            $stock = $stmt->fetch(PDO::FETCH_ASSOC);
            $new_quantity = $stock['quantity'] + $data->quantity;
            $new_bill = $stock['purchase_bill_amount'] + $data->purchase_bill_amount;
            $new_unit_amount = round($new_bill / $new_quantity, 4);

            [$profit_per_unit, $profit_margin] = $calculateProfit($new_unit_amount, $selling_unit_price);

            $update = $db->prepare("UPDATE suppliers_stocks SET quantity = ?, purchase_bill_amount = ?, purchase_unit_amount = ?, selling_unit_price = ? WHERE stock_id = ?");
            $update->execute([$new_quantity, $new_bill, $new_unit_amount, $selling_unit_price, $stock['stock_id']]);

            http_response_code(200);
            echo json_encode([
                "status" => true,
                "message" => "Stock updated successfully",
                "stock_id" => $stock['stock_id'],
                "new_quantity" => $new_quantity,
                "new_unit_cost" => $new_unit_amount,
                "profit_per_unit" => $profit_per_unit,
                "profit_margin" => $profit_margin
            ]);
        } else {
            [$profit_per_unit, $profit_margin] = $calculateProfit($data->purchase_unit_amount, $selling_unit_price);

            $insert = $db->prepare("
                INSERT INTO suppliers_stocks (user_id, warehouse_id, category_id, product_id, brand_id, quantity, quantity_unit, purchase_bill_amount, purchase_unit_amount, selling_unit_price)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insert->execute([$data->userId, $data->warehouse_id, $data->category_id, $data->product_id, $data->brand_id, $data->quantity, $data->quantity_unit, $data->purchase_bill_amount, $data->purchase_unit_amount, $selling_unit_price]);

            http_response_code(201);
            echo json_encode([
                "status" => true,
                "message" => "Stock created successfully",
                "stock_id" => $db->lastInsertId(),
                "profit_per_unit" => $profit_per_unit,
                "profit_margin" => $profit_margin
            ]);
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => false, "message" => "Server error", "error" => $e->getMessage()]);
    }
}


createStock($db);
