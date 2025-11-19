<?php
/**
 * Change Password API
 * Allows logged-in users to change their own password
 * Clears must_change_password flag when password is changed
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

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

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (!$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'User ID not found in session']);
    exit;
}

// Validate inputs
if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'All password fields are required']);
    exit;
}

if (strlen($newPassword) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'New password must be at least 8 characters']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'New password and confirmation do not match']);
    exit;
}

// Get current user password
$stmt = mysqli_prepare($conn, "SELECT password_hash, password FROM users WHERE user_id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

// Verify current password
$storedPassword = $user['password_hash'] ?? $user['password'] ?? '';
$passwordValid = false;

if (strlen($storedPassword) === 60 && in_array(substr($storedPassword, 0, 4), ['$2y$', '$2a$', '$2b$'])) {
    // Password is hashed
    $passwordValid = password_verify($currentPassword, $storedPassword);
} else {
    // Plain text password (legacy)
    $passwordValid = ($storedPassword === $currentPassword);
}

if (!$passwordValid) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
    exit;
}

// Check and create must_change_password column if it doesn't exist
$checkMustChange = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'must_change_password'");
if (mysqli_num_rows($checkMustChange) === 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) DEFAULT 0 AFTER status");
}

// Hash new password and update
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
$updateStmt = mysqli_prepare($conn, "UPDATE users SET password_hash = ?, must_change_password = 0 WHERE user_id = ?");

if (!$updateStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to prepare update statement']);
    exit;
}

mysqli_stmt_bind_param($updateStmt, "si", $hashedPassword, $userId);

if (mysqli_stmt_execute($updateStmt)) {
    // Clear flag from localStorage
    echo json_encode([
        'success' => true,
        'message' => 'Password changed successfully. You will need to login again.',
        'must_change_password' => false
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update password: ' . mysqli_error($conn)]);
}

mysqli_stmt_close($updateStmt);
?>

