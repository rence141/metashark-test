<?php
// 1. Start Session
session_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    error_log("Session not active in google_login_process.php");
    header("Location: ../../login_users.php?error=Session failed to start");
    exit;
}

// 2. Load Composer Autoload (for Google API Client)
require_once __DIR__ . '/../../vendor/autoload.php';

// --- DATABASE CONNECTION ---
if (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
}
if (!isset($conn) && file_exists(__DIR__ . '/../../includes/db_connect.php')) {
    require_once __DIR__ . '/../../includes/db_connect.php';
}
if (!isset($conn)) {
    // Manual fallback
    $host = "localhost";
    $user = "root";
    $pass = "003421.!"; 
    $dbname = "MetaAccesories";
    $port = 3307;

    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli($host, $user, $pass, $dbname, $port);
}

if (!isset($conn) || $conn->connect_error) {
    die("Critical Database Error: " . (isset($conn) ? $conn->connect_error : "Connection failed"));
}
$conn->set_charset("utf8mb4");
// --- END DB CONNECTION ---


// 3. Verify Google Session Data Exists
if (!isset($_SESSION['google_email']) || !isset($_SESSION['google_name'])) {
    header("Location: ../../login_users.php?error=Invalid Google login session");
    exit;
}

$google_email = $_SESSION['google_email'];
$google_name  = $_SESSION['google_name'];

// 4. Check if User Exists
// FIX: Changed selection to 'fullname' instead of first_name/last_name
$stmt = $conn->prepare("SELECT id, fullname, role, is_suspended FROM users WHERE email = ? LIMIT 1");
if (!$stmt) {
    die("Database prepare failed: " . $conn->error);
}
$stmt->bind_param("s", $google_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // --- EXISTING USER ---
    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    $role = $user['role'];
    $db_fullname = $user['fullname'];
    
    // === CRITICAL SUSPENSION CHECK ===
    if ((int)$user['is_suspended'] === 1) {
        // Log the attempt
        error_log("SUSPENDED USER ATTEMPT: $google_email");
        
        // Store email for the appeal form
        $_SESSION['temp_email'] = $google_email;
        
        // Clear Google data
        unset($_SESSION['google_email']);
        unset($_SESSION['google_name']);
        
        // Redirect to Suspension Page
        header("Location: suspended_account.php");
        exit;
    }
    // =================================

} else {
    // --- NEW USER REGISTRATION ---
    
    $password = password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT); // Random secure password
    $role = 'buyer';
    $is_suspended = 0; // Default active

    // FIX: Changed INSERT to use 'fullname'
    $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, role, is_suspended) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        die("Insert prepare failed: " . $conn->error);
    }
    
    // Bind parameters: s=string, i=integer
    $stmt->bind_param("ssssi", $google_name, $google_email, $password, $role, $is_suspended);
    
    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        $db_fullname = $google_name;
    } else {
        die("Registration failed: " . $conn->error);
    }
}

// 5. Set Session Variables (Log them in)
$_SESSION['user_id'] = $user_id;
$_SESSION['user_name'] = $db_fullname; // Used for "Hello, Name"
$_SESSION['role'] = $role;
$_SESSION['email'] = $google_email;
$_SESSION['login_success'] = true; 

// Clean up temp Google vars
unset($_SESSION['google_email']);
unset($_SESSION['google_name']);

session_write_close();

// 6. Redirect to Shop
header("Location: http://localhost/SaysonCotest/main/php/shop.php");
exit;
?>