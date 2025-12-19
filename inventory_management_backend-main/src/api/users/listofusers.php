<?php
// filepath: /home/anix-sam/Desktop/work/stock_management/backend/src/api/users/listofusers.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Include database connection
require_once __DIR__ . '/../../../db/connect.php';

// Allow both GET and POST requests
$request_method = $_SERVER['REQUEST_METHOD'];

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get role parameter from different request methods
if ($request_method === 'POST') {
    $createdBy = isset($_GET['createdBy']) ? $_GET['createdBy'] : null;
    $data = json_decode(file_get_contents("php://input"));
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
        // Define valid roles
        $valid_roles = array('admin', 'shopkeeper', 'worker', 'supplier', 'normal_user');

        $roles = $data->roles ?? null;
        
        // Define role display names
        $role_display_names = array(
            'admin' => 'Administrator',
            'shopkeeper' => 'Shopkeeper',
            'worker' => 'Worker',
            'supplier' => 'Supplier',
            'normal_user' => 'Normal User'
        );
        // Check if role is provided and valid
        if ($roles !== null) {
            
            // Check if provided roles are valid

            foreach ($roles as $r) {
                if (!in_array($r, $valid_roles)) {
                    http_response_code(400);
                    echo json_encode(array(
                        "status" => false,
                        "message" => "Invalid role: $r. Valid roles are: " . implode(", ", $valid_roles)
                    ));
                    exit();
                }
            }

            $placeholders = implode(',', array_fill(0, count($roles), '?'));

            $users_query = "SELECT id, username, email, firstname, lastname, address, userRole, is_active, created_at 
                            FROM `users` 
                            WHERE userRole IN ($placeholders)
                            AND created_by = ?
                            ORDER BY userRole, created_at DESC";

            $users_stmt = $db->prepare($users_query);

            // Bind roles
            $i = 1;
            foreach ($roles as $r) {
                $users_stmt->bindValue($i, $r, PDO::PARAM_STR);
                $i++;
            }

            // Bind createdBy (last one)
            $users_stmt->bindValue($i, $createdBy, PDO::PARAM_INT);

            $users_stmt->execute();
            $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

            
           // Initialize stats
            $total_users = count($users);
            $active_users = 0;
            $inactive_users = 0;

            // Count active/inactive users from fetched array
            foreach ($users as $u) {
                if ($u['is_active']) {
                    $active_users++;
                } else {
                    $inactive_users++;
                }
            }
            
            http_response_code(200);
            echo json_encode(array(
                "status" => true,
                "message" => "Users retrieved successfully",
                "filter" => array(
                    "roles" => $roles, // multiple roles
                    "roles_display" => array_map(function($r) use ($role_display_names) {
                        return $role_display_names[$r] ?? $r;
                }, $roles),),
                    "createdBy" => $createdBy,
                "statistics" => array(
                    "total_users" => $total_users,
                    "active_users" => $active_users,
                    "inactive_users" => $inactive_users
                ),
                "users" => $users
            ));
            
        } else {
            
            // No role specified - return all users grouped by role
            $all_users_query = "SELECT id, username, email, firstname, lastname, address, userRole, is_active, created_at FROM `users` ORDER BY userRole, created_at DESC";
            $all_users_stmt = $db->prepare($all_users_query);
            $all_users_stmt->execute();
            
            $all_users = $all_users_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group users by role
            $users_by_role = array();
            $role_counts = array();
            
            foreach ($all_users as $user) {
                $user_role = $user['userRole'];
                
                if (!isset($users_by_role[$user_role])) {
                    $users_by_role[$user_role] = array();
                    $role_counts[$user_role] = array('total' => 0, 'active' => 0, 'inactive' => 0);
                }
                
                $users_by_role[$user_role][] = $user;
                $role_counts[$user_role]['total']++;
                
                if ($user['is_active']) {
                    $role_counts[$user_role]['active']++;
                } else {
                    $role_counts[$user_role]['inactive']++;
                }
            }
            
            // Get overall statistics
            $total_query = "SELECT 
                COUNT(*) as total_users,
                COUNT(CASE WHEN is_active = TRUE THEN 1 END) as total_active,
                COUNT(CASE WHEN is_active = FALSE THEN 1 END) as total_inactive
                FROM `users`";
            $total_stmt = $db->prepare($total_query);
            $total_stmt->execute();
            $total_result = $total_stmt->fetch(PDO::FETCH_ASSOC);
            
            http_response_code(200);
            echo json_encode(array(
                "status" => true,
                "message" => "All users retrieved successfully",
                "filter" => "all_roles",
                "overall_statistics" => array(
                    "total_users" => $total_result['total_users'],
                    "total_active" => $total_result['total_active'],
                    "total_inactive" => $total_result['total_inactive']
                ),
                "role_statistics" => $role_counts,
                "users_by_role" => $users_by_role,
                "available_roles" => $valid_roles
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
?>