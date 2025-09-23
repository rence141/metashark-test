<?php
// Copies phone fields into standardized contact fields if missing
// Rules:
// - If users.gcash_number is NULL/empty and users.phone is present -> set gcash_number = phone
// - If users.contact_number is NULL/empty and users.phone is present -> set contact_number = phone

function sync_user_contact_fields($conn, $userId) {
    $sel = $conn->prepare("SELECT phone, gcash_number, contact_number FROM users WHERE id = ?");
    if (!$sel) { return false; }
    $sel->bind_param("i", $userId);
    $sel->execute();
    $res = $sel->get_result();
    if ($res->num_rows !== 1) { return false; }
    $u = $res->fetch_assoc();

    $phone = trim((string)($u['phone'] ?? ''));
    $gcash = trim((string)($u['gcash_number'] ?? ''));
    $contact = trim((string)($u['contact_number'] ?? ''));

    if ($phone === '') { return false; }

    // Basic normalization: remove spaces and dashes
    $normalized = preg_replace('/[^0-9+]/', '', $phone);
    if ($normalized === '') { $normalized = $phone; }

    $didUpdate = false;

    if ($gcash === '') {
        $upd = $conn->prepare("UPDATE users SET gcash_number = ? WHERE id = ?");
        if ($upd) { $upd->bind_param("si", $normalized, $userId); $upd->execute(); $didUpdate = true; }
    }
    if ($contact === '') {
        $upd2 = $conn->prepare("UPDATE users SET contact_number = ? WHERE id = ?");
        if ($upd2) { $upd2->bind_param("si", $normalized, $userId); $upd2->execute(); $didUpdate = true; }
    }

    return $didUpdate;
}


