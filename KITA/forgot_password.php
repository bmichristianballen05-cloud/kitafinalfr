<?php
require_once __DIR__ . '/db.php';

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($email === '' || $password === '' || $confirmPassword === '') {
        $error = 'Please complete all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $user = find_user_by_email($email);
        if (!$user) {
            $error = 'No account found for that email.';
        } else {
            $updated = update_user_password_by_email($email, password_hash($password, PASSWORD_DEFAULT));
            if (!$updated) {
                $error = 'Could not reset password right now. Please try again.';
            } else {
                header('Location: login.php?notice=' . urlencode('Password reset successful. Please log in.'));
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>KITA | Reset Password</title>
    <link rel="stylesheet" href="style.css" />
    <style>
        .reset-wrap {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 20px;
            background: radial-gradient(circle at 50% 30%, rgba(0, 255, 127, 0.1), transparent 55%), #0b1210;
        }

        .reset-card {
            width: min(440px, 92vw);
            background: #151d19;
            border: 1px solid rgba(43, 153, 98, 0.45);
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 16px 50px rgba(0, 255, 127, 0.18);
        }

        .reset-card h1 {
            margin: 0 0 6px;
            color: #00ff7f;
            font-size: 32px;
            text-align: center;
        }

        .reset-sub {
            margin: 0 0 16px;
            color: #9fb2a6;
            text-align: center;
            font-size: 13px;
        }

        .reset-card label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #b6ccc0;
            margin: 12px 0 6px;
        }

        .reset-card input {
            width: 100%;
            min-height: 46px;
            border-radius: 10px;
            border: 1px solid #6c8377;
            background: rgba(0, 0, 0, 0.14);
            color: #dbf4e6;
            padding: 0 12px;
            font-size: 14px;
            outline: none;
        }

        .reset-card input:focus {
            border-color: #00ff7f;
        }

        .reset-error {
            margin-bottom: 10px;
            border: 1px solid rgba(255, 126, 126, 0.4);
            background: rgba(255, 126, 126, 0.1);
            color: #ffadad;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 12px;
        }

        .reset-actions {
            margin-top: 16px;
            display: grid;
            gap: 8px;
        }

        .reset-btn {
            min-height: 46px;
            border-radius: 10px;
            border: 1px solid #00ff7f;
            background: transparent;
            color: #00ff7f;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }

        .reset-btn:hover {
            background: #00ff7f;
            color: #0f1a14;
        }

        .reset-link {
            text-align: center;
            color: #90a59a;
            font-size: 13px;
            text-decoration: none;
        }

        .reset-link:hover {
            color: #00ff7f;
        }
    </style>
</head>
<body>
    <div class="reset-wrap">
        <form class="reset-card" method="post" action="forgot_password.php">
            <h1>KITA</h1>
            <p class="reset-sub">Reset your account password</p>

            <?php if ($error !== ''): ?>
                <div class="reset-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <label for="email">Email Address</label>
            <input id="email" name="email" type="email" required />

            <label for="password">New Password</label>
            <input id="password" name="password" type="password" minlength="6" required />

            <label for="confirm_password">Confirm Password</label>
            <input id="confirm_password" name="confirm_password" type="password" minlength="6" required />

            <div class="reset-actions">
                <button class="reset-btn" type="submit">Reset Password</button>
                <a class="reset-link" href="login.php">Back to login</a>
            </div>
        </form>
    </div>
</body>
</html>
