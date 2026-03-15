<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
ensure_email_verification_schema();

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$email = trim((string) ($_GET['email'] ?? $_POST['email'] ?? ''));
$notice = trim((string) ($_GET['notice'] ?? ''));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? 'verify'));
    $email = trim((string) ($_POST['email'] ?? ''));
    $user = find_user_by_email($email);

    if (!$user) {
        $error = 'Account not found for this email.';
    } else {
        $userId = (int) ($user['user_id'] ?? 0);
        if ($action === 'resend') {
            $code = create_email_verification_code($userId, (string) ($user['email'] ?? $email));
            $sent = $code ? send_verification_email((string) ($user['email'] ?? $email), (string) ($user['username'] ?? 'KITA User'), $code) : false;
            if (PHP_SAPI !== 'cli' && in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true)) {
                $_SESSION['kita_dev_verify'] = [
                    'email' => (string) ($user['email'] ?? $email),
                    'code' => $code
                ];
            } else {
                unset($_SESSION['kita_dev_verify']);
            }
            $notice = $sent ? 'Verification code sent to your email.' : 'Email sending is not configured. Use local dev code below.';
        } else {
            $codeInput = trim((string) ($_POST['code'] ?? ''));
            if ($codeInput === '') {
                $error = 'Enter your verification code.';
            } elseif (!verify_email_code($userId, $codeInput)) {
                $error = 'Invalid or expired verification code.';
            } else {
                $fresh = find_user_by_id($userId);
                if (!$fresh) {
                    $error = 'Verified, but account reload failed. Please login.';
                } else {
                    unset($fresh['password']);
                    $_SESSION['user'] = $fresh;
                    unset($_SESSION['kita_dev_verify']);
                    header('Location: index.php?auth=1');
                    exit;
                }
            }
        }
    }
}

$devCode = '';
$dev = $_SESSION['kita_dev_verify'] ?? null;
if (is_array($dev) && strtolower((string) ($dev['email'] ?? '')) === strtolower($email)) {
    $devCode = (string) ($dev['code'] ?? '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>KITA | Verify Email</title>
    <link rel="stylesheet" href="style.css" />
    <style>
        body { min-height: 100vh; display: grid; place-items: center; background: #07120f; color: #d9e8df; font-family: "Segoe UI", Tahoma, sans-serif; padding: 16px; }
        .card { width: min(460px, 92vw); background: rgba(8, 18, 14, 0.95); border: 1px solid #1b3a2c; border-radius: 14px; padding: 22px; box-shadow: 0 14px 40px rgba(0, 0, 0, 0.35); }
        h1 { margin: 0 0 8px; color: #00ff7f; font-size: 30px; text-align: center; }
        p { margin: 0 0 16px; color: #a9beb3; text-align: center; font-size: 13px; }
        label { display: block; font-size: 12px; color: #b8cdc2; margin: 10px 0 6px; }
        input { width: 100%; border: 1px solid #3f5b4f; background: rgba(0,0,0,0.25); color: #e2f3ea; border-radius: 8px; padding: 10px 12px; font-size: 14px; outline: none; }
        .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 14px; }
        button { border: 1px solid #00ff7f; background: transparent; color: #00ff7f; border-radius: 8px; padding: 10px 12px; font-weight: 700; cursor: pointer; }
        button.primary { background: #00ff7f; color: #04250f; }
        .msg { border-radius: 8px; padding: 10px; font-size: 13px; margin-bottom: 10px; }
        .error { background: rgba(239, 68, 68, 0.16); border: 1px solid rgba(239, 68, 68, 0.45); color: #fecaca; }
        .notice { background: rgba(34, 197, 94, 0.12); border: 1px solid rgba(34, 197, 94, 0.45); color: #b7f5cb; }
        .dev { margin-top: 10px; font-size: 12px; color: #e9d78f; background: rgba(255, 202, 40, 0.12); border: 1px solid rgba(255, 202, 40, 0.35); border-radius: 8px; padding: 8px; }
        .links { text-align: center; margin-top: 12px; font-size: 13px; }
        .links a { color: #8fd5af; text-decoration: none; }
    </style>
</head>
<body>
    <form class="card" method="post" action="verify_email.php">
        <h1>Verify Email</h1>
        <p>Enter the 6-digit code sent to your email before continuing.</p>

        <?php if ($error !== ''): ?>
            <div class="msg error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($notice !== ''): ?>
            <div class="msg notice"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <label>Email</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required />

        <label>Verification code</label>
        <input type="text" name="code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" />

        <div class="actions">
            <button class="primary" type="submit" name="action" value="verify">Verify</button>
            <button type="submit" name="action" value="resend">Resend code</button>
        </div>

        <?php if ($devCode !== ''): ?>
            <div class="dev">Local dev code: <strong><?php echo htmlspecialchars($devCode, ENT_QUOTES, 'UTF-8'); ?></strong></div>
        <?php endif; ?>

        <div class="links"><a href="login.php">Back to login</a></div>
    </form>
</body>
</html>

