<?php
/**
 * Get Archives API
 * Retrieves archived items: expired items, cancelled orders, and deleted items
 */

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

header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once __DIR__ . '/conn.php';

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
    if (!isset($conn) || !$conn) {
        sendJsonResponse(false, 'Database connection failed', null, 500);
    }

    $type = isset($_GET['type']) ? $_GET['type'] : 'all'; // all, expired, cancelled, deleted
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    $result = [
        'expired' => [],
        'cancelled' => [],
        'deleted' => []
    ];

    // Get expired items
    if ($type === 'all' || $type === 'expired') {
        $expiredSql = "SELECT 
                        ae.id,
                        ae.original_batch_item_id,
                        ae.batch_id,
                        ae.batch_number,
                        ae.medicine_id,
                        ae.medicine_name,
                        ae.medicine_ndc,
                        ae.quantity,
                        ae.expiration_date,
                        ae.expired_at,
                        ae.archived_at,
                        ae.supplier_id,
                        ae.supplier_name,
                        DATEDIFF(CURDATE(), ae.expiration_date) as days_expired
                    FROM archived_expired_items ae
                    ORDER BY ae.expired_at DESC
                    LIMIT ? OFFSET ?";
        
        $stmt = mysqli_prepare($conn, $expiredSql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
            mysqli_stmt_execute($stmt);
            $expiredResult = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($expiredResult)) {
                $result['expired'][] = $row;
            }
            mysqli_stmt_close($stmt);
        }
    }

    // Get cancelled orders
    if ($type === 'all' || $type === 'cancelled') {
        $cancelledSql = "SELECT 
                            ao.id,
                            ao.original_id,
                            ao.supplier_id,
                            ao.supplier_name,
                            ao.order_date,
                            ao.cancelled_at,
                            ao.cancelled_by,
                            ao.total_amount,
                            ao.notes,
                            ao.cancellation_reason,
                            ao.original_status,
                            (SELECT COUNT(*) FROM archived_order_items WHERE archived_order_id = ao.id) as item_count
                        FROM archived_orders ao
                        ORDER BY ao.cancelled_at DESC
                        LIMIT ? OFFSET ?";
        
        $stmt = mysqli_prepare($conn, $cancelledSql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
            mysqli_stmt_execute($stmt);
            $cancelledResult = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($cancelledResult)) {
                // Get order items
                $itemsSql = "SELECT 
                                aoi.id,
                                aoi.medicine_id,
                                aoi.medicine_name,
                                aoi.quantity,
                                aoi.price
                            FROM archived_order_items aoi
                            WHERE aoi.archived_order_id = ?";
                $itemsStmt = mysqli_prepare($conn, $itemsSql);
                if ($itemsStmt) {
                    mysqli_stmt_bind_param($itemsStmt, 'i', $row['id']);
                    mysqli_stmt_execute($itemsStmt);
                    $itemsResult = mysqli_stmt_get_result($itemsStmt);
                    $row['items'] = [];
                    while ($item = mysqli_fetch_assoc($itemsResult)) {
                        $row['items'][] = $item;
                    }
                    mysqli_stmt_close($itemsStmt);
                }
                $result['cancelled'][] = $row;
            }
            mysqli_stmt_close($stmt);
        }
    }

    // Get deleted medicines
    if ($type === 'all' || $type === 'deleted') {
        $deletedSql = "SELECT 
                        am.id,
                        am.original_id,
                        am.ndc,
                        am.name,
                        am.manufacturer,
                        am.category,
                        am.dosage_form,
                        am.price,
                        am.quantity,
                        am.description,
                        am.deleted_at,
                        am.deleted_by,
                        am.reason
                    FROM archived_medicines am
                    ORDER BY am.deleted_at DESC
                    LIMIT ? OFFSET ?";
        
        $stmt = mysqli_prepare($conn, $deletedSql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
            mysqli_stmt_execute($stmt);
            $deletedResult = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($deletedResult)) {
                $result['deleted'][] = $row;
            }
            mysqli_stmt_close($stmt);
        }
    }

    // Get counts
    $counts = [
        'expired' => 0,
        'cancelled' => 0,
        'deleted' => 0
    ];

    $countSql = "SELECT COUNT(*) as count FROM archived_expired_items";
    $countResult = mysqli_query($conn, $countSql);
    if ($countResult) {
        $counts['expired'] = (int)mysqli_fetch_assoc($countResult)['count'];
    }

    $countSql = "SELECT COUNT(*) as count FROM archived_orders";
    $countResult = mysqli_query($conn, $countSql);
    if ($countResult) {
        $counts['cancelled'] = (int)mysqli_fetch_assoc($countResult)['count'];
    }

    $countSql = "SELECT COUNT(*) as count FROM archived_medicines";
    $countResult = mysqli_query($conn, $countSql);
    if ($countResult) {
        $counts['deleted'] = (int)mysqli_fetch_assoc($countResult)['count'];
    }

    sendJsonResponse(true, 'Archives retrieved successfully', [
        'items' => $result,
        'counts' => $counts
    ], 200);

} catch (Exception $e) {
    error_log('Exception in get_archives.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), null, 500);
} catch (Error $e) {
    error_log('Fatal error in get_archives.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Fatal error: ' . $e->getMessage(), null, 500);
}

?>

