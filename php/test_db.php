<?php
// Simple database test script
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conn.php';

try {
    // Test connection
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Check if table exists
    $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'medicines'");
    if (mysqli_num_rows($tableCheck) == 0) {
        throw new Exception('Table "medicines" does not exist');
    }
    
    // Get table structure
    $result = mysqli_query($conn, "DESCRIBE medicines");
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful',
        'table_exists' => true,
        'columns' => $columns
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>

