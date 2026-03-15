<?php
$status = $_GET['status'] ?? '';
$message = $_GET['message'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Registration - KITA</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap');

        :root {
            --text: #e8ecf8;
            --muted: #a9bac0;
            --panel: rgba(18, 45, 42, 0.78);
            --panel-border: rgba(93, 177, 126, 0.30);
            --input-bg: rgba(21, 43, 40, 0.88);
            --input-border: rgba(112, 181, 136, 0.45);
            --accent: #2fcf79;
            --accent2: #1daa6c;
            --success-bg: rgba(25, 94, 52, 0.45);
            --success-text: #9ff2bc;
            --error-bg: rgba(114, 40, 40, 0.45);
            --error-text: #ffbbbb;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Manrope', sans-serif;
        }

        body {
            min-height: 100vh;
            color: var(--text);
            background:
                radial-gradient(circle at 20% 15%, rgba(24, 160, 96, 0.30), transparent 38%),
                radial-gradient(circle at 80% 82%, rgba(31, 121, 79, 0.30), transparent 35%),
                linear-gradient(180deg, #04110f 0%, #020806 100%);
            display: grid;
            place-items: center;
            padding: 28px 16px;
        }

        .card {
            width: 100%;
            max-width: 760px;
            border-radius: 22px;
            padding: 28px;
            border: 1px solid var(--panel-border);
            background: var(--panel);
            backdrop-filter: blur(14px);
            box-shadow: 0 22px 55px rgba(0, 0, 0, 0.52);
        }

        .head {
            margin-bottom: 18px;
        }

        .tag {
            display: inline-block;
            background: rgba(47, 207, 121, 0.2);
            border: 1px solid rgba(112, 181, 136, 0.45);
            color: #bff4d2;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.3px;
            margin-bottom: 10px;
        }

        h1 {
            font-size: clamp(28px, 5vw, 40px);
            line-height: 1.05;
            margin-bottom: 8px;
            background: linear-gradient(92deg, #8bf3a6 0%, #39d98a 48%, #66f0dc 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .sub {
            color: var(--muted);
            line-height: 1.5;
            font-size: 14px;
        }

        .alert {
            border-radius: 10px;
            padding: 10px 12px;
            margin: 10px 0 16px;
            font-size: 13px;
            border: 1px solid transparent;
        }

        .alert.success {
            background: var(--success-bg);
            border-color: rgba(122, 229, 162, 0.35);
            color: var(--success-text);
        }

        .alert.error {
            background: var(--error-bg);
            border-color: rgba(255, 156, 156, 0.35);
            color: var(--error-text);
        }

        form {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .full {
            grid-column: 1 / -1;
        }

        input,
        select {
            width: 100%;
            border: 1px solid var(--input-border);
            border-radius: 12px;
            background: var(--input-bg);
            color: var(--text);
            padding: 12px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus,
        select:focus {
            border-color: #4fd48b;
            box-shadow: 0 0 0 3px rgba(79, 212, 139, 0.24);
        }

        input::placeholder {
            color: #91b4a4;
        }

        .actions {
            grid-column: 1 / -1;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 4px;
        }

        button,
        .ghost {
            border: none;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }

        button {
            background: linear-gradient(92deg, var(--accent) 0%, var(--accent2) 100%);
            color: white;
        }

        .ghost {
            border: 1px solid var(--input-border);
            background: rgba(21, 43, 40, 0.58);
            color: var(--text);
        }

        .foot {
            margin-top: 14px;
            font-size: 13px;
            color: var(--muted);
        }

        .foot a {
            color: #8ef5a9;
            font-weight: 600;
            text-decoration: none;
        }

        @media (max-width: 720px) {
            .card {
                padding: 22px 16px;
            }

            form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="head">
            <span class="tag">FOR EMPLOYERS</span>
            <h1>Register your company</h1>
            <p class="sub">Create an employer account to post opportunities and connect with student talent.</p>
        </div>

        <?php if ($message !== ''): ?>
            <p class="alert <?php echo $status === 'ok' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <form action="employer_register_process.php" method="POST">
            <input type="text" name="company_name" placeholder="Company name" class="full" required maxlength="120">
            <input type="text" name="contact_name" placeholder="Contact person" required maxlength="100">
            <input type="email" name="email" placeholder="Work email" required maxlength="140">

            <input type="text" name="industry" list="industry-list" placeholder="Industry" required maxlength="100">
            <datalist id="industry-list">
                <option value="Small Business / MSME">
                <option value="Sari-Sari Store / Neighborhood Retail">
                <option value="Online Shop / E-commerce Seller">
                <option value="Food Stall / Carinderia">
                <option value="Home-based Services">
                <option value="Information Technology">
                <option value="Business Process Outsourcing">
                <option value="Banking and Finance">
                <option value="Retail and E-commerce">
                <option value="Education">
                <option value="Healthcare">
                <option value="Manufacturing">
                <option value="Construction">
                <option value="Hospitality and Tourism">
                <option value="Logistics and Supply Chain">
                <option value="Telecommunications">
                <option value="Media and Advertising">
            </datalist>

            <select name="company_size" required>
                <option value="">Company size</option>
                <option value="1-5">1-5 employees (Micro / Small Business)</option>
                <option value="1-10">1-10 employees</option>
                <option value="11-50">11-50 employees</option>
                <option value="51-200">51-200 employees</option>
                <option value="201-500">201-500 employees</option>
                <option value="501-1000">501-1000 employees</option>
                <option value="1000+">1000+ employees</option>
            </select>

            <input type="text" name="location" placeholder="Company location" required maxlength="120">
            <input type="text" name="phone" placeholder="Contact number (optional)" maxlength="30">
            <input type="url" name="website" placeholder="Company website (optional)">

            <input type="password" name="password" placeholder="Create password" class="full" minlength="6" required>

            <div class="actions">
                <button type="submit">Create employer account</button>
                <a class="ghost" href="employer_login.php">Login as employer</a>
                <a class="ghost" href="login.php">Back to login</a>
            </div>
        </form>

        <p class="foot">Job seeker? <a href="register.php">Register as student</a></p>
    </main>
</body>
</html>
