<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'employer_config.php';

function redirect_with_message(string $status, string $message): void
{
    $query = http_build_query([
        'status' => $status,
        'message' => $message
    ]);
    header('Location: employer_register.php?' . $query);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('error', 'Please submit the employer registration form.');
}

$companyName = trim($_POST['company_name'] ?? '');
$contactName = trim($_POST['contact_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$industry = trim($_POST['industry'] ?? '');
$companySize = trim($_POST['company_size'] ?? '');
$location = trim($_POST['location'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$website = trim($_POST['website'] ?? '');
$rawPassword = $_POST['password'] ?? '';

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

$checkSql = "SELECT id FROM employers WHERE email = ? LIMIT 1";
$checkStmt = $conn->prepare($checkSql);
if (!$checkStmt) {
    redirect_with_message('error', 'Database error: ' . $conn->error);
}

$checkStmt->bind_param('s', $email);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
if ($checkResult->num_rows > 0) {
    $checkStmt->close();
    $conn->close();
    redirect_with_message('error', 'This work email is already registered as an employer account.');
}
$checkStmt->close();

$password = password_hash($rawPassword, PASSWORD_DEFAULT);
$insertSql = "INSERT INTO employers (company_name, contact_name, email, industry, company_size, location, phone, website, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($insertSql);

if (!$stmt) {
    redirect_with_message('error', 'Database error: ' . $conn->error);
}

$stmt->bind_param('sssssssss', $companyName, $contactName, $email, $industry, $companySize, $location, $phone, $website, $password);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    redirect_with_message('ok', 'Employer account created successfully. You can now log in once employer login is enabled.');
}

$error = $stmt->error;
$stmt->close();
$conn->close();
redirect_with_message('error', 'Registration failed: ' . $error);
?>