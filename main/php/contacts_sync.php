<?php
// Copies phone field into standardized format if present
// Since users table has only `phone`, we won't use gcash_number/contact_number

function sync_user_contact_fields($conn, $userId) {
    // Only select phone
    $sel = $conn->prepare("SELECT phone FROM users WHERE id = ?");
    if (!$sel) { 
        return false; 
    }

    $sel->bind_param("i", $userId);
    $sel->execute();
    $res = $sel->get_result();
    if ($res->num_rows !== 1) { 
        return false; 
    }

    $u = $res->fetch_assoc();
    $phone = trim((string)($u['phone'] ?? ''));

    if ($phone === '') { 
        return false; 
    }

    // Normalize phone: remove spaces, dashes, keep only digits/plus
    $normalized = preg_replace('/[^0-9+]/', '', $phone);
    if ($normalized === '') { 
        $normalized = $phone; 
    }

    // For now, just return the normalized phone
    return $normalized;
}
