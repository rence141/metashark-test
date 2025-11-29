<?php
session_start();
include("db.php");
include_once("email.php");

$message = '';
$email = isset($_GET['email']) ? $_GET['email'] : ($_SESSION['pending_verification_email'] ?? '');

if ($_SERVER["REQUEST_METHOD"] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $userId = $_SESSION['pending_verification_user_id'] ?? 0;

    if ($userId && $code !== '') {
        $sql = "SELECT verification_code, verification_expires FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 1) {
                $row = $res->fetch_assoc();

                $valid = $row['verification_code'] === $code &&
                        strtotime($row['verification_expires']) >= time();

                if ($valid) {
                    $upd = $conn->prepare("UPDATE users SET is_verified = 1, verification_code = NULL, verification_expires = NULL WHERE id = ?");
                    if ($upd) {
                        $upd->bind_param("i", $userId);
                        $upd->execute();
                    }

                    // Load user language preference from database
                    $langSql = "SELECT language FROM users WHERE id = ?";
                    $langStmt = $conn->prepare($langSql);
                    if ($langStmt) {
                        $langStmt->bind_param("i", $userId);
                        $langStmt->execute();
                        $langResult = $langStmt->get_result();
                        if ($langResult->num_rows === 1) {
                            $langRow = $langResult->fetch_assoc();
                            $_SESSION['language'] = $langRow['language'] ?? 'en';
                        } else {
                            $_SESSION['language'] = 'en';
                        }
                        $langStmt->close();
                    } else {
                        $_SESSION['language'] = 'en';
                    }

                    $_SESSION['user_id'] = $userId;
                    $_SESSION['email'] = $_SESSION['pending_verification_email'] ?? '';
                    $_SESSION['role'] = $_SESSION['pending_verification_role'] ?? 'buyer';
                    $_SESSION['login_success'] = true;

                    unset($_SESSION['pending_verification_user_id'], $_SESSION['pending_verification_email'], $_SESSION['pending_verification_role']);

                    $role = $_SESSION['role'];
                    header("Location: " . (($role === 'seller' || $role === 'admin') ? "seller_dashboard.php" : "shop.php"));
                    exit();
                } else {
                    $message = 'Invalid or expired code. Please request a new one.';
                }
            }
        }
    } else {
        $message = 'Enter the verification code.';
    }
}

if (isset($_GET['resend']) && ($_SESSION['pending_verification_user_id'] ?? 0)) {
    $userId = $_SESSION['pending_verification_user_id'];
    $code = (string)random_int(100000, 999999);
    $expiryAt = date('Y-m-d H:i:s', time() + 15 * 60);

    $upd = $conn->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?");
    if ($upd) {
        $upd->bind_param("ssi", $code, $expiryAt, $userId);
        $upd->execute();
        $message = 'A new code has been generated.';
    }

    if (!empty($_SESSION['pending_verification_email'])) {
        $subject = 'Your new verification code';
        $body = "Your new verification code is: " . htmlspecialchars($code);

        send_email($_SESSION['pending_verification_email'], $subject, $body);
        $message .= ' Check your email.';
    }
} elseif (isset($_GET['resend'])) {
    $message = 'No pending verification found.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Account</title>

<!-- Poppins Font -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
body {
    margin: 0;
    padding: 0;
    font-family: 'Poppins', sans-serif;
    background: #0e0e0e;
    color: #fff;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background-image: radial-gradient(circle at top, #1a1a1a 0%, #000 100%);
}

.card {
    background: rgba(255, 255, 255, 0.06);
    backdrop-filter: blur(15px);
    padding: 40px;
    width: 100%;
    max-width: 430px;
    border-radius: 18px;
    box-shadow: 0 0 30px rgba(68, 214, 44, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.15);
    animation: fadeIn 0.6s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

h1 {
    text-align: center;
    font-size: 26px;
    margin-bottom: 10px;
    color: #44D62C;
}

.email-display {
    color: #44D62C;
    font-weight: 600;
}

p {
    text-align: center;
    font-size: 15px;
    color: #bbb;
}

/* Progress bar */
.verification-bar {
    display: flex;
    justify-content: space-between;
    margin-bottom: 28px;
}

.step-box {
    flex: 1;
    height: 60px;
    margin: 0 5px;
    background: rgba(255,255,255,0.1);
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.15);
    color: #aaa;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    font-size: 13px;
    transition: 0.3s;
}

.step-box.completed,
.step-box.active {
    background: #44D62C;
    color: #000;
    font-weight: bold;
    border-color: #44D62C;
    box-shadow: 0 0 8px #44D62C;
}

/* OTP Boxes */
.code-inputs {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin: 25px 0;
}

.code-box {
    width: 52px;
    height: 60px;
    font-size: 24px;
    text-align: center;
    border-radius: 12px;
    border: none;
    outline: none;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    color: white;
    box-shadow: 0 0 10px rgba(68, 214, 44, 0);
    transition: 0.25s;
}

.code-box:focus {
    background: rgba(255,255,255,0.25);
    box-shadow: 0 0 12px #44D62C;
}

/* Button */
.btn {
    width: 100%;
    padding: 15px;
    font-size: 15px;
    border-radius: 10px;
    border: none;
    background: #44D62C;
    color: black;
    font-weight: bold;
    cursor: pointer;
    transition: 0.2s;
}

.btn:hover {
    background: #33b41f;
    box-shadow: 0 0 10px #44D62C;
}

/* Links */
.link {
    text-align: center;
    display: block;
    margin-top: 20px;
    color: #44D62C;
    text-decoration: none;
    font-size: 14px;
}

.link:hover {
    text-decoration: underline;
}

/* Timer */
.timer {
    text-align: center;
    margin-top: 10px;
    font-size: 14px;
    color: #44D62C;
}

.timer.expired {
    color: red;
}

.msg, .error-msg {
    text-align: center;
    margin-top: 10px;
    font-size: 14px;
}

.error-msg { color: #ff5858; }
</style>
</head>
<body>

<div class="card">

    <div class="verification-bar">
        <div class="step-box completed"><div>✓</div><small>Register</small></div>
        <div class="step-box active"><div>2</div><small>Verify</small></div>
        <div class="step-box"><div>3</div><small>Complete</small></div>
    </div>

    <h1>Email Verification</h1>
    <p>We sent a code to <br><span class="email-display"><?php echo htmlspecialchars($email); ?></span></p>

    <div class="timer" id="timer">Time remaining: 15:00</div>

    <?php if ($message): ?>
        <div class="<?php echo (str_contains($message, 'Invalid') ? 'error-msg' : 'msg'); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="verifyForm">
        <div class="code-inputs">
            <input type="text" class="code-box" maxlength="1" inputmode="numeric">
            <input type="text" class="code-box" maxlength="1" inputmode="numeric">
            <input type="text" class="code-box" maxlength="1" inputmode="numeric">
            <input type="text" class="code-box" maxlength="1" inputmode="numeric">
            <input type="text" class="code-box" maxlength="1" inputmode="numeric">
            <input type="text" class="code-box" maxlength="1" inputmode="numeric">
        </div>

        <input type="hidden" name="code" id="codeInput">

        <button type="submit" class="btn">Verify Account</button>
    </form>

    <a href="verify_account.php?resend=1" class="link">Resend verification code</a>
</div>

<script>
function startTimer(duration, display) {
    let timer = duration;
    const interval = setInterval(() => {
        const m = String(Math.floor(timer / 60)).padStart(2, '0');
        const s = String(timer % 60).padStart(2, '0');
        display.textContent = `Time remaining: ${m}:${s}`;

        if (--timer < 0) {
            clearInterval(interval);
            display.textContent = "Code expired!";
            display.classList.add("expired");
        }
    }, 1000);
}
window.onload = () => startTimer(900, document.getElementById('timer'));

document.addEventListener('DOMContentLoaded', () => {
    const boxes = document.querySelectorAll('.code-box');
    const hidden = document.getElementById('codeInput');
    const form = document.getElementById('verifyForm');

    boxes.forEach((box, index) => {
        box.addEventListener('input', () => {
            box.value = box.value.replace(/[^0-9]/g, '');
            if (box.value && index < 5) boxes[index + 1].focus();

            const code = Array.from(boxes).map(x => x.value).join('');
            hidden.value = code;

            if (code.length === 6) form.submit();
        });

        box.addEventListener('keydown', e => {
            if (e.key === 'Backspace' && !box.value && index > 0)
                boxes[index - 1].focus();
        });
    });
});
</script>

</body>
</html>
