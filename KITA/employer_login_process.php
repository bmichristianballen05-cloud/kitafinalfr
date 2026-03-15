<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: employer_login.php?error=Please%20submit%20the%20login%20form');
    exit;
}

$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    header('Location: employer_login.php?error=Please%20enter%20email%20and%20password');
    exit;
}

$conn = db();
$stmt = $conn->prepare('SELECT id, company_name, contact_name, email, password FROM employers WHERE email = ? LIMIT 1');
if (!$stmt) {
    header('Location: employer_login.php?error=Database%20error');
    exit;
}

$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$employer = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$employer || !password_verify($password, (string) ($employer['password'] ?? ''))) {
    header('Location: employer_login.php?error=Invalid%20employer%20login%20details');
    exit;
}

unset($employer['password']);
$_SESSION['employer'] = [
    'id' => (int) ($employer['id'] ?? 0),
    'company_name' => (string) ($employer['company_name'] ?? ''),
    'contact_name' => (string) ($employer['contact_name'] ?? ''),
    'email' => (string) ($employer['email'] ?? ''),
];

header('Location: index.php');
exit;
