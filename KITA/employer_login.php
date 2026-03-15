<?php
require_once __DIR__ . '/config.php';

if (isset($_SESSION['employer'])) {
    header('Location: index.php');
    exit;
}

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>KITA | Employer Login</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap');

        :root {
            --text: #e8fff4;
            --muted: #9ec5b4;
            --panel: rgba(13, 37, 29, 0.86);
            --line: rgba(109, 205, 150, 0.34);
            --input-bg: rgba(13, 31, 24, 0.84);
            --accent: #30d786;
            --accent2: #1aab66;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: "Manrope", sans-serif; }

        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 18px;
            color: var(--text);
            background:
                radial-gradient(circle at 14% 14%, rgba(48, 215, 134, 0.28), transparent 34%),
                radial-gradient(circle at 86% 86%, rgba(14, 122, 74, 0.26), transparent 35%),
                linear-gradient(180deg, #04120d 0%, #020907 100%);
        }

        .card {
            width: min(460px, 94vw);
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--line);
            background: var(--panel);
            box-shadow: 0 22px 46px rgba(0, 0, 0, 0.45);
            display: grid;
            gap: 14px;
        }

        h1 {
            font-size: 34px;
            line-height: 1;
            color: #8af2b8;
        }

        p {
            color: var(--muted);
            font-size: 14px;
        }

        .alert {
            border: 1px solid rgba(255, 145, 145, 0.35);
            background: rgba(122, 39, 39, 0.3);
            color: #ffc2c2;
            font-size: 13px;
            border-radius: 10px;
            padding: 10px 12px;
        }

        form {
            display: grid;
            gap: 10px;
        }

        input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 11px;
            background: var(--input-bg);
            color: var(--text);
            padding: 12px;
            font-size: 14px;
            outline: none;
        }

        input:focus {
            border-color: #63de9d;
            box-shadow: 0 0 0 3px rgba(99, 222, 157, 0.2);
        }

        button {
            border: 0;
            border-radius: 11px;
            background: linear-gradient(92deg, var(--accent) 0%, var(--accent2) 100%);
            color: #fff;
            font-size: 14px;
            font-weight: 800;
            padding: 12px;
            cursor: pointer;
        }

        .links {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .links a {
            color: #9af5c1;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <main class="card">
        <h1>Employer Login</h1>
        <p>Sign in to manage job listings and applications.</p>

        <?php if ($error !== ''): ?>
            <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form action="employer_login_process.php" method="post">
            <input type="email" name="email" placeholder="Work email" autocomplete="email" required />
            <input type="password" name="password" placeholder="Password" autocomplete="current-password" required />
            <button type="submit">Login as employer</button>
        </form>

        <div class="links">
            <a href="employer_register.php">Create employer account</a>
            <a href="login.php">Student login</a>
        </div>
    </main>
</body>
</html>
