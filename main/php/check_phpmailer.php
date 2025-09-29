<?php
$phpmailerDir = __DIR__ . '/PHPMailer/';
$requiredFiles = ['Exception.php', 'PHPMailer.php', 'SMTP.php'];

echo "<h3>PHPMailer Installation Check</h3>";
echo "<p>Scanning: " . htmlspecialchars($phpmailerDir) . "</p>";

if (!is_dir($phpmailerDir)) {
    echo '<p style="color:red;">❌ PHPMailer folder missing. Create it and add files.</p>';
} else {
    echo '<p style="color:orange;">📁 PHPMailer folder exists.</p>';
    $missing = [];
    foreach ($requiredFiles as $file) {
        if (file_exists($phpmailerDir . $file)) {
            echo '<p style="color:green;">✅ ' . htmlspecialchars($file) . ' found.</p>';
        } else {
            echo '<p style="color:red;">❌ ' . htmlspecialchars($file) . ' missing.</p>';
            $missing[] = $file;
        }
    }
    if (empty($missing)) {
        echo '<p style="color:green;"><strong>All files present! PHPMailer is ready.</strong></p>';
        // Quick class check
        require_once $phpmailerDir . 'PHPMailer.php';
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            echo '<p style="color:green;">✅ PHPMailer class loads successfully.</p>';
        } else {
            echo '<p style="color:red;">❌ Class not found (wrong version/path).</p>';
        }
    } else {
        echo '<p style="color:red;">Missing files: ' . implode(', ', array_map('htmlspecialchars', $missing)) . '</p>';
    }
}
echo '<hr><p><a href="verify_account.php">Back to Verification</a></p>';
?>