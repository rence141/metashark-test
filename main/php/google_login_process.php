<?php
// Start session and ensure it's active
session_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    error_log("Session not active in google_login_process.php");
    header("Location: ../../login_users.php?error=Session failed to start");
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';
include("db.php");

// Check if Google session data is available
if (!isset($_SESSION['google_email']) || !isset($_SESSION['google_name'])) {
    error_log("Missing Google session data: email=" . (isset($_SESSION['google_email']) ? $_SESSION['google_email'] : 'not set') . ", name=" . (isset($_SESSION['google_name']) ? $_SESSION['google_name'] : 'not set'));
    header("Location: ../../login_users.php?error=Invalid Google login session");
    exit;
}

$email = $_SESSION['google_email'];
$name = $_SESSION['google_name'];

// Check if user exists
$stmt = $conn->prepare("SELECT id, role FROM users WHERE email = ? LIMIT 1");
if (!$stmt) {
    error_log("Database prepare failed: " . $conn->error);
    header("Location: ../../login_users.php?error=Database error");
    exit;
}
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, role) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Database prepare failed for insert: " . $conn->error);
        header("Location: ../../login_users.php?error=Database error");
        exit;
    }
    $password = password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT); // Random password
    $role = 'buyer'; // Default role
    $stmt->bind_param("ssss", $name, $email, $password, $role);
    $stmt->execute();
    $user_id = $conn->insert_id; // Get the ID of the newly inserted user
} else {
    // Existing user
    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    $role = $user['role'] ?? 'buyer';
}

// Set session variables
$_SESSION['user_id'] = $user_id; // Required by shop.php
$_SESSION['email'] = $email;
$_SESSION['name'] = $name;
$_SESSION['role'] = $role;
$_SESSION['login_success'] = true; // Required for shop.php's just-logged-in behavior

// Log session data for debugging
error_log("Session set: user_id=$user_id, email=$email, name=$name, role=$role, login_success=true");

// Clear temporary Google session data
unset($_SESSION['google_email']);
unset($_SESSION['google_name']);

// Ensure session is written before redirect
session_write_close();

// Redirect to shop.php
header("Location: http://localhost/SaysonCotest/main/php/shop.php");
exit;
?>