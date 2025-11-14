<?php
// Dashboard API
// Provides data for dashboard including metrics, low stock alerts, recent activities, and calendar events

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
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once __DIR__ . '/conn.php';

try {
    $action = $_GET['action'] ?? 'metrics';

    switch ($action) {
        case 'metrics':
            getDashboardMetrics($conn);
            break;
        case 'low-stock':
            getLowStockData($conn);
            break;
        case 'expiration-alerts':
            getExpirationAlerts($conn);
            break;
        case 'activities':
            getRecentActivities($conn);
            break;
        case 'calendar':
            getCalendarEvents($conn);
            break;
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
            break;
    }
} catch (Exception $e) {
    error_log("Error in get_dashboard.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function getDashboardMetrics($conn) {
    // Total Medicines
    $totalMedicinesSql = "SELECT COUNT(*) as total FROM medicines";
    $totalMedicinesResult = mysqli_query($conn, $totalMedicinesSql);
    $totalMedicines = 0;
    if ($totalMedicinesResult) {
        $row = mysqli_fetch_assoc($totalMedicinesResult);
        $totalMedicines = (int)($row['total'] ?? 0);
    }

    // Pending Orders (if orders table exists, otherwise use low-stock as proxy)
    // For now, we'll use medicines with low stock as "pending" items
    $pendingOrdersSql = "SELECT COUNT(*) as total FROM medicines WHERE status = 'low-stock'";
    $pendingOrdersResult = mysqli_query($conn, $pendingOrdersSql);
    $pendingOrders = 0;
    if ($pendingOrdersResult) {
        $row = mysqli_fetch_assoc($pendingOrdersResult);
        $pendingOrders = (int)($row['total'] ?? 0);
    }

    // Total Inventory Value
    $totalValueSql = "SELECT SUM(quantity * price) as total_value FROM medicines";
    $totalValueResult = mysqli_query($conn, $totalValueSql);
    $totalValue = 0;
    if ($totalValueResult) {
        $row = mysqli_fetch_assoc($totalValueResult);
        $totalValue = (float)($row['total_value'] ?? 0);
    }

    // Low Stock Count
    $lowStockSql = "SELECT COUNT(*) as total FROM medicines WHERE status IN ('low-stock', 'out-of-stock')";
    $lowStockResult = mysqli_query($conn, $lowStockSql);
    $lowStockCount = 0;
    if ($lowStockResult) {
        $row = mysqli_fetch_assoc($lowStockResult);
        $lowStockCount = (int)($row['total'] ?? 0);
    }

    // Low Stock Percentage
    $lowStockPercentage = $totalMedicines > 0 ? round(($lowStockCount / $totalMedicines) * 100) : 0;

    echo json_encode([
        'success' => true,
        'data' => [
            'totalMedicines' => $totalMedicines,
            'pendingOrders' => $pendingOrders,
            'totalValue' => number_format($totalValue, 2),
            'lowStockCount' => $lowStockCount,
            'lowStockPercentage' => $lowStockPercentage
        ]
    ], JSON_UNESCAPED_UNICODE);
}

function getLowStockData($conn) {
    $sql = "SELECT id, name, quantity, reorder_level, status 
            FROM medicines 
            WHERE status IN ('low-stock', 'out-of-stock')
            ORDER BY quantity ASC
            LIMIT 10";

    $result = mysqli_query($conn, $sql);
    $medicines = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $medicines[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'quantity' => (int)$row['quantity'],
                'reorderLevel' => (int)$row['reorder_level'],
                'status' => $row['status']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $medicines
    ], JSON_UNESCAPED_UNICODE);
}

function getRecentActivities($conn) {
    // Get recent medicine additions and updates
    $sql = "SELECT 
                id,
                name,
                'medicine_added' as activity_type,
                created_at as activity_date,
                'Medicine added to inventory' as description
            FROM medicines
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            
            UNION ALL
            
            SELECT 
                id,
                name,
                'medicine_updated' as activity_type,
                updated_at as activity_date,
                'Medicine updated' as description
            FROM medicines
            WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND updated_at != created_at
            
            ORDER BY activity_date DESC
            LIMIT 10";

    $result = mysqli_query($conn, $sql);
    $activities = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $activities[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'type' => $row['activity_type'],
                'date' => $row['activity_date'],
                'description' => $row['description']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $activities
    ], JSON_UNESCAPED_UNICODE);
}

