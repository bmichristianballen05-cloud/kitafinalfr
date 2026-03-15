<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php?error=Please%20log%20in%20first');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: jobs_all.php');
    exit;
}

$user   = $_SESSION['user'];
$userId = (int) ($user['user_id'] ?? 0);
$jobId  = (int) ($_POST['job_id'] ?? 0);

if ($userId <= 0 || $jobId <= 0) {
    header('Location: jobs_all.php?error=Invalid+request');
    exit;
}

$conn = db();

// Check the job exists
$checkStmt = $conn->prepare("SELECT job_id FROM jobs WHERE job_id = ? LIMIT 1");
if (!$checkStmt) { header 
    header('Location: jobs_all.php?error=Database+error');
    exit;
} 
$checkStmt->bind_param('i', $jobId);
$checkStmt->execute();
$checkRes = $checkStmt->get_result();
if (!$checkRes || $checkRes->num_rows === 0) {
    $checkStmt->close();
    header('Location: jobs_all.php?error=Job+not+found');
    exit;
}
$checkStmt->close();

// Check if already applied
$dupStmt = $conn->prepare("SELECT application_id FROM applications WHERE job_id = ? AND student_id = ? LIMIT 1");
if ($dupStmt) {
    $dupStmt->bind_param('ii', $jobId, $userId);
    $dupStmt->execute();
    $dupRes = $dupStmt->get_result();
    if ($dupRes && $dupRes->num_rows > 0) {
        $dupStmt->close();
        header('Location: jobs_all.php?notice=You+have+already+applied+for+this+job');
        exit;
    }
    $dupStmt->close();
}

// Insert application
$insStmt = $conn->prepare("INSERT INTO applications (job_id, student_id, status, applied_at) VALUES (?, ?, 'pending', NOW())");
if (!$insStmt) {
    header('Location: jobs_all.php?error=Could+not+submit+application');
    exit;
}
$insStmt->bind_param('ii', $jobId, $userId);
$ok = $insStmt->execute();
$insStmt->close();

if ($ok) {
    header('Location: jobs_all.php?notice=Application+submitted+successfully');
} else {
    header('Location: jobs_all.php?error=Could+not+submit+application');
}
exit;
