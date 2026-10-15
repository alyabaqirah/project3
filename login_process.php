<?php
// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Prevent any output before JSON response
ob_start();

session_start();

// Check if db_connection.php exists, if not try db.php
$db_file = 'db.php';

if (!file_exists($db_file)) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database configuration file not found.']);
    exit;
}

require $db_file;

// Clear any buffered output
ob_end_clean();

// Set JSON header
header('Content-Type: application/json');

// Verify database connection (check for both $pdo and $conn)
if (!isset($pdo) && !isset($conn)) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// Use whichever connection variable is available
if (!isset($pdo) && isset($conn)) {
    $pdo = $conn;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get and validate inputs
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'member';

// Validate required fields
if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

// Validate role
$allowed_roles = ['member', 'staff', 'admin'];
if (!in_array($role, $allowed_roles)) {
    echo json_encode(['success' => false, 'message' => 'Invalid role selected.']);
    exit;
}

try {
    // Query user from database
    $stmt = $pdo->prepare("
        SELECT user_id, full_name, email, phone, password_hash, role, status, 
               staff_id, department, position, admin_id,
               email_notifications, sms_notifications
        FROM users 
        WHERE email = :email AND role = :role
    ");
    
    $stmt->execute([
        ':email' => $email,
        ':role' => $role
    ]);
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if user exists
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password for the selected role.']);
        exit;
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    // Check account status - UPDATED PENDING CHECK
    if ($user['status'] === 'pending') {
        $role_display = ucfirst($user['role']);
        echo json_encode([
            'success' => false, 
            'message' => "Your {$role_display} account is pending admin approval. You will be able to login once your account is approved."
        ]);
        exit;
    }

    if ($user['status'] === 'suspended') {
        echo json_encode(['success' => false, 'message' => 'Your account has been suspended. Please contact support.']);
        exit;
    }

    if ($user['status'] === 'inactive') {
        echo json_encode(['success' => false, 'message' => 'Your account is inactive. Please contact support to reactivate.']);
        exit;
    }

    if ($user['status'] !== 'active') {
        echo json_encode(['success' => false, 'message' => 'Your account status is: ' . $user['status'] . '. Please contact support.']);
        exit;
    }

    // Login successful - create session
    session_regenerate_id(true); // Regenerate session ID for security
    
    // Set session variables to match your existing dashboard structure
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['role'] = $user['role']; // Direct role for compatibility
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['phone'] = $user['phone'];
    $_SESSION['status'] = $user['status'];
    
    // Also set the complete user array for other uses
    $_SESSION['user'] = [
        'user_id' => $user['user_id'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'role' => $user['role'],
        'status' => $user['status'],
        'staff_id' => $user['staff_id'],
        'department' => $user['department'],
        'position' => $user['position'],
        'admin_id' => $user['admin_id'],
        'email_notifications' => $user['email_notifications'],
        'sms_notifications' => $user['sms_notifications']
    ];
    
    // Ensure session is written before redirect
    session_write_close();

    // Update last login timestamp (optional - you can add a last_login column to your database)
    try {
        $updateStmt = $pdo->prepare("UPDATE users SET updated_at = NOW() WHERE user_id = :user_id");
        $updateStmt->execute([':user_id' => $user['user_id']]);
    } catch (PDOException $e) {
        // Just log the error, don't fail the login
        error_log('Failed to update last login: ' . $e->getMessage());
    }

    // Determine redirect URL based on role
    $redirect = '';
    switch ($user['role']) {
        case 'member':
            $redirect = 'member-dashboard.php';
            break;
        case 'staff':
            $redirect = 'staff-dashboard.php';
            break;
        case 'admin':
            $redirect = 'admin-dashboard.php';
            break;
        default:
            $redirect = 'guest-home.php';
    }

    echo json_encode([
        'success' => true,
        'message' => 'Login successful! Redirecting...',
        'redirect' => $redirect,
        'user' => [
            'user_id' => $user['user_id'],
            'full_name' => $user['full_name'],
            'role' => $user['role']
        ]
    ]);

} catch (PDOException $e) {
    // Log error for debugging
    error_log('Login error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again later.']);
} catch (Exception $e) {
    error_log('Unexpected login error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again.']);
}
?>