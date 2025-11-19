<?php
/**
 * User Management API
 * Full CRUD operations for users, admins, and managers
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../php/config.php';
include __DIR__ . '/../php/conn.php';

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Verify the users table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
if (mysqli_num_rows($table_check) == 0) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Users table does not exist in the database']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'get_all':
            get_all_users($conn);
            break;
            
        case 'get_user':
            get_user($conn);
            break;
            
        case 'create_user':
            if ($method === 'POST') {
                create_user($conn);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'update_user':
            if ($method === 'POST') {
                update_user($conn);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'delete_user':
            if ($method === 'POST') {
                delete_user($conn);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'update_status':
            if ($method === 'POST') {
                update_user_status($conn);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'update_role':
            if ($method === 'POST') {
                update_user_role($conn);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'reset_password':
            if ($method === 'POST') {
                reset_user_password($conn);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'lock_account':
            if ($method === 'POST') {
                lock_user_account($conn);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'unlock_account':
            if ($method === 'POST') {
                unlock_user_account($conn);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// Get all users
function get_all_users($conn) {
    // Check if status column exists
    $checkStatus = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'status'");
    $hasStatus = mysqli_num_rows($checkStatus) > 0;
    
    if ($hasStatus) {
        $query = "SELECT user_id, full_name, email, username, employee_id, status, role, created_at 
                  FROM users 
                  ORDER BY created_at DESC";
    } else {
        $query = "SELECT user_id, full_name, email, username, employee_id, 'active' as status, role, created_at 
                  FROM users 
                  ORDER BY created_at DESC";
    }
    
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        http_response_code(500);
        $error_msg = mysqli_error($conn);
        error_log("Failed to fetch users: " . $error_msg);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch users: ' . $error_msg]);
        return;
    }
    
    $users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Ensure all fields have proper defaults
        $row['role'] = $row['role'] ?? 'user';
        $row['status'] = $row['status'] ?? 'active';
        $row['employee_id'] = $row['employee_id'] ?? null;
        $row['username'] = $row['username'] ?? null;
        $users[] = $row;
    }
    
    echo json_encode(['success' => true, 'users' => $users], JSON_UNESCAPED_UNICODE);
}

// Get single user
function get_user($conn) {
    $user_id = intval($_GET['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
        return;
    }
    
    // Check if status column exists
    $checkStatus = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'status'");
    $hasStatus = mysqli_num_rows($checkStatus) > 0;
    
    if ($hasStatus) {
        $stmt = mysqli_prepare($conn, "SELECT user_id, full_name, email, username, employee_id, status, role, created_at FROM users WHERE user_id = ?");
    } else {
        $stmt = mysqli_prepare($conn, "SELECT user_id, full_name, email, username, employee_id, 'active' as status, role, created_at FROM users WHERE user_id = ?");
    }
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($user = mysqli_fetch_assoc($result)) {
        $user['role'] = $user['role'] ?? 'user';
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
    }
    
    mysqli_stmt_close($stmt);
}

// Create new user
function create_user($conn) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    // Auto-generate default password - no password input required
    $password = 'user123'; // Default password for all new users
    $role = trim($_POST['role'] ?? 'user');
    
    if (empty($full_name) || empty($email) || empty($username)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Full name, email, and username are required']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid email format']);
        return;
    }
    
    if (!in_array($role, ['admin', 'user', 'supplier'])) {
        $role = 'user';
    }
    
    // Check if email exists
    $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        mysqli_stmt_close($stmt);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email already exists']);
        return;
    }
    mysqli_stmt_close($stmt);
    
    // Check if username exists
    $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE username = ?");
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        mysqli_stmt_close($stmt);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Username already exists']);
        return;
    }
    mysqli_stmt_close($stmt);
    
    $employee_id = generate_employee_id($conn);
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Check and create must_change_password column if it doesn't exist
    $checkMustChange = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'must_change_password'");
    if (mysqli_num_rows($checkMustChange) === 0) {
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) DEFAULT 1 AFTER status");
    }
    
    // Check if status column exists
    $checkStatus = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'status'");
    $hasStatus = mysqli_num_rows($checkStatus) > 0;
    
    // Try with status, must_change_password, and role columns
    if ($hasStatus) {
        $stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, email, username, password_hash, employee_id, status, must_change_password, role, created_at) VALUES (?, ?, ?, ?, ?, 'active', 1, ?, NOW())");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssssss', $full_name, $email, $username, $hashed_password, $employee_id, $role);
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'User created successfully with default password (user123)',
                    'employee_id' => $employee_id,
                    'default_password' => 'user123'
                ]);
                mysqli_stmt_close($stmt);
                return;
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Fallback without status column
    $stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, email, username, password_hash, employee_id, must_change_password, role, created_at) VALUES (?, ?, ?, ?, ?, 1, ?, NOW())");
    mysqli_stmt_bind_param($stmt, 'ssssss', $full_name, $email, $username, $hashed_password, $employee_id, $role);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            'success' => true,
            'message' => 'User created successfully with default password (user123)',
            'employee_id' => $employee_id,
            'default_password' => 'user123'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create user: ' . mysqli_error($conn)]);
    }
    
    mysqli_stmt_close($stmt);
}

// Update user
function update_user($conn) {
    $user_id = intval($_POST['user_id'] ?? 0);
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
        return;
    }
    
    if (empty($full_name) || empty($email) || empty($username)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'All fields are required']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid email format']);
        return;
    }
    
    // Check if email exists for another user
    $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    mysqli_stmt_bind_param($stmt, 'si', $email, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        mysqli_stmt_close($stmt);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email already exists']);
        return;
    }
    mysqli_stmt_close($stmt);
    
    // Check if username exists for another user
    $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE username = ? AND user_id != ?");
    mysqli_stmt_bind_param($stmt, 'si', $username, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        mysqli_stmt_close($stmt);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Username already exists']);
        return;
    }
    mysqli_stmt_close($stmt);
    
    $stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, username = ? WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'sssi', $full_name, $email, $username, $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update user: ' . mysqli_error($conn)]);
    }
    
    mysqli_stmt_close($stmt);
}

// Delete user
function delete_user($conn) {
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
        return;
    }
    
    // Prevent deleting yourself (if session is available)
    // You can add session check here if needed
    
    $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete user: ' . mysqli_error($conn)]);
    }
    
    mysqli_stmt_close($stmt);
}

// Update user status
function update_user_status($conn) {
    $user_id = intval($_POST['user_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    
    // Support all status values from database: 'active', 'inactive', 'offline', 'locked'
    if ($user_id <= 0 || !in_array($status, ['active', 'inactive', 'offline', 'locked'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        return;
    }
    
    // Check if status column exists
    $checkStatus = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'status'");
    if (mysqli_num_rows($checkStatus) == 0) {
        // Add status column if it doesn't exist
        $addStatus = "ALTER TABLE users ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' AFTER role";
        if (!mysqli_query($conn, $addStatus)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Status column does not exist and could not be created']);
            return;
        }
    }
    
    $stmt = mysqli_prepare($conn, "UPDATE users SET status = ? WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $status, $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update status: ' . mysqli_error($conn)]);
    }
    
    mysqli_stmt_close($stmt);
}

// Update user role
function update_user_role($conn) {
    $user_id = intval($_POST['user_id'] ?? 0);
    $role = trim($_POST['role'] ?? '');
    
    if ($user_id <= 0 || !in_array($role, ['admin', 'user', 'supplier'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        return;
    }
    
    // Check if role column exists
    $stmt = mysqli_prepare($conn, "UPDATE users SET role = ? WHERE user_id = ?");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'si', $role, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Role updated successfully']);
            mysqli_stmt_close($stmt);
            return;
        }
        mysqli_stmt_close($stmt);
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update role']);
}

// Reset user password
function reset_user_password($conn) {
    $user_id = intval($_POST['user_id'] ?? 0);
    $new_password = $_POST['new_password'] ?? '';
    
    if ($user_id <= 0 || empty($new_password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        return;
    }
    
    if (strlen($new_password) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']);
        return;
    }
    
    // Check and create must_change_password column if it doesn't exist
    $checkMustChange = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'must_change_password'");
    if (mysqli_num_rows($checkMustChange) === 0) {
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) DEFAULT 0 AFTER status");
    }
    
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    // Clear must_change_password flag when password is changed
    $stmt = mysqli_prepare($conn, "UPDATE users SET password_hash = ?, must_change_password = 0 WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $hashed_password, $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to reset password: ' . mysqli_error($conn)]);
    }
    
    mysqli_stmt_close($stmt);
}

// Generate unique employee ID
function generate_employee_id($conn) {
    $result = mysqli_query($conn, "SELECT MAX(CAST(SUBSTRING(employee_id, 5) AS UNSIGNED)) AS max_num FROM users WHERE employee_id LIKE 'EMP-%'");
    $row = mysqli_fetch_assoc($result);
    $next_num = ($row['max_num'] ?? 0) + 1;
    return 'EMP-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
}

// Lock user account
function lock_user_account($conn) {
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
        return;
    }
    
    // Check if status column exists and update enum if needed
    $checkStatus = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'status'");
    if (mysqli_num_rows($checkStatus) > 0) {
        $columnInfo = mysqli_fetch_assoc($checkStatus);
        $enumValues = $columnInfo['Type'] ?? '';
        if (strpos($enumValues, 'locked') === false) {
            // Update enum to include locked
            $alterQuery = "ALTER TABLE users MODIFY COLUMN status ENUM('active', 'inactive', 'offline', 'locked') DEFAULT 'active'";
            mysqli_query($conn, $alterQuery);
        }
    }
    
    $stmt = mysqli_prepare($conn, "UPDATE users SET status = 'locked' WHERE user_id = ?");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Account locked successfully']);
            mysqli_stmt_close($stmt);
            return;
        }
        mysqli_stmt_close($stmt);
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to lock account: ' . mysqli_error($conn)]);
}

// Unlock user account
function unlock_user_account($conn) {
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
        return;
    }
    
    // Set status to 'offline' when unlocking (user needs to login to become 'active')
    $stmt = mysqli_prepare($conn, "UPDATE users SET status = 'offline' WHERE user_id = ? AND status = 'locked'");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $affected = mysqli_stmt_affected_rows($stmt);
            if ($affected > 0) {
                echo json_encode(['success' => true, 'message' => 'Account unlocked successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Account is not locked']);
            }
            mysqli_stmt_close($stmt);
            return;
        }
        mysqli_stmt_close($stmt);
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to unlock account: ' . mysqli_error($conn)]);
}
?>