function getExpirationAlerts($conn) {
    $currentDate = date('Y-m-d');
    $thirtyDaysFromNow = date('Y-m-d', strtotime('+30 days'));
    
    // Get expired medicines count
    $expiredSql = "SELECT COUNT(*) as count FROM medicines 
                   WHERE expiration_date IS NOT NULL 
                   AND expiration_date < ?";
    $expiredStmt = mysqli_prepare($conn, $expiredSql);
    mysqli_stmt_bind_param($expiredStmt, 's', $currentDate);
    mysqli_stmt_execute($expiredStmt);
    $expiredResult = mysqli_stmt_get_result($expiredStmt);
    $expiredCount = 0;
    if ($expiredResult) {
        $row = mysqli_fetch_assoc($expiredResult);
        $expiredCount = (int)($row['count'] ?? 0);
    }
    mysqli_stmt_close($expiredStmt);
    
    // Get expiring soon (within 30 days)
    $expiringSoonSql = "SELECT COUNT(*) as count FROM medicines 
                        WHERE expiration_date IS NOT NULL 
                        AND expiration_date >= ? 
                        AND expiration_date <= ?";
    $expiringSoonStmt = mysqli_prepare($conn, $expiringSoonSql);
    mysqli_stmt_bind_param($expiringSoonStmt, 'ss', $currentDate, $thirtyDaysFromNow);
    mysqli_stmt_execute($expiringSoonStmt);
    $expiringSoonResult = mysqli_stmt_get_result($expiringSoonStmt);
    $expiringSoonCount = 0;
    if ($expiringSoonResult) {
        $row = mysqli_fetch_assoc($expiringSoonResult);
        $expiringSoonCount = (int)($row['count'] ?? 0);
    }
    mysqli_stmt_close($expiringSoonStmt);
    
    // Get expired medicines list (top 5)
    $expiredListSql = "SELECT id, name, expiration_date, batch_number, quantity
                       FROM medicines 
                       WHERE expiration_date IS NOT NULL 
                       AND expiration_date < ?
                       ORDER BY expiration_date ASC
                       LIMIT 5";
    $expiredListStmt = mysqli_prepare($conn, $expiredListSql);
    mysqli_stmt_bind_param($expiredListStmt, 's', $currentDate);
    mysqli_stmt_execute($expiredListStmt);
    $expiredListResult = mysqli_stmt_get_result($expiredListStmt);
    
    $expiredMedicines = [];
    if ($expiredListResult) {
        while ($row = mysqli_fetch_assoc($expiredListResult)) {
            $expiredMedicines[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'expirationDate' => $row['expiration_date'],
                'batchNumber' => $row['batch_number'] ? (int)$row['batch_number'] : null,
                'quantity' => (int)$row['quantity']
            ];
        }
    }
    mysqli_stmt_close($expiredListStmt);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'expiredCount' => $expiredCount,
            'expiringSoonCount' => $expiringSoonCount,
            'totalAlerts' => $expiredCount + $expiringSoonCount,
            'expiredMedicines' => $expiredMedicines
        ]
    ], JSON_UNESCAPED_UNICODE);
}

function getCalendarEvents($conn) {
    // Get medicines with expiration dates in the next 90 days
    $sql = "SELECT 
                id,
                name,
                expiration_date,
                quantity,
                status
            FROM medicines
            WHERE expiration_date IS NOT NULL
                AND expiration_date >= CURDATE()
                AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
            ORDER BY expiration_date ASC";

    $result = mysqli_query($conn, $sql);
    $events = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $status = $row['status'];
            $color = '#22C55E'; // green for in-stock
            
            if ($status === 'expired') {
                $color = '#EF4444'; // red
            } elseif ($status === 'low-stock') {
                $color = '#F59E0B'; // orange
            } elseif ($status === 'out-of-stock') {
                $color = '#6B7280'; // gray
            }

            $events[] = [
                'id' => (int)$row['id'],
                'title' => $row['name'] . ' (Expires)',
                'start' => $row['expiration_date'],
                'color' => $color,
                'extendedProps' => [
                    'medicineId' => (int)$row['id'],
                    'quantity' => (int)$row['quantity'],
                    'status' => $status
                ]
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $events
    ], JSON_UNESCAPED_UNICODE);
}

?>

