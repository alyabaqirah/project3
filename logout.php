<?php
/**
 * Logout Script
 * Destroys user session and redirects to guest home page
 */

// Start the session
session_start();

// Unset all session variables
$_SESSION = array();

// If using session cookies, delete the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Regenerate session ID for security
session_regenerate_id(true);

// Redirect to guest home page
header('Location: guest-home.html');
exit;
?>