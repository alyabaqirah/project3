<?php
// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to prevent any premature output
ob_start();

session_start();

// Try to include database connection file
$db_file = 'db.php';

if (empty($db_file)) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database configuration file not found.']);
    exit;
}

require_once $db_file;

// Clear any buffered output before sending JSON
ob_end_clean();

// Set JSON header
header('Content-Type: application/json');

// Verify database connection
if (!isset($pdo) && !isset($conn)) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed. Please check your configuration.']);
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

// Validate and sanitize inputs
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'member';

// Validate required fields
if (empty($full_name) || empty($email) || empty($phone) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

// Validate email format - multiple checks
if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required.']);
    exit;
}

// Check if email contains @ symbol
if (strpos($email, '@') === false) {
    echo json_encode(['success' => false, 'message' => 'Email must contain @ symbol.']);
    exit;
}

// Validate email format using PHP's built-in filter
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format. Please enter a valid email address.']);
    exit;
}

// Validate phone number - only numbers, spaces, and hyphens allowed
if (empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Phone number is required.']);
    exit;
}

// Check if phone contains invalid characters (anything except digits, spaces, and hyphens)
if (preg_match('/[^0-9\s\-]/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Phone number can only contain numbers, spaces, and hyphens (-).']);
    exit;
}

// Check for consecutive hyphens or spaces (which might indicate invalid formatting)
if (preg_match('/\-\-/', $phone) || preg_match('/\s\s/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Phone number contains invalid formatting.']);
    exit;
}

// Check if phone starts or ends with a hyphen or space
if (preg_match('/^[\s\-]/', $phone) || preg_match('/[\s\-]$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Phone number cannot start or end with a space or hyphen.']);
    exit;
}

// Remove allowed separators to check the actual digit count
$digits_only = preg_replace('/[^0-9]/', '', $phone);

// Check minimum digit count
if (strlen($digits_only) < 5) {
    echo json_encode(['success' => false, 'message' => 'Phone number must contain at least 5 digits.']);
    exit;
}

// Check maximum digit count (reasonable limit for phone numbers)
if (strlen($digits_only) > 15) {
    echo json_encode(['success' => false, 'message' => 'Phone number cannot exceed 15 digits.']);
    exit;
}

// Validate password length
if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
    exit;
}

// Validate role
$allowed_roles = ['member', 'staff', 'admin'];
if (!in_array($role, $allowed_roles)) {
    echo json_encode(['success' => false, 'message' => 'Invalid role selected.']);
    exit;
}

// Role-specific fields initialization
$staff_id = null;
$department = null;
$position = null;
$admin_id = null;

// Hash password
$password_hash = password_hash($password, PASSWORD_DEFAULT);

/**
 * Function to find the next available ID by filling gaps in the sequence
 * This function reuses rejected or deleted IDs instead of always incrementing
 * 
 * @param PDO $pdo Database connection
 * @param string $prefix ID prefix (e.g., 'STF' or 'ADM')
 * @param string $column_name Column name in database (e.g., 'staff_id' or 'admin_id')
 * @return string Next available ID with gaps filled
 */
