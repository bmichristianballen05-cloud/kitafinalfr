<?php
require_once __DIR__ . '/db.php';
ensure_email_verification_schema();

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    header('Location: login.php?error=Please%20enter%20email%20and%20password');
    exit;
}

$user = find_user_by_email($email);
if (!$user || !password_verify($password, $user['password'] ?? '')) {
    header('Location: login.php?error=Invalid%20login%20details');
    exit;
}

unset($user['password']);
$_SESSION['user'] = $user;

header('Location: index.php?auth=1');
exit;

