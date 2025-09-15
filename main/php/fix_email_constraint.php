<?php
// Script to fix email constraint in users table
include("db.php");

echo "<h2>Fixing Email Constraint in Users Table</h2>";

try {
    // First, let's see the current table structure
    echo "<h3>Current Table Structure:</h3>";
    $result = $conn->query("SHOW CREATE TABLE users");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>";
    }
    
    echo "<h3>Current Indexes on email column:</h3>";
    $result = $conn->query("SHOW INDEX FROM users WHERE Key_name = 'email'");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "Index: " . $row['Key_name'] . " - Column: " . $row['Column_name'] . "<br>";
        }
    } else {
        echo "No email indexes found.<br>";
    }
    
    // Try to drop the email unique constraint
    echo "<h3>Attempting to remove email unique constraint...</h3>";
    
    // Drop email index if it exists
    $sql = "ALTER TABLE users DROP INDEX email";
    if ($conn->query($sql)) {
        echo " Successfully dropped 'email' index<br>";
    } else {
        echo " Error dropping 'email' index: " . $conn->error . "<br>";
    }
    
    // Drop email_2 index if it exists
    $sql = "ALTER TABLE users DROP INDEX email_2";
    if ($conn->query($sql)) {
        echo " Successfully dropped 'email_2' index<br>";
    } else {
        echo " Error dropping 'email_2' index: " . $conn->error . "<br>";
    }
    
    // Check if there are any other email-related constraints
    $result = $conn->query("SHOW INDEX FROM users WHERE Column_name = 'email'");
    if ($result && $result->num_rows > 0) {
        echo "<h3>Remaining email indexes:</h3>";
        while ($row = $result->fetch_assoc()) {
            echo "Index: " . $row['Key_name'] . " - Type: " . $row['Index_type'] . "<br>";
        }
    } else {
        echo " No more email indexes found - constraint removed!<br>";
    }
    
    echo "<h3> Email constraint fix completed!</h3>";
    echo "<p>Now the same email can be used for multiple accounts with different roles.</p>";
    
} catch (Exception $e) {
    echo " Error: " . $e->getMessage();
}

$conn->close();
?>