function getNextAvailableId($pdo, $prefix, $column_name) {
    // Get all existing IDs with this prefix, ordered numerically
    $stmt = $pdo->prepare("
        SELECT $column_name 
        FROM users 
        WHERE $column_name LIKE :prefix 
        ORDER BY $column_name ASC
    ");
    $stmt->execute([':prefix' => $prefix . '%']);
    $existing_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Extract numeric parts from existing IDs
    $existing_numbers = [];
    foreach ($existing_ids as $id) {
        if ($id) {
            $number = intval(substr($id, strlen($prefix)));
            if ($number > 0) {
                $existing_numbers[] = $number;
            }
        }
    }
    
    // Sort the numbers to ensure proper gap detection
    sort($existing_numbers);
    
    // Find the first gap in the sequence
    $next_number = 1;
    
    foreach ($existing_numbers as $num) {
        if ($num == $next_number) {
            // This number is taken, try the next one
            $next_number++;
        } else if ($num > $next_number) {
            // Found a gap! Use the current next_number
            break;
        }
    }
    
    // Format and return the ID (e.g., STF001, ADM001)
    return $prefix . str_pad($next_number, 3, '0', STR_PAD_LEFT);
}

try {
    // Check for existing email
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already exists. Please use a different email.']);
        exit;
    }

    // Set status based on role
    // Members: active immediately
    // Staff and Admin: pending approval
    if ($role === 'member') {
        $status = 'active';
    } else {
        $status = 'pending';
    }

    // Auto-generate staff_id for staff role with gap-filling logic
    if ($role === 'staff') {
        // Use the new function to find the next available ID (fills gaps)
        $staff_id = getNextAvailableId($pdo, 'STF', 'staff_id');
        
        // Set default values for staff
        $position = 'Staff';
        $department = 'General';
    }

    // Auto-generate admin_id for admin role with gap-filling logic
    if ($role === 'admin') {
        // Use the new function to find the next available ID (fills gaps)
        $admin_id = getNextAvailableId($pdo, 'ADM', 'admin_id');
    }

    // Set notification preferences
    $email_notifications = 1;
    $sms_notifications = 0;
    $preferences = json_encode([
        "emailNotifications" => true,
        "smsNotifications" => false
    ]);

    // Insert user into database
    $sql = "INSERT INTO users 
            (full_name, email, phone, password_hash, role, status, 
             email_notifications, sms_notifications, 
             staff_id, department, position, 
             admin_id, 
             preferences, created_at, updated_at)
            VALUES
            (:full_name, :email, :phone, :password_hash, :role, :status, 
             :email_notifications, :sms_notifications, 
             :staff_id, :department, :position, 
             :admin_id, 
             :preferences, NOW(), NOW())";

    $stmt = $pdo->prepare($sql);
    
    $result = $stmt->execute([
        ':full_name' => $full_name,
        ':email' => $email,
        ':phone' => $phone,
        ':password_hash' => $password_hash,
        ':role' => $role,
        ':status' => $status,
        ':email_notifications' => $email_notifications,
        ':sms_notifications' => $sms_notifications,
        ':staff_id' => $staff_id,
        ':department' => $department,
        ':position' => $position,
        ':admin_id' => $admin_id,
        ':preferences' => $preferences
    ]);

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Failed to create account. Please try again.']);
        exit;
    }

    // Get the newly created user_id
    $user_id = $pdo->lastInsertId();

    // Prepare success message based on role and status
    if ($role === 'staff') {
        $message = 'Staff account created successfully! Your Staff ID is ' . $staff_id . '. Your account is pending admin approval. You will be able to login once approved.';
    } elseif ($role === 'admin') {
        $message = 'Admin account created successfully! Your Admin ID is ' . $admin_id . '. Your account is pending admin approval. You will be able to login once approved.';
    } else {
        $message = 'Account created successfully! Please login to continue.';
    }
    
    // Always redirect to login page
    $redirect = 'login.php';

    echo json_encode([
        'success' => true, 
        'message' => $message, 
        'redirect' => $redirect,
        'user_id' => $user_id,
        'staff_id' => $staff_id,
        'admin_id' => $admin_id,
        'status' => $status
    ]);

} catch (PDOException $e) {
    // Log error
    error_log('Registration error: ' . $e->getMessage());
    
    // Check for specific database errors
    if ($e->getCode() == 23000) {
        if (strpos($e->getMessage(), 'email') !== false) {
            echo json_encode(['success' => false, 'message' => 'Email already exists.']);
        } elseif (strpos($e->getMessage(), 'staff_id') !== false) {
            echo json_encode(['success' => false, 'message' => 'Staff ID already exists.']);
        } elseif (strpos($e->getMessage(), 'admin_id') !== false) {
            echo json_encode(['success' => false, 'message' => 'Admin ID already exists.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Duplicate entry detected.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again later.']);
    }
} catch (Exception $e) {
    error_log('Unexpected error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again.']);
}
?>