<?php
/**
 * Suppliers Management API
 * Full CRUD operations for suppliers
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

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'get_all':
            get_all_suppliers($conn);
            break;
            
        case 'get_supplier':
            get_supplier($conn);
            break;
            
        case 'create_supplier':
            if ($method === 'POST') {
                create_supplier($conn);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'update_supplier':
            if ($method === 'POST') {
                update_supplier($conn);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'delete_supplier':
            if ($method === 'POST') {
                delete_supplier($conn);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            }
            break;
            
        case 'update_status':
            if ($method === 'POST') {
                update_supplier_status($conn);
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

// Get all suppliers
function get_all_suppliers($conn) {
    $query = "SELECT supplier_id, supplier_name, contact_person, email, phone, address, city, country, status, created_at 
              FROM suppliers 
              ORDER BY created_at DESC";
    
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        // If table doesn't exist, return empty array
        if (strpos(mysqli_error($conn), "doesn't exist") !== false) {
            echo json_encode(['success' => true, 'suppliers' => []]);
            return;
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch suppliers: ' . mysqli_error($conn)]);
        return;
    }
    
    $suppliers = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $suppliers[] = $row;
    }
    
    echo json_encode(['success' => true, 'suppliers' => $suppliers]);
}

// Get single supplier
function get_supplier($conn) {
    $supplier_id = intval($_GET['supplier_id'] ?? 0);
    
    if ($supplier_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid supplier ID']);
        return;
    }
    
    $stmt = mysqli_prepare($conn, "SELECT * FROM suppliers WHERE supplier_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $supplier_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($supplier = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => true, 'supplier' => $supplier]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Supplier not found']);
    }
    
    mysqli_stmt_close($stmt);
}

// Create new supplier
function create_supplier($conn) {
    $supplier_name = trim($_POST['supplier_name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');
    
    if (empty($supplier_name) || empty($contact_person) || empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Supplier name, contact person, and email are required']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid email format']);
        return;
    }
    
    // Check if email exists
    $stmt = mysqli_prepare($conn, "SELECT supplier_id FROM suppliers WHERE email = ?");
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
    
    $stmt = mysqli_prepare($conn, "INSERT INTO suppliers (supplier_name, contact_person, email, phone, address, city, country, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
    mysqli_stmt_bind_param($stmt, 'sssssss', $supplier_name, $contact_person, $email, $phone, $address, $city, $country);
    
    if (mysqli_stmt_execute($stmt)) {
        $supplier_id = mysqli_insert_id($conn);
        echo json_encode([
            'success' => true,
            'message' => 'Supplier created successfully',
            'supplier_id' => $supplier_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create supplier: ' . mysqli_error($conn)]);
    }
    
    mysqli_stmt_close($stmt);
}

// Update supplier
function update_supplier($conn) {
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    $supplier_name = trim($_POST['supplier_name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');
    
    if ($supplier_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid supplier ID']);
        return;
    }
    
    if (empty($supplier_name) || empty($contact_person) || empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Supplier name, contact person, and email are required']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid email format']);
        return;
    }
    
    // Check if email exists for another supplier
    $stmt = mysqli_prepare($conn, "SELECT supplier_id FROM suppliers WHERE email = ? AND supplier_id != ?");
    mysqli_stmt_bind_param($stmt, 'si', $email, $supplier_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        mysqli_stmt_close($stmt);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email already exists']);
        return;
    }
    mysqli_stmt_close($stmt);
    
    $stmt = mysqli_prepare($conn, "UPDATE suppliers SET supplier_name = ?, contact_person = ?, email = ?, phone = ?, address = ?, city = ?, country = ? WHERE supplier_id = ?");
    mysqli_stmt_bind_param($stmt, 'sssssssi', $supplier_name, $contact_person, $email, $phone, $address, $city, $country, $supplier_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Supplier updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update supplier: ' . mysqli_error($conn)]);
    }
    
    mysqli_stmt_close($stmt);
}

// Delete supplier
function delete_supplier($conn) {
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    
    if ($supplier_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid supplier ID']);
        return;
    }
    
    $stmt = mysqli_prepare($conn, "DELETE FROM suppliers WHERE supplier_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $supplier_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Supplier deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete supplier: ' . mysqli_error($conn)]);
    }
    
    mysqli_stmt_close($stmt);
}

// Update supplier status
function update_supplier_status($conn) {
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    
    if ($supplier_id <= 0 || !in_array($status, ['active', 'inactive'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        return;
    }
    
    $stmt = mysqli_prepare($conn, "UPDATE suppliers SET status = ? WHERE supplier_id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $status, $supplier_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update status: ' . mysqli_error($conn)]);
    }
    
    mysqli_stmt_close($stmt);
}
?>

