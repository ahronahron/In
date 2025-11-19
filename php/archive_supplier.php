<?php
// Archive Supplier API
// Archives a supplier instead of deleting it

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Enhanced CORS headers
$allowed_origins = [
    'http://localhost:3000',
    'http://localhost',
    'http://127.0.0.1:3000',
    'http://127.0.0.1'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Helper function to send JSON response
function sendJsonResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    ob_clean();
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

try {
    require_once __DIR__ . '/conn.php';

    // Check database connection
    if (!isset($conn) || !$conn) {
        sendJsonResponse(false, 'Database connection failed', null, 500);
    }

    // Get supplier ID from POST
    $supplier_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($supplier_id <= 0) {
        error_log("Invalid supplier ID received: " . ($_POST['id'] ?? 'not set'));
        sendJsonResponse(false, 'Invalid supplier ID: ' . ($_POST['id'] ?? 'not provided'), null, 400);
    }

    error_log("Attempting to archive supplier ID: " . $supplier_id);

    // Check if supplier exists
    $checkSql = "SELECT * FROM suppliers WHERE id = ?";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    if (!$checkStmt) {
        $error = mysqli_error($conn);
        error_log("Check prepare error: " . $error);
        sendJsonResponse(false, 'Database error during supplier check: ' . $error, null, 500);
    }

    mysqli_stmt_bind_param($checkStmt, 'i', $supplier_id);
    if (!mysqli_stmt_execute($checkStmt)) {
        $error = mysqli_stmt_error($checkStmt);
        error_log("Check execute error: " . $error);
        mysqli_stmt_close($checkStmt);
        sendJsonResponse(false, 'Database error executing query: ' . $error, null, 500);
    }
    
    $checkResult = mysqli_stmt_get_result($checkStmt);

    if (!$checkResult || mysqli_num_rows($checkResult) === 0) {
        mysqli_stmt_close($checkStmt);
        error_log("Supplier not found in database. ID: " . $supplier_id);
        sendJsonResponse(false, 'Supplier not found with ID: ' . $supplier_id, null, 404);
    }

    $supplier = mysqli_fetch_assoc($checkResult);
    mysqli_stmt_close($checkStmt);

    // Check if archived_suppliers table exists, if not create it
    $tableCheck = "SHOW TABLES LIKE 'archived_suppliers'";
    $tableResult = mysqli_query($conn, $tableCheck);
    if (mysqli_num_rows($tableResult) == 0) {
        // Create archived_suppliers table
        $createTableSql = "CREATE TABLE IF NOT EXISTS archived_suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique archive ID',
            original_id INT UNSIGNED NOT NULL COMMENT 'Original supplier ID before archiving',
            name VARCHAR(255) NOT NULL COMMENT 'Supplier name',
            contact VARCHAR(255) NULL COMMENT 'Contact person',
            email VARCHAR(255) NULL COMMENT 'Email address',
            phone VARCHAR(50) NULL COMMENT 'Phone number',
            location VARCHAR(255) NULL COMMENT 'Location/Address',
            website VARCHAR(255) NULL COMMENT 'Website URL',
            notes TEXT NULL COMMENT 'Additional notes',
            archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When supplier was archived',
            archived_by VARCHAR(255) NULL COMMENT 'User who archived the supplier',
            reason TEXT NULL COMMENT 'Reason for archiving',
            INDEX idx_original_id (original_id),
            INDEX idx_archived_at (archived_at),
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Archived suppliers'";
        
        if (!mysqli_query($conn, $createTableSql)) {
            error_log("Error creating archived_suppliers table: " . mysqli_error($conn));
            sendJsonResponse(false, 'Failed to create archive table', null, 500);
        }
    }

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // Archive supplier
        $archived_by = isset($_POST['archived_by']) ? $_POST['archived_by'] : null;
        $reason = isset($_POST['reason']) ? $_POST['reason'] : 'Supplier archived';

        $archiveSql = "INSERT INTO archived_suppliers 
                        (original_id, name, contact, email, phone, location, website, notes, archived_by, reason)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $archiveStmt = mysqli_prepare($conn, $archiveSql);
        if (!$archiveStmt) {
            throw new Exception('Database error during archiving: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($archiveStmt, 'isssssssss',
            $supplier['id'],
            $supplier['name'],
            $supplier['contact'] ?? null,
            $supplier['email'] ?? null,
            $supplier['phone'] ?? null,
            $supplier['location'] ?? null,
            $supplier['website'] ?? null,
            $supplier['notes'] ?? null,
            $archived_by,
            $reason
        );

        if (!mysqli_stmt_execute($archiveStmt)) {
            throw new Exception('Failed to archive supplier: ' . mysqli_stmt_error($archiveStmt));
        }

        mysqli_stmt_close($archiveStmt);

        // Delete supplier from active table
        $deleteSql = "DELETE FROM suppliers WHERE id = ?";
        $deleteStmt = mysqli_prepare($conn, $deleteSql);
        if (!$deleteStmt) {
            throw new Exception('Database error during deletion: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($deleteStmt, 'i', $supplier_id);

        if (!mysqli_stmt_execute($deleteStmt)) {
            throw new Exception('Failed to delete supplier: ' . mysqli_stmt_error($deleteStmt));
        }

        $affectedRows = mysqli_stmt_affected_rows($deleteStmt);
        mysqli_stmt_close($deleteStmt);

        if ($affectedRows === 0) {
            mysqli_rollback($conn);
            sendJsonResponse(false, 'No supplier was deleted after archiving', null, 404);
        }

        // Commit transaction
        mysqli_commit($conn);

        // Success response
        sendJsonResponse(true, "Supplier '{$supplier['name']}' archived successfully", [
            'id' => $supplier_id,
            'name' => $supplier['name']
        ], 200);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }

} catch (Exception $e) {
    error_log('Exception in archive_supplier.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
} catch (Error $e) {
    error_log('Fatal error in archive_supplier.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Fatal error: ' . $e->getMessage(), ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
} catch (Throwable $e) {
    error_log('Throwable in archive_supplier.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
}

?>

