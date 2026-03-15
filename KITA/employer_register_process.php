<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function redirect_with_message(string $status, string $message): void
{
    $query = http_build_query([
        'status' => $status,
        'message' => $message,
    ]);
    header('Location: employer_register.php?' . $query);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('error', 'Please submit the employer registration form.');
}

$companyName = trim((string) ($_POST['company_name'] ?? ''));
$contactName = trim((string) ($_POST['contact_name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$industry = trim((string) ($_POST['industry'] ?? ''));
$companySize = trim((string) ($_POST['company_size'] ?? ''));
$location = trim((string) ($_POST['location'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$website = trim((string) ($_POST['website'] ?? ''));
$rawPassword = (string) ($_POST['password'] ?? '');

$validSizes = ['1-5', '1-10', '11-50', '51-200', '201-500', '501-1000', '1000+'];

if ($companyName === '' || $contactName === '' || $email === '' || $industry === '' || $companySize === '' || $location === '' || $rawPassword === '') {
    redirect_with_message('error', 'Please fill in all required fields.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_with_message('error', 'Please enter a valid work email.');
}
if (!in_array($companySize, $validSizes, true)) {
    redirect_with_message('error', 'Please select a valid company size.');
}
if (strlen($rawPassword) < 6) {
    redirect_with_message('error', 'Password must be at least 6 characters.');
}
if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
    redirect_with_message('error', 'Please enter a valid website URL.');
}

$conn = db();
$createTableSql = "
CREATE TABLE IF NOT EXISTS employers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(120) NOT NULL,
    contact_name VARCHAR(100) NOT NULL,
    email VARCHAR(140) NOT NULL UNIQUE,
    industry VARCHAR(100) NOT NULL,
    company_size VARCHAR(20) NOT NULL,
    location VARCHAR(120) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    website VARCHAR(255) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if (!$conn->query($createTableSql)) {
    redirect_with_message('error', 'Could not prepare employer table: ' . $conn->error);
}

$checkStmt = $conn->prepare("SELECT id FROM employers WHERE email = ? LIMIT 1");
if (!$checkStmt) {
    redirect_with_message('error', 'Database error: ' . $conn->error);
}
$checkStmt->bind_param('s', $email);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
if ($checkResult && $checkResult->num_rows > 0) {
    $checkStmt->close();
    redirect_with_message('error', 'This work email is already registered as an employer account.');
}
$checkStmt->close();

$password = password_hash($rawPassword, PASSWORD_DEFAULT);
$insertStmt = $conn->prepare(
    "INSERT INTO employers (company_name, contact_name, email, industry, company_size, location, phone, website, password)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
if (!$insertStmt) {
    redirect_with_message('error', 'Database error: ' . $conn->error);
}

$insertStmt->bind_param('sssssssss', $companyName, $contactName, $email, $industry, $companySize, $location, $phone, $website, $password);
if (!$insertStmt->execute()) {
    $error = $insertStmt->error;
    $insertStmt->close();
    redirect_with_message('error', 'Registration failed: ' . $error);
}
$insertStmt->close();

redirect_with_message('ok', 'Employer account created successfully.');
