<?php
header('Content-Type: application/json');
session_start();

// Basic auth check: require logged-in user
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Ensure upload dir exists
$uploadDir = __DIR__ . '/../Uploads/chat_images';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No image uploaded']);
    exit;
}

$file = $_FILES['image'];
// Validate mime
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
if (!isset($allowed[$mime])) {
    echo json_encode(['success' => false, 'error' => 'Unsupported image type']);
    exit;
}

// Size limit: 5MB
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'Image too large']);
    exit;
}

$ext = $allowed[$mime];
$filename = 'img_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$target = $uploadDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    echo json_encode(['success' => false, 'error' => 'Save failed']);
    exit;
}

// Build public URL relative to this script (../Uploads/chat_images/...)
$publicUrl = 'Uploads/chat_images/' . $filename;
echo json_encode(['success' => true, 'url' => $publicUrl]);
?>


