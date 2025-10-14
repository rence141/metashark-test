<?php
// DB connection (update credentials as needed or set env vars)
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_PORT = getenv('DB_PORT') ?: '3307'; // allow blank to try defaults
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '003421.!';
$DB_NAME = getenv('DB_NAME') ?: 'metaaccesories';

// Build candidate host/port pairs to try
$candidates = [];

// If explicit port provided, try it first for the given host
if ($DB_PORT !== '') {
    $candidates[] = ['host' => $DB_HOST, 'port' => (int)$DB_PORT];
} else {
    // common combinations to try
    $candidates[] = ['host' => $DB_HOST, 'port' => 3306];
    $candidates[] = ['host' => $DB_HOST, 'port' => 3307];
    // try localhost variants if initial host was 127.0.0.1
    if ($DB_HOST === '127.0.0.1') {
        $candidates[] = ['host' => 'localhost', 'port' => 3306];
        $candidates[] = ['host' => 'localhost', 'port' => 3307];
    } else {
        // if a hostname was provided, also try 127.0.0.1 and localhost
        $candidates[] = ['host' => '127.0.0.1', 'port' => 3306];
        $candidates[] = ['host' => 'localhost', 'port' => 3306];
    }
}

// Ensure uniqueness
$seen = [];
$unique = [];
foreach ($candidates as $c) {
    $key = $c['host'] . ':' . $c['port'];
    if (!isset($seen[$key])) { $seen[$key] = true; $unique[] = $c; }
}
$candidates = $unique;

// Try connections without throwing a fatal
mysqli_report(MYSQLI_REPORT_OFF);
$lastErr = null;
$conn = null;

foreach ($candidates as $c) {
    $h = $c['host'];
    $p = $c['port'];

    // use a temporary variable for the attempt
    $tmp = @new mysqli($h, $DB_USER, $DB_PASS, $DB_NAME, $p);

    // if connection succeeded, keep it and stop trying
    if ($tmp instanceof mysqli && !$tmp->connect_errno) {
        $conn = $tmp;
        break;
    }

    // record last error info (do not call ->close() on failed objects)
    $errNo = ($tmp instanceof mysqli) ? $tmp->connect_errno : null;
    $errMsg = ($tmp instanceof mysqli) ? $tmp->connect_error : (error_get_last()['message'] ?? 'unknown');
    $lastErr = [
        'host' => $h,
        'port' => $p,
        'errno' => $errNo,
        'error' => $errMsg
    ];

    // release temporary reference (no explicit close)
    unset($tmp);
}

// If still no connection, show friendly message and log details
if (!($conn instanceof mysqli) || $conn->connect_errno) {
    // log detailed info for admin / developer only
    error_log('DB connection failure. Tried candidates: ' . json_encode($candidates));
    if ($lastErr) error_log('Last connect error: ' . json_encode($lastErr));

    // Friendly HTML output (no credentials shown)
    http_response_code(500);
    $attempts = array_map(function($c){ return htmlspecialchars($c['host'] . ':' . $c['port']); }, $candidates);
    $attemptList = implode(', ', $attempts);
    echo "<!doctype html><html><head><meta charset='utf-8'><title>Database connection error</title>";
    echo "<style>body{font-family:Arial,Helvetica,sans-serif;background:#111;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;margin:0} .box{max-width:820px;padding:24px;border-radius:8px;background:#0b1220;border:1px solid #222} a{color:#0ff}</style>";
    echo "</head><body><div class='box'>";
    echo "<h2>Database connection failed</h2>";
    echo "<p>Please verify MySQL is running and your database credentials/port in <code>includes/db_connect.php</code>.</p>";
    echo "<p>Attempted connection targets: <strong>{$attemptList}</strong></p>";
    echo "<p>Common fixes:</p><ul>";
    echo "<li>Start MySQL (XAMPP Control Panel &rarr; Start MySQL)</li>";
    echo "<li>Check port (default 3306). If XAMPP MySQL uses 3307, set <code>DB_PORT=3307</code> in your environment or edit <code>includes/db_connect.php</code>.</li>";
    echo "<li>Ensure username/password are correct. By default XAMPP uses user <code>root</code> with empty password.</li>";
    echo "<li>If using sockets on Linux, configure host appropriately (localhost vs 127.0.0.1).</li>";
    echo "</ul>";
    echo "<p>If this persists, check the server error log for details or contact your server administrator.</p>";
    echo "</div></body></html>";
    exit;
}

// set utf8 and continue
$conn->set_charset('utf8mb4');