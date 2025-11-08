<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

// CORS headers (for development)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database configuration
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'inventory_system_db';

// Connect to database
$mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}
$mysqli->set_charset('utf8mb4');

// Get the action parameter
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_all':
            get_all_users($mysqli);
            break;
            
        case 'create_user':
            create_user($mysqli);
            break;
            
        case 'update_status':
            update_user_status($mysqli);
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

$mysqli->close();

// Get all users
function get_all_users($mysqli): void {
    $query = "SELECT user_id, full_name, email, username, employee_id, status, role, created_at 
              FROM users 
              ORDER BY created_at DESC";
    
    $result = $mysqli->query($query);
    
    if (!$result) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch users']);
        return;
    }
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        // Default role to 'user' if not set
        if (!isset($row['role']) || empty($row['role'])) {
            $row['role'] = 'user';
        }
        $users[] = $row;
    }
    
    echo json_encode(['success' => true, 'users' => $users]);
}

// Create new user
function create_user($mysqli): void {
    // Validate required fields
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? 'user');
    
    if (empty($full_name) || empty($email) || empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'All fields are required']);
        return;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid email format']);
        return;
    }
    
    // Validate password length
    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']);
        return;
    }
    
    // Validate role
    if (!in_array($role, ['admin', 'manager', 'user'])) {
        $role = 'user';
    }
    
    // Check if email already exists
    $stmt = $mysqli->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email already exists']);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    // Check if username already exists
    $stmt = $mysqli->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Username already exists']);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    // Generate employee ID
    $employee_id = generate_employee_id($mysqli);
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user (with role if column exists)
    $stmt = $mysqli->prepare("INSERT INTO users (full_name, email, username, password, employee_id, status, role, created_at) 
                              VALUES (?, ?, ?, ?, ?, 'active', ?, NOW())");
    $stmt->bind_param('ssssss', $full_name, $email, $username, $hashed_password, $employee_id, $role);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'User created successfully',
            'employee_id' => $employee_id
        ]);
    } else {
        // Fallback: try without role column
        $stmt->close();
        $stmt = $mysqli->prepare("INSERT INTO users (full_name, email, username, password, employee_id, status, created_at) 
                                  VALUES (?, ?, ?, ?, ?, 'active', NOW())");
        $stmt->bind_param('sssss', $full_name, $email, $username, $hashed_password, $employee_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'User created successfully',
                'employee_id' => $employee_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to create user']);
        }
    }
    
    $stmt->close();
}

// Update user status
function update_user_status($mysqli): void {
    $user_id = intval($_POST['user_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    
    if ($user_id <= 0 || !in_array($status, ['active', 'inactive'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        return;
    }
    
    $stmt = $mysqli->prepare("UPDATE users SET status = ? WHERE user_id = ?");
    $stmt->bind_param('si', $status, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update status']);
    }
    
    $stmt->close();
}

// Generate unique employee ID
function generate_employee_id($mysqli): string {
    // Get the highest existing employee ID
    $result = $mysqli->query("SELECT MAX(CAST(SUBSTRING(employee_id, 5) AS UNSIGNED)) AS max_num FROM users WHERE employee_id LIKE 'EMP-%'");
    $row = $result->fetch_assoc();
    $next_num = ($row['max_num'] ?? 0) + 1;
    
    // Format as EMP-0001, EMP-0002, etc.
    return 'EMP-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
}
?>
