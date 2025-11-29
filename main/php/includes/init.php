<?php
/**
 * Common initialization file
 * Loads language preferences and translations
 */

if (!isset($_SESSION)) {
    session_start();
}

// Load language preference from database if user is logged in
if (isset($_SESSION['user_id']) && !isset($_SESSION['language_loaded'])) {
    if (file_exists(__DIR__ . '/db_connect.php')) {
        require_once __DIR__ . '/db_connect.php';
        $userId = $_SESSION['user_id'];
        $langSql = "SELECT language FROM users WHERE id = ?";
        $langStmt = $conn->prepare($langSql);
        if ($langStmt) {
            $langStmt->bind_param("i", $userId);
            $langStmt->execute();
            $langResult = $langStmt->get_result();
            if ($langResult->num_rows === 1) {
                $langRow = $langResult->fetch_assoc();
                $_SESSION['language'] = $langRow['language'] ?? 'en';
            }
            $langStmt->close();
        }
        $_SESSION['language_loaded'] = true;
    }
}

// Load translations
require_once __DIR__ . '/translations.php';
?>

