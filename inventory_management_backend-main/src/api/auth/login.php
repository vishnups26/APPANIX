<?php
// filepath: /home/anix-sam/Desktop/work/stock_management/backend/src/api/auth/login.php
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array("status" => false, "message" => "Only POST method allowed"));
    exit();
}

// Get posted data
$data = json_decode(file_get_contents("php://input"));

// Check if required fields are provided
if (!empty($data->email) && !empty($data->password)) {

    $valid_roles = array(1, 2, 3, 4, 5);
    
    // Define role names for ENUM
    $role_names = array(
        1 => 'admin',
        2 => 'shopkeeper',
        3 => 'worker',
        4 => 'supplier',
        5 => 'normal_user'
    );
    
    // Define role display names
    $role_display_names = array(
        1 => 'Admin',
        2 => 'Shopkeeper',
        3 => 'Worker',
        4 => 'Supplier',
        5 => 'Normal User'
    );
    
    // Validate role
    if (!in_array($data->role, $valid_roles)) {
        http_response_code(400);
        echo json_encode(array(
            "status" => false,
            "message" => "Invalid role. Valid roles are: 1-Admin, 2-Shopkeeper, 3-Worker, 4-Supplier, 5-Normal User"
        ));
        exit();
    }
    
    // Get role name for database
    $user_role = $role_names[$data->role];
    
    // Create database instance
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db != null) {
        try {
            
            // Create sessions table if it doesn't exist
            $create_sessions_table = "CREATE TABLE IF NOT EXISTS `user_sessions` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id VARCHAR(36) UNIQUE NOT NULL,
                user_id INT NOT NULL,
                username VARCHAR(50) NOT NULL,
                userRole ENUM('admin', 'shopkeeper', 'worker', 'supplier', 'normal_user') NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )";
            
            $sessions_stmt = $db->prepare($create_sessions_table);
            $sessions_stmt->execute();
            
            // Find user by username
            $user_query = "SELECT id, username, password, userRole, firstname, lastname, email, is_active FROM `users` WHERE email = ? AND userRole = ?";
            $user_stmt = $db->prepare($user_query);
            $user_stmt->bindParam(1, $data->email);
            $user_stmt->bindParam(2, $user_role);
            $user_stmt->execute();
            
            if ($user_stmt->rowCount() == 1) {
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if user account is active
                if (!$user['is_active']) {
                    http_response_code(403);
                    echo json_encode(array(
                        "status" => false,
                        "message" => "Account is deactivated. Please contact administrator."
                    ));
                    exit();
                }
                
                // Verify password
                if (password_verify($data->password, $user['password'])) {
                    
                    // Generate UUID for session
                    function generateUUID() {
                        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                            mt_rand(0, 0xffff),
                            mt_rand(0, 0x0fff) | 0x4000,
                            mt_rand(0, 0x3fff) | 0x8000,
                            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                        );
                    }
                    
                    $session_id = generateUUID();
                    
                    // Set session expiry (24 hours from now)
                    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    
                    // Clean up expired sessions for this user
                    $cleanup_query = "DELETE FROM `user_sessions` WHERE user_id = ? AND (expires_at < NOW() OR is_active = FALSE)";
                    $cleanup_stmt = $db->prepare($cleanup_query);
                    $cleanup_stmt->bindParam(1, $user['id']);
                    $cleanup_stmt->execute();
                    
                    // Insert new session
                    $session_query = "INSERT INTO `user_sessions` (session_id, user_id, username, userRole, expires_at) VALUES (?, ?, ?, ?, ?)";
                    $session_stmt = $db->prepare($session_query);
                    $session_stmt->bindParam(1, $session_id);
                    $session_stmt->bindParam(2, $user['id']);
                    $session_stmt->bindParam(3, $user['username']);
                    $session_stmt->bindParam(4, $user['userRole']);
                    $session_stmt->bindParam(5, $expires_at);
                    
                    if ($session_stmt->execute()) {
                        
                        // Get active sessions count
                        $active_sessions_query = "SELECT COUNT(*) as active_sessions FROM `user_sessions` WHERE user_id = ? AND is_active = TRUE AND expires_at > NOW()";
                        $active_stmt = $db->prepare($active_sessions_query);
                        $active_stmt->bindParam(1, $user['id']);
                        $active_stmt->execute();
                        $active_result = $active_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Update user last login (optional)
                        $update_login_query = "UPDATE `users` SET updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                        $update_stmt = $db->prepare($update_login_query);
                        $update_stmt->bindParam(1, $user['id']);
                        $update_stmt->execute();
                        
                        http_response_code(200);
                        echo json_encode(array(
                            "status" => true,
                            "message" => "Login successful",
                            "session_data" => array(
                                "session_id" => $session_id,
                                "expires_at" => $expires_at,
                                "expires_in_hours" => 24
                            ),
                            "user_data" => array(
                                "id" => $user['id'],
                                "username" => $user['username'],
                                "userRole" => $user['userRole'],
                                "firstname" => $user['firstname'],
                                "lastname" => $user['lastname'],
                                "email" => $user['email']
                            ),
                            "session_info" => array(
                                "active_sessions" => $active_result['active_sessions'],
                                "login_time" => date('Y-m-d H:i:s')
                            )
                        ));
                        
                    } else {
                        http_response_code(500);
                        echo json_encode(array(
                            "status" => false,
                            "message" => "Failed to create session"
                        ));
                    }
                    
                } else {
                    http_response_code(401);
                    echo json_encode(array(
                        "status" => false,
                        "message" => "Invalid password"
                    ));
                }
                
            } else {
                http_response_code(401);
                echo json_encode(array(
                    "status" => false,
                    "message" => "Username not found"
                ));
            }
            
        } catch(PDOException $exception) {
            http_response_code(500);
            echo json_encode(array(
                "status" => false,
                "message" => "Database operation failed",
                "error" => $exception->getMessage()
            ));
        }
        
    } else {
        http_response_code(500);
        echo json_encode(array(
            "status" => false,
            "message" => "Database connection failed"
        ));
    }
    
} else {
    http_response_code(400);
    echo json_encode(array(
        "status" => false,
        "message" => "Email and password are required",
        "example_request" => array(
            "email" => "john_admin@gmail.com",
            "password" => "securePassword123"
        )
    ));
}
?>