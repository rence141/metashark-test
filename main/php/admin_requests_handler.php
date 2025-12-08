<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/email.php';

// Check if Admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$token = $_GET['token'] ?? '';
$action = $_GET['action'] ?? '';
$redirect_url = 'pending_requests.php';

function redirect_with_alert($type, $title, $message, $url) {
    $_SESSION['swal_trigger'] = [
        'icon' => $type, 
        'title' => $title,
        'text' => $message
    ];
    header("Location: $url");
    exit;
}

if (!$token || !in_array($action, ['approve','reject'])) {
    redirect_with_alert('error', 'Invalid Request', 'Missing token or action.', $redirect_url);
}

// Fetch request
$stmt = $conn->prepare("SELECT * FROM admin_requests WHERE token = ? LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$req) {
    redirect_with_alert('error', 'Not Found', 'Request not found.', $redirect_url);
} elseif ($req['status'] !== 'pending') {
    redirect_with_alert('info', 'Already Processed', 'Request already processed.', $redirect_url);
}

// Data from request
$requesterEmail = $req['email'];
$first = $req['first_name'];
$last = $req['last_name'];
$fullName = $first . ' ' . $last; // Prepared for single-column tables

if ($action === 'approve') {
    
    // 1. Check if user exists (SCENARIO A: Upgrade existing)
    $chk = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $chk->bind_param('s', $requesterEmail);
    $chk->execute();
    $existingUser = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($existingUser) {
        // UPDATE Existing User
        $upd = $conn->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
        $upd->bind_param('i', $existingUser['id']);
        if ($upd->execute()) {
            $conn->query("UPDATE admin_requests SET status='approved' WHERE id=" . (int)$req['id']);
            send_email($requesterEmail, 'Admin Access Granted', "Your account has been upgraded to Admin.");
            redirect_with_alert('success', 'Upgraded', "Existing user upgraded to Admin.", $redirect_url);
        } else {
            redirect_with_alert('error', 'Database Error', $conn->error, $redirect_url);
        }
        $upd->close();
    } else {
        // SCENARIO B: INSERT New User (SMART AUTO-DETECT)
        
        // 1. Get actual column names from the 'users' table
        $columns = [];
        $res = $conn->query("SHOW COLUMNS FROM users");
        while($row = $res->fetch_assoc()) {
            $columns[] = $row['Field'];
        }

        // 2. logic to pick the right column names
        $query = "";
        $types = "";
        $params = [];

        // CHECK: Does 'role' exist? Or is it 'usertype'?
        $roleCol = in_array('role', $columns) ? 'role' : (in_array('usertype', $columns) ? 'usertype' : 'type');

        if (in_array('first_name', $columns) && in_array('last_name', $columns)) {
            // Standard: first_name, last_name
            $query = "INSERT INTO users (first_name, last_name, email, password, $roleCol, created_at) VALUES (?, ?, ?, ?, 'admin', NOW())";
            $types = "ssss";
            $params = [$first, $last, $requesterEmail, $req['password_hash']];

        } elseif (in_array('firstname', $columns) && in_array('lastname', $columns)) {
            // Variation: firstname, lastname (No underscores)
            $query = "INSERT INTO users (firstname, lastname, email, password, $roleCol, created_at) VALUES (?, ?, ?, ?, 'admin', NOW())";
            $types = "ssss";
            $params = [$first, $last, $requesterEmail, $req['password_hash']];

        } elseif (in_array('fname', $columns) && in_array('lname', $columns)) {
            // Short: fname, lname
            $query = "INSERT INTO users (fname, lname, email, password, $roleCol, created_at) VALUES (?, ?, ?, ?, 'admin', NOW())";
            $types = "ssss";
            $params = [$first, $last, $requesterEmail, $req['password_hash']];

        } elseif (in_array('full_name', $columns)) {
            // Single: full_name
            $query = "INSERT INTO users (full_name, email, password, $roleCol, created_at) VALUES (?, ?, ?, 'admin', NOW())";
            $types = "sss";
            $params = [$fullName, $requesterEmail, $req['password_hash']];

        } elseif (in_array('fullname', $columns)) {
            // Single: fullname
            $query = "INSERT INTO users (fullname, email, password, $roleCol, created_at) VALUES (?, ?, ?, 'admin', NOW())";
            $types = "sss";
            $params = [$fullName, $requesterEmail, $req['password_hash']];

        } elseif (in_array('name', $columns)) {
            // Single: name (You tried this, but adding it just in case logic was wrong before)
            $query = "INSERT INTO users (name, email, password, $roleCol, created_at) VALUES (?, ?, ?, 'admin', NOW())";
            $types = "sss";
            $params = [$fullName, $requesterEmail, $req['password_hash']];
            
        } else {
             die("<strong>Database Structure Error:</strong> Could not find a recognizable name column in your 'users' table. <br>Columns found: " . implode(", ", $columns));
        }

        // 3. Execute the detected query
        $ins = $conn->prepare($query);
        if (!$ins) {
            die("Prepare failed: " . $conn->error . "<br>Query: " . $query);
        }
        $ins->bind_param($types, ...$params); // standard unpacking

        if ($ins->execute()) {
            $conn->query("UPDATE admin_requests SET status='approved' WHERE id=" . (int)$req['id']);
            send_email($requesterEmail, 'Admin Request Approved', "Hello {$first},\n\nYour admin request has been approved. You can now login.");
            redirect_with_alert('success', 'Account Created', "New user created in 'users' table.", $redirect_url);
        } else {
            redirect_with_alert('error', 'Database Error', $conn->error, $redirect_url);
        }
        $ins->close();
    }
} else { // reject
    $conn->query("UPDATE admin_requests SET status='rejected' WHERE id=" . (int)$req['id']);
    send_email($requesterEmail, 'Admin Request Rejected', "Hello {$first},\n\nYour admin request has been rejected.");
    redirect_with_alert('success', 'Rejected', "Request rejected.", $redirect_url);
}
?>