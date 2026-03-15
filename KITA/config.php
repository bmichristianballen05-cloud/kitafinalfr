<?php
declare(strict_types=1);

session_start();

define('BASE_PATH', __DIR__);

/*
 * Database settings
 * Change these if your MySQL setup is different.
 */
define('DB_HOST', getenv('KITA_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int) (getenv('KITA_DB_PORT') ?: 3307));
define('DB_USER', getenv('KITA_DB_USER') ?: 'root');
define('DB_PASS', getenv('KITA_DB_PASS') ?: '');
define('DB_NAME', getenv('KITA_DB_NAME') ?: 'kita');

/*
 * SMTP / Email settings
 * Use a Gmail account with an App Password (not your regular password).
 * Generate an App Password at: https://myaccount.google.com/apppasswords
 */
define('SMTP_HOST',     getenv('KITA_SMTP_HOST')     ?: 'smtp.gmail.com');
define('SMTP_PORT',     (int)(getenv('KITA_SMTP_PORT')     ?: 587));
define('SMTP_USER',     getenv('KITA_SMTP_USER')     ?: 'your_gmail@gmail.com');
define('SMTP_PASS',     getenv('KITA_SMTP_PASS')     ?: 'your_app_password');
define('SMTP_FROM',     getenv('KITA_SMTP_FROM')     ?: 'your_gmail@gmail.com');
define('SMTP_FROM_NAME', getenv('KITA_SMTP_FROM_NAME') ?: 'KITA');

/**
 * Returns a shared mysqli connection.
 */
function db(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_errno) {
        http_response_code(500);
        die('Database connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

/*
 * Backward-compatible variable for older files still using $conn.
 */
$conn = db();
