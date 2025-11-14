<?php
declare(strict_types=1);
// Diagnostic / hardened send_reset_email endpoint
// - Logs details to pages/send_reset_email.log
// - Returns generic success to client unless DEBUG=1 (env) or ?debug=1 (local dev)
// - Adjust DB credentials if not default
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');
$LOGFILE = __DIR__ . '/send_reset_email.log';
function log_msg(string $m): void { global $LOGFILE; @file_put_contents($LOGFILE, date('Y-m-d H:i:s') . ' ' . $m . PHP_EOL, FILE_APPEND | LOCK_EX); }

$debugEnv = getenv('DEBUG') === '1';
$debugQuery = (isset($_GET['debug']) && $_GET['debug'] === '1');
$DEBUG = $debugEnv || $debugQuery;

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Accept either 'email' or 'resetEmail'
$rawEmail = $_POST['email'] ?? $_POST['resetEmail'] ?? '';
$email = filter_var(trim((string)$rawEmail), FILTER_VALIDATE_EMAIL);
if (!$email) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid email address']);
    exit;
}

// Update PHPMailer path to match your system
$phpmailerBase = realpath(__DIR__ . '/../PHPMailer/src');
$phpex = $phpmailerBase ? $phpmailerBase . '/Exception.php' : null;
$phpmailer = $phpmailerBase ? $phpmailerBase . '/PHPMailer.php' : null;
$phpsmtp = $phpmailerBase ? $phpmailerBase . '/SMTP.php' : null;

if (!$phpex || !file_exists($phpex) || !file_exists($phpmailer) || !file_exists($phpsmtp)) {
    $err = 'PHPMailer files not found at expected path: ' . (__DIR__ . '/../PHPMailer/src');
    log_msg($err);
    if ($DEBUG) {
        http_response_code(500);
        echo json_encode(['error' => $err]);
    } else {
        echo json_encode(['success' => true, 'message' => 'If your email is registered you will receive instructions.']);
    }
    exit;
}

require_once $phpex;
require_once $phpmailer;
require_once $phpsmtp;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// DB connect — adjust credentials for your setup
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'Inventory_system_db';

$mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    $err = 'DB connect error: ' . $mysqli->connect_error;
    log_msg($err);
    if ($DEBUG) { http_response_code(500); echo json_encode(['error' => $err]); } 
    else { echo json_encode(['success' => true, 'message' => 'If your email is registered you will receive instructions.']); }
    exit;
}
$mysqli->set_charset('utf8mb4');

// Lookup user (active)
$stmt = $mysqli->prepare('SELECT user_id, full_name, email FROM users WHERE email = ? AND status = "active" LIMIT 1');
if (!$stmt) {
    log_msg('Prepare failed (select): ' . $mysqli->error);
    echo json_encode(['success' => true, 'message' => 'If your email is registered you will receive instructions.']);
    exit;
}
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
    // Email not found in database
    if ($DEBUG) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Email not found.']);
    } else {
        // For production, return success=false with a user-friendly message
        echo json_encode(['success' => false, 'error' => 'The email address you entered is not registered.']);
    }
    $mysqli->close();
    exit;
}

// Generate secure random token
$rawToken = bin2hex(random_bytes(32)); // Creates 64-char hex string
$tokenHash = hash('sha256', $rawToken); // Hash for DB storage
$expiresAt = date('Y-m-d H:i:s', time() + 1800); // 30 min expiry

// First, invalidate any existing reset tokens for this user
$invalidateStmt = $mysqli->prepare('UPDATE users SET password_reset_token = NULL, password_reset_expires = NULL WHERE user_id = ?');
if ($invalidateStmt) {
    $invalidateStmt->bind_param('i', $user['user_id']);
    $invalidateStmt->execute();
    $invalidateStmt->close();
}

// Now set the new reset token
$upd = $mysqli->prepare('UPDATE users SET password_reset_token = ?, password_reset_expires = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?');
if (!$upd) {
    log_msg('Prepare failed (update): ' . $mysqli->error);
    echo json_encode(['success' => true, 'message' => 'If your email is registered you will receive instructions.']);
    $mysqli->close();
    exit;
}
$upd->bind_param('ssi', $tokenHash, $expiresAt, $user['user_id']);
if (!$upd->execute()) {
    log_msg('Execute failed (update): ' . $upd->error);
    $upd->close();
    echo json_encode(['success' => true, 'message' => 'If your email is registered you will receive instructions.']);
    $mysqli->close();
    exit;
}
$upd->close();

// Build reset URL (change domain in production)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$resetUrl = sprintf('%s://%s/pages/forgot_password.php?token=%s', $scheme, $host, rawurlencode($rawToken));

// Update Gmail credentials with your actual values
// TODO: Replace with your actual Gmail and App Password
$gmailUser = 'isystem190@gmail.com'; // Replace with your Gmail
$gmailAppPass = 'hvvd bruj giwq jvfs'; // Replace with your App Password

// Send mail
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $gmailUser; 
    $mail->Password = $gmailAppPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($gmailUser, 'Inventory System');
    $mail->addAddress($user['email'], $user['full_name'] ?? '');
    $mail->Subject = 'Password reset instructions';
    $mail->isHTML(true);
    $mail->Body =
        '<div style="max-width: 500px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 40px 32px; border: 1px solid #e1e5e9; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
            <div style="text-align: center; margin-bottom: 32px;">
                <h1 style="font-size: 24px; color: #1e293b; font-weight: 700; margin: 0 0 8px 0; letter-spacing: -0.025em;">Password Reset Request</h1>
                <p style="font-size: 16px; color: #64748b; margin: 0;">Secure your account</p>
            </div>
            
            <div style="background: #f8fafc; border-radius: 8px; padding: 24px; margin-bottom: 24px; border-left: 4px solid #3b82f6;">
                <p style="font-size: 16px; color: #1e293b; margin: 0 0 12px 0; font-weight: 500;">Hello <strong>' . htmlspecialchars($user['full_name'] ?? 'System Administrator') . '</strong>,</p>
                <p style="font-size: 15px; color: #475569; margin: 0 0 16px 0; line-height: 1.5;">Click the link below to reset your password. This link will expire in <strong style="color: #dc2626;">30 minutes</strong> for security purposes.</p>
                
                <div style="text-align: center; margin: 24px 0;">
                    <a href="' . htmlspecialchars($resetUrl) . '" style="display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #3b82f6, #2563eb); color: #ffffff; font-size: 16px; font-weight: 600; border-radius: 8px; text-decoration: none; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); transition: all 0.2s;">Reset Password</a>
                </div>
                
                <p style="font-size: 13px; color: #64748b; margin: 16px 0 0 0; text-align: center;">If the button doesn\'t work, copy and paste this link:<br><span style="word-break: break-all; color: #3b82f6;">' . htmlspecialchars($resetUrl) . '</span></p>
            </div>
            
        </div>';
    $mail->AltBody = "Reset link:\n\n" . $resetUrl;
    $mail->send();

    echo json_encode(['success' => true, 'message' => 'If your email is registered you will receive instructions.']);
} catch (Exception $e) {
    log_msg('PHPMailer error: ' . ($mail->ErrorInfo ?? '') . ' | Exception: ' . $e->getMessage());
    if ($DEBUG) {
        http_response_code(500);
        echo json_encode(['error' => 'Mail error: ' . ($mail->ErrorInfo ?? $e->getMessage())]);
    } else {
        echo json_encode(['success' => true, 'message' => 'If your email is registered you will receive instructions.']);
    }
}

$mysqli->close();
exit;
?>
