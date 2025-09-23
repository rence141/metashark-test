<?php
// Maintenance script: Sync contact fields for all users
session_start();
include("db.php");
include_once("contacts_sync.php");

// Optional admin check can be added here

$rs = $conn->query("SELECT id FROM users");
$count = 0;
if ($rs) {
    while ($row = $rs->fetch_assoc()) {
        if (sync_user_contact_fields($conn, (int)$row['id'])) { $count++; }
    }
}

echo "Synced contacts for $count user(s).";


