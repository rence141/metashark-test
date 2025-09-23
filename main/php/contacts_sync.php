<?php
// Copies phone fields into standardized contact fields if missing
// Rules:
// - If users.gcash_number is NULL/empty and users.phone is present -> set gcash_number = phone
// - If users.contact_number is NULL/empty and users.phone is present -> set contact_number = phone

function sync_user_contact_fields($conn, $userId) {
    // Detect optional columns
    $hasGcash = false; $hasContact = false;
    $c1 = $conn->query("SHOW COLUMNS FROM users LIKE 'gcash_number'");
    if ($c1 && $c1->num_rows > 0) { $hasGcash = true; }
    $c2 = $conn->query("SHOW COLUMNS FROM users LIKE 'contact_number'");
    if ($c2 && $c2->num_rows > 0) { $hasContact = true; }

    // Build SELECT with only existing columns
    $selectCols = ['phone'];
    if ($hasGcash) { $selectCols[] = 'gcash_number'; }
    if ($hasContact) { $selectCols[] = 'contact_number'; }
    $colsSql = implode(', ', $selectCols);

    $sel = $conn->prepare("SELECT $colsSql FROM users WHERE id = ?");
    if (!$sel) { return false; }
    $sel->bind_param("i", $userId);
    $sel->execute();
    $res = $sel->get_result();
    if ($res->num_rows !== 1) { return false; }
    $u = $res->fetch_assoc();

    $phone = trim((string)($u['phone'] ?? ''));
    if ($phone === '') { return false; }

    // Basic normalization: remove spaces and dashes
    $normalized = preg_replace('/[^0-9+]/', '', $phone);
    if ($normalized === '') { $normalized = $phone; }

    $didUpdate = false;

    if ($hasGcash) {
        $gcash = trim((string)($u['gcash_number'] ?? ''));
        if ($gcash === '') {
            $upd = $conn->prepare("UPDATE users SET gcash_number = ? WHERE id = ?");
            if ($upd) { $upd->bind_param("si", $normalized, $userId); $upd->execute(); $didUpdate = true; }
        }
    }
    if ($hasContact) {
        $contact = trim((string)($u['contact_number'] ?? ''));
        if ($contact === '') {
            $upd2 = $conn->prepare("UPDATE users SET contact_number = ? WHERE id = ?");
            if ($upd2) { $upd2->bind_param("si", $normalized, $userId); $upd2->execute(); $didUpdate = true; }
        }
    }

    return $didUpdate;
}


