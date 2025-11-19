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

        // Map supplier fields correctly (suppliers table uses contact_person and address)
        // The archived_suppliers table uses 'contact' and 'location' fields
        $contact = isset($supplier['contact_person']) ? $supplier['contact_person'] : (isset($supplier['contact']) ? $supplier['contact'] : null);
        $location = isset($supplier['address']) ? $supplier['address'] : (isset($supplier['location']) ? $supplier['location'] : null);
        // Note: suppliers table may not have website/notes, so these will be null
        $website = isset($supplier['website']) ? $supplier['website'] : null;
        $notes = isset($supplier['notes']) ? $supplier['notes'] : null;
        $email = isset($supplier['email']) ? $supplier['email'] : null;
        $phone = isset($supplier['phone']) ? $supplier['phone'] : null;
        
        error_log("Archiving supplier - ID: {$supplier['id']}, Name: {$supplier['name']}, Contact: " . ($contact ?? 'NULL') . ", Location: " . ($location ?? 'NULL'));
        
        mysqli_stmt_bind_param($archiveStmt, 'isssssssss',
            $supplier['id'],
            $supplier['name'],
            $contact,
            $email,
            $phone,
            $location,
            $website,
            $notes,
            $archived_by,
            $reason
        );

        if (!mysqli_stmt_execute($archiveStmt)) {
            throw new Exception('Failed to archive supplier: ' . mysqli_stmt_error($archiveStmt));
        }

        mysqli_stmt_close($archiveStmt);

        // Handle foreign key constraints before deleting
        // 1. Update batches to set supplier_id to NULL (batches table has FK without ON DELETE CASCADE)
        $updateBatchesSql = "UPDATE batches SET supplier_id = NULL WHERE supplier_id = ?";
        $updateBatchesStmt = mysqli_prepare($conn, $updateBatchesSql);
        if ($updateBatchesStmt) {
            mysqli_stmt_bind_param($updateBatchesStmt, 'i', $supplier_id);
            if (!mysqli_stmt_execute($updateBatchesStmt)) {
                error_log("Warning: Failed to update batches supplier_id: " . mysqli_stmt_error($updateBatchesStmt));
                // Continue anyway, as this is not critical
            }
            mysqli_stmt_close($updateBatchesStmt);
        }

        // 2. Check if there are orders with this supplier (orders has ON DELETE RESTRICT)
        // We'll update the supplier_id to NULL in orders as well, or we could prevent archiving
        // For now, let's update orders to set supplier_id to NULL
        $updateOrdersSql = "UPDATE orders SET supplier_id = NULL WHERE supplier_id = ?";
        $updateOrdersStmt = mysqli_prepare($conn, $updateOrdersSql);
        if ($updateOrdersStmt) {
            mysqli_stmt_bind_param($updateOrdersStmt, 'i', $supplier_id);
            if (!mysqli_stmt_execute($updateOrdersStmt)) {
                error_log("Warning: Failed to update orders supplier_id: " . mysqli_stmt_error($updateOrdersStmt));
                // Continue anyway
            }
            mysqli_stmt_close($updateOrdersStmt);
        }

        // 3. Delete supplier_medicines relationships (junction table)
        // This table may have ON DELETE RESTRICT or no CASCADE, so we need to delete manually
        $deleteSupplierMedicinesSql = "DELETE FROM supplier_medicines WHERE supplier_id = ?";
        $deleteSupplierMedicinesStmt = mysqli_prepare($conn, $deleteSupplierMedicinesSql);
        if (!$deleteSupplierMedicinesStmt) {
            throw new Exception('Database error preparing supplier_medicines deletion: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($deleteSupplierMedicinesStmt, 'i', $supplier_id);
        if (!mysqli_stmt_execute($deleteSupplierMedicinesStmt)) {
            $error = mysqli_stmt_error($deleteSupplierMedicinesStmt);
            mysqli_stmt_close($deleteSupplierMedicinesStmt);
            throw new Exception('Failed to delete supplier_medicines relationships: ' . $error);
        }
        mysqli_stmt_close($deleteSupplierMedicinesStmt);

        // Delete supplier from active table
        $deleteSql = "DELETE FROM suppliers WHERE id = ?";
        $deleteStmt = mysqli_prepare($conn, $deleteSql);
        if (!$deleteStmt) {
            throw new Exception('Database error during deletion: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($deleteStmt, 'i', $supplier_id);

        if (!mysqli_stmt_execute($deleteStmt)) {
            $error = mysqli_stmt_error($deleteStmt);
            mysqli_stmt_close($deleteStmt);
            throw new Exception('Failed to delete supplier: ' . $error);
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

