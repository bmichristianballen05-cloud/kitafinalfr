<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/db.php';
ensure_email_verification_schema();

function redirect_with_message(string $status, string $message): void
{
    $query = http_build_query([
        'status' => $status,
        'message' => $message,
    ]);
    header('Location: register.php?' . $query);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('error', 'Please submit the form to create an account.');
}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$rawPassword = $_POST['password'] ?? '';
$location = trim($_POST['location'] ?? '');
$strand = trim($_POST['strand'] ?? '');
$skillsInput = $_POST['skills'] ?? '';

if ($username === '' || $email === '' || $rawPassword === '' || $location === '' || $strand === '') {
    redirect_with_message('error', 'Please fill in all fields.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_with_message('error', 'Please enter a valid email address.');
}

if (!preg_match('/^[a-zA-Z0-9._]{3,30}$/', $username)) {
    redirect_with_message('error', 'Username must be 3-30 characters and use only letters, numbers, dot or underscore.');
}

if (strlen($rawPassword) < 6) {
    redirect_with_message('error', 'Password must be at least 6 characters.');
}

if (strlen($location) > 120) {
    redirect_with_message('error', 'Location is too long.');
}

$skills = [];
if (is_array($skillsInput)) {
    foreach ($skillsInput as $item) {
        $skill = trim((string) $item);
        if ($skill !== '') {
            $skills[] = preg_replace('/\s+/', ' ', $skill);
        }
    }
} else {
    $raw = trim((string) $skillsInput);
    if ($raw !== '') {
        foreach (explode(',', $raw) as $part) {
            $skill = trim($part);
            if ($skill !== '') {
                $skills[] = preg_replace('/\s+/', ' ', $skill);
            }
        }
    }
}
$skills = array_values(array_unique($skills));

if (find_user_by_email($email) || find_user_by_username($username)) {
    redirect_with_message('error', 'Email or username is already registered.');
}

$result = create_user([
    'username' => $username,
    'email' => $email,
    'password_hash' => password_hash($rawPassword, PASSWORD_DEFAULT),
    'role' => 'student',
    'strand' => $strand,
    'location' => $location,
]);

if (!($result['ok'] ?? false)) {
    $error = (string) ($result['error'] ?? 'Unknown database error.');
    redirect_with_message('error', 'Registration failed: ' . $error);
}

$newUserId = (int) ($result['user_id'] ?? 0);
if ($newUserId > 0) {
    save_user_skills($newUserId, $skills);
}

$newUser = find_user_by_email($email);
if ($newUser) {
    unset($newUser['password']);
    $_SESSION['user'] = $newUser;
    header('Location: index.php?auth=1');
    exit();
}

header('Location: login.php?error=Registration%20succeeded%20but%20auto-login%20failed.%20Please%20log%20in.');
exit();
