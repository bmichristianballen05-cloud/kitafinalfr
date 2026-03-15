<?php
require_once __DIR__ . '/config.php';

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = $_GET['error'] ?? '';
$notice = $_GET['notice'] ?? '';
$skipLoader = ($error !== '' || $notice !== '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>KITA | Login</title>
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        .login-page #form-ui {
            opacity: 0;
            transform: translateY(14px) scale(0.985);
            transition: opacity 420ms ease, transform 420ms ease;
        }

        .login-page.ready #form-ui {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        .login-loader {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: grid;
            place-items: center;
            cursor: pointer;
            background:
                radial-gradient(circle at 50% 35%, rgba(0, 255, 127, 0.14), transparent 55%),
                linear-gradient(180deg, #07120f 0%, #040908 100%);
            transition: opacity 360ms ease, visibility 360ms ease;
        }

        .login-loader.hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .loader-core {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: min(360px, 90vw);
            display: grid;
            place-items: center;
            gap: 14px;
            text-align: center;
        }

        .loader-logo-wrap {
            width: 190px;
            height: 190px;
            position: relative;
            display: grid;
            place-items: center;
        }

        .loader-logo {
            width: 150px;
            height: 150px;
            border-radius: 34px;
            object-fit: cover;
            box-shadow: 0 12px 30px rgba(0, 255, 127, 0.35);
            animation:
                logoPop 1.4s cubic-bezier(0.2, 0.9, 0.2, 1) both,
                logoBob 2.8s ease-in-out infinite 1.4s,
                logoGlow 3.4s ease-in-out infinite 1.4s;
        }

        .loader-sparkle {
            position: absolute;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #ffe08a;
            box-shadow: 0 0 14px rgba(255, 224, 138, 0.7);
            animation: sparklePop 2.2s ease-in-out infinite;
        }

        .loader-sparkle.s1 { top: 16px; right: 18px; animation-delay: -0.6s; }
        .loader-sparkle.s2 { bottom: 20px; left: 14px; animation-delay: -1.2s; }
        .loader-sparkle.s3 { top: 32px; left: 22px; animation-delay: -1.8s; }

        .loader-heart {
            position: absolute;
            width: 10px;
            height: 10px;
            background: #ff8fb1;
            transform: rotate(45deg);
            animation: heartFloat 3s ease-in-out infinite;
            opacity: 0.9;
        }

        .loader-heart::before,
        .loader-heart::after {
            content: "";
            position: absolute;
            width: 10px;
            height: 10px;
            background: #ff8fb1;
            border-radius: 50%;
        }

        .loader-heart::before { left: -5px; top: 0; }
        .loader-heart::after { left: 0; top: -5px; }

        .loader-heart.h1 { right: 10px; bottom: 34px; animation-delay: -0.8s; }
        .loader-heart.h2 { left: 18px; top: 20px; animation-delay: -1.6s; }

        .loader-text {
            font-size: 13px;
            letter-spacing: 0.16em;
            color: #b9d2c5;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .loader-hint {
            font-size: 12px;
            color: #8cb39f;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            opacity: 0.68;
            transition: opacity 260ms ease, color 260ms ease;
        }

        @keyframes logoBob {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-8px) scale(1.03); }
        }

        @keyframes logoPop {
            0% { transform: translateY(18px) scale(0.65); opacity: 0; }
            55% { transform: translateY(-6px) scale(1.05); opacity: 1; }
            80% { transform: translateY(2px) scale(0.98); }
            100% { transform: translateY(0) scale(1); opacity: 1; }
        }

        @keyframes logoGlow {
            0%, 100% { box-shadow: 0 12px 30px rgba(0, 255, 127, 0.28); }
            50% { box-shadow: 0 18px 38px rgba(0, 255, 127, 0.5); }
        }

        @keyframes sparklePop {
            0%, 100% { transform: scale(0.6); opacity: 0.45; }
            50% { transform: scale(1.2); opacity: 1; }
        }

        @keyframes heartFloat {
            0%, 100% { transform: translateY(0) rotate(45deg) scale(0.9); opacity: 0.7; }
            50% { transform: translateY(-10px) rotate(45deg) scale(1.1); opacity: 1; }
        }

        .login-page {
            overflow-y: auto;
            padding: 16px 0;
        }

        .login-page #form {
            width: min(460px, 92vw);
            min-height: 690px;
            height: auto;
            display: block;
            padding: 34px 27px 28px;
            border-radius: 14px;
            background: linear-gradient(180deg, rgba(22, 22, 22, 0.98), rgba(14, 22, 18, 0.96));
            box-shadow: 0 18px 64px rgba(0, 255, 127, 0.22);
        }

        .login-page #form-body {
            position: static;
            width: 100%;
            margin: 0;
        }

        .login-page .kita-caption {
            margin-top: 8px;
            color: #9fb2a6;
            font-size: 13px;
            text-align: center;
        }

        .login-page .field-label {
            display: block;
            margin-bottom: 8px;
            color: #b6ccc0;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .login-page .form-inp {
            min-height: 50px;
            margin-bottom: 13px;
            border-color: #92a79b;
            background: rgba(0, 0, 0, 0.08);
        }

        .login-page .form-inp input {
            font-size: 15px;
            line-height: 1.35;
            border-radius: 6px;
            -webkit-appearance: none;
            appearance: none;
        }

        .login-page .form-inp input:-webkit-autofill,
        .login-page .form-inp input:-webkit-autofill:hover,
        .login-page .form-inp input:-webkit-autofill:focus,
        .login-page .form-inp input:-webkit-autofill:active {
            -webkit-text-fill-color: #00ff7f;
            caret-color: #00ff7f;
            -webkit-box-shadow: 0 0 0 1000px rgba(8, 14, 12, 0.92) inset;
            box-shadow: 0 0 0 1000px rgba(8, 14, 12, 0.92) inset;
            border-radius: 6px;
            transition: background-color 9999s ease-in-out 0s;
        }

        .login-page .password-wrap {
            width: 100%;
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 8px;
        }

        .login-page .password-toggle {
            border: 0;
            background: transparent;
            color: #a6b9ad;
            cursor: pointer;
            font-size: 16px;
            padding: 2px 4px;
        }

        .login-page .auth-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 2px;
            margin-bottom: 8px;
            color: #a1b4a8;
            font-size: 13px;
        }

        .login-page .remember-wrap {
            display: inline-flex;
            gap: 8px;
            align-items: center;
            cursor: pointer;
        }

        .login-page .remember-wrap input {
            accent-color: #00ff7f;
        }

        .login-page .auth-row a {
            color: #93a89c;
            text-decoration: none;
        }

        .login-page .auth-row a:hover {
            color: #00ff7f;
        }

        .login-page #submit-button-cvr {
            margin-top: 14px;
        }

        .login-page #submit-button {
            min-height: 50px;
            font-size: 17px;
            border-radius: 10px;
        }

        .login-page .divider-note {
            margin-top: 12px;
            text-align: center;
            color: #8ea399;
            font-size: 12px;
        }

        .login-page #input-area {
            margin-top: 40px;
        }

        .login-page #register-link {
            margin-top: 12px;
            text-align: center;
        }

        .login-page #register-link a {
            color: #00ff7f;
            font-size: 14px;
        }

        .login-page .login-error {
            margin-top: 14px;
            margin-bottom: 6px;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid rgba(255, 126, 126, 0.4);
            background: rgba(255, 126, 126, 0.09);
            font-size: 12px;
        }

        .login-page .login-notice {
            margin-top: 14px;
            margin-bottom: 6px;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid rgba(77, 196, 132, 0.45);
            background: rgba(41, 138, 93, 0.18);
            color: #a9f5c9;
            font-size: 12px;
        }

        @media (max-width: 520px) {
            .login-page {
                padding: 12px 0;
            }

            .login-page #form {
                min-height: 0;
                height: auto;
                padding: 26px 16px;
            }

            .login-page #form-body {
                width: 100%;
            }

            .login-page #welcome-line-1 {
                font-size: 48px;
            }

            .login-page #welcome-line-2 {
                font-size: 20px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .loader-logo,
            .loader-sparkle,
            .loader-heart,
            .loader-hint {
                animation: none;
            }
        }
    </style>
</head>
<body class="login-page<?php echo $skipLoader ? ' ready' : ''; ?>">
    <?php if (!$skipLoader): ?>
    <div class="login-loader" id="loginLoader" aria-hidden="true">
        <div class="loader-core">
            <div class="loader-logo-wrap" aria-hidden="true">
                <img class="loader-logo" src="uploads/kita_logo.png" alt="" />
                <span class="loader-sparkle s1"></span>
                <span class="loader-sparkle s2"></span>
                <span class="loader-sparkle s3"></span>
                <span class="loader-heart h1"></span>
                <span class="loader-heart h2"></span>
            </div>
            <div class="loader-text">Loading KITA</div>
            <div class="loader-hint" id="loaderHint"></div>
        </div>
    </div>
    <?php endif; ?>
    <div class="login-bg-fly" aria-hidden="true">
        <img class="fly-logo fly-1" src="uploads/kita_logo.png" alt="" />
        <img class="fly-logo fly-2" src="uploads/kita_logo.png" alt="" />
        <img class="fly-logo fly-3" src="uploads/kita_logo.png" alt="" />
        <img class="fly-logo fly-4" src="uploads/kita_logo.png" alt="" />
        <img class="fly-logo fly-5" src="uploads/kita_logo.png" alt="" />
        <img class="fly-logo fly-6" src="uploads/kita_logo.png" alt="" />
    </div>
    <div id="form-ui">
        <form action="login_process.php" method="post" id="form">
            <div id="form-body">
                <div id="welcome-lines">
                    <div id="welcome-line-1">KITA</div>
                    <div id="welcome-line-2">Welcome Back</div>
                    <p class="kita-caption">Log in to continue your KITA journey.</p>
                </div>

                <?php if ($error): ?>
                    <div class="login-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($notice): ?>
                    <div class="login-notice"><?php echo htmlspecialchars($notice); ?></div>
                <?php endif; ?>

                <div id="input-area">
                    <label class="field-label" for="emailInput">Email Address</label>
                    <div class="form-inp">
                        <input id="emailInput" name="email" placeholder="your@email.com" type="email" autocomplete="email" required />
                    </div>
                    <label class="field-label" for="passwordInput">Password</label>
                    <div class="form-inp">
                        <div class="password-wrap">
                            <input id="passwordInput" name="password" placeholder="Enter password" type="password" autocomplete="current-password" required />
                            <button class="password-toggle" id="togglePasswordBtn" type="button" aria-label="Show password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="auth-row">
                        <label class="remember-wrap">
                            <input type="checkbox" name="remember" />
                            <span>Remember me</span>
                        </label>
                        <a href="forgot_password.php">Forgot password?</a>
                    </div>
                </div>
                <div id="submit-button-cvr">
                    <button id="submit-button" type="submit">Login</button>
                </div>
                <div class="divider-note">New to KITA?</div>
                <div id="register-link">
                    <a href="register.php">Create your account</a>
                </div>
                <div id="register-link">
                    <a href="employer_home.php">Employer homepage</a>
                </div>
                <div id="register-link">
                    <a href="employer_login.php">Login as employer</a>
                </div>
                <div id="bar"></div>
            </div>
        </form>
    </div>
    <script>
        (function () {
            const body = document.body;
            const loginLoader = document.getElementById("loginLoader");
            const loaderHint = document.getElementById("loaderHint");
            const emailInput = document.getElementById("emailInput");
            const passwordInput = document.getElementById("passwordInput");
            const togglePasswordBtn = document.getElementById("togglePasswordBtn");
            const welcomeLine = document.getElementById("welcome-line-2");
            let introDone = false;

            function revealLogin() {
                if (introDone) return;
                introDone = true;
                if (loginLoader) {
                    loginLoader.classList.add("hidden");
                    setTimeout(() => loginLoader.remove(), 420);
                }
                body.classList.add("ready");
            }

            if (!loginLoader) {
                body.classList.add("ready");
            } else {
                if (loaderHint) loaderHint.textContent = "Click to enter";
                loginLoader.addEventListener("click", revealLogin);
            }

            if (!emailInput || !welcomeLine) return;

            function toDisplayName(value) {
                const raw = (value || "").trim();
                if (!raw) return "";
                const head = raw.split("@")[0].trim();
                if (!head) return "";
                return head.replace(/[._-]+/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
            }

            function updateWelcomeFromEmail() {
                const name = toDisplayName(emailInput.value);
                welcomeLine.textContent = name ? `Welcome Back, ${name}` : "Welcome Back";
            }

            emailInput.addEventListener("input", updateWelcomeFromEmail);
            emailInput.addEventListener("change", updateWelcomeFromEmail);
            setTimeout(updateWelcomeFromEmail, 80);
            setTimeout(updateWelcomeFromEmail, 350);

            if (passwordInput && togglePasswordBtn) {
                togglePasswordBtn.addEventListener("click", () => {
                    const isPassword = passwordInput.type === "password";
                    passwordInput.type = isPassword ? "text" : "password";
                    togglePasswordBtn.innerHTML = isPassword
                        ? '<i class="fa-regular fa-eye-slash"></i>'
                        : '<i class="fa-regular fa-eye"></i>';
                    togglePasswordBtn.setAttribute("aria-label", isPassword ? "Hide password" : "Show password");
                });
            }
        })();
    </script>
</body>
</html>

