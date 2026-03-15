<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
ensure_application_scheduling_schema();

// Only employers can use this
if (!isset($_SESSION['employer'])) {
    header('Location: employer_login.php?error=Please+log+in+as+employer');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: employer.php');
    exit;
}

$employer   = $_SESSION['employer'];
$employerId = (int) ($employer['id'] ?? 0);
$appId      = (int) ($_POST['application_id'] ?? 0);
$action     = trim((string) ($_POST['action'] ?? ''));

if ($employerId <= 0 || $appId <= 0) {
    header('Location: employer.php?error=Invalid+request');
    exit;
}

$conn = db();

// Verify the application belongs to a job owned by this employer
$verifyStmt = $conn->prepare(
    "SELECT a.application_id FROM applications a
     INNER JOIN jobs j ON j.job_id = a.job_id
     WHERE a.application_id = ? AND j.employer_id = ?
     LIMIT 1"
);
if (!$verifyStmt) {
    header('Location: employer.php?error=Database+error');
    exit;
}
$verifyStmt->bind_param('ii', $appId, $employerId);
$verifyStmt->execute();
$verifyRes = $verifyStmt->get_result();
if (!$verifyRes || $verifyRes->num_rows === 0) {
    $verifyStmt->close();
    header('Location: employer.php?error=Application+not+found');
    exit;
}
$verifyStmt->close();

if ($action === 'schedule') {
    $interviewType     = trim((string) ($_POST['interview_type'] ?? ''));
    $interviewDatetime = trim((string) ($_POST['interview_datetime'] ?? ''));
    $interviewNotes    = trim((string) ($_POST['interview_notes'] ?? ''));

    if (!in_array($interviewType, ['interview', 'call'], true)) {
        header('Location: employer.php?error=Invalid+appointment+type');
        exit;
    }
    if ($interviewDatetime === '') {
        header('Location: employer.php?error=Please+select+a+date+and+time');
        exit;
    }

    $newStatus = $interviewType === 'interview' ? 'interview_scheduled' : 'call_scheduled';

    // Fetch student_id and job title/company for notification
    $infoStmt = $conn->prepare(
        "SELECT a.student_id, j.job_title, e.company_name
         FROM applications a
         INNER JOIN jobs j ON j.job_id = a.job_id
         LEFT JOIN employers e ON e.id = j.employer_id
         WHERE a.application_id = ? LIMIT 1"
    );
    $studentId = 0; $jobTitle = ''; $companyName = '';
    if ($infoStmt) {
        $infoStmt->bind_param('i', $appId);
        $infoStmt->execute();
        $infoRes = $infoStmt->get_result();
        if ($infoRes && ($infoRow = $infoRes->fetch_assoc())) {
            $studentId   = (int) $infoRow['student_id'];
            $jobTitle    = (string) ($infoRow['job_title'] ?? '');
            $companyName = (string) ($infoRow['company_name'] ?? '');
        }
        $infoStmt->close();
    }

    $updStmt = $conn->prepare(
        "UPDATE applications
         SET status = ?, interview_type = ?, interview_datetime = ?, interview_notes = ?
         WHERE application_id = ?
         LIMIT 1"
    );
    if (!$updStmt) {
        header('Location: employer.php?error=Database+error');
        exit;
    }
    $updStmt->bind_param('ssssi', $newStatus, $interviewType, $interviewDatetime, $interviewNotes, $appId);
    $ok = $updStmt->execute();
    $updStmt->close();

    if ($ok && $studentId > 0) {
        $typeLabel   = $interviewType === 'interview' ? 'Interview' : 'Phone / Online Call';
        $nTitle      = '📅 Appointment Scheduled';
        $nBody       = "Your application for \"{$jobTitle}\" at {$companyName} has been scheduled.\nType: {$typeLabel}\nDate/Time: {$interviewDatetime}" . ($interviewNotes !== '' ? "\nNotes: {$interviewNotes}" : '');
        $nData       = json_encode(['application_id' => $appId, 'interview_datetime' => $interviewDatetime, 'interview_type' => $interviewType]);
        $extKey      = "appt_{$appId}";
        $notifStmt   = $conn->prepare(
            "INSERT INTO notifications (user_id, type, title, body, data_json, external_key, is_read)
             VALUES (?, 'appointment_scheduled', ?, ?, ?, ?, 0)
             ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body), data_json=VALUES(data_json), is_read=0, created_at=CURRENT_TIMESTAMP"
        );
        if ($notifStmt) {
            $notifStmt->bind_param('issss', $studentId, $nTitle, $nBody, $nData, $extKey);
            $notifStmt->execute();
            $notifStmt->close();
        }
    }

    if ($ok) {
        header('Location: employer.php?notice=Appointment+scheduled+successfully');
    } else {
        header('Location: employer.php?error=Could+not+schedule+appointment');
    }
    exit;
}

if ($action === 'cancel_schedule') {
    $updStmt = $conn->prepare(
        "UPDATE applications
         SET status = 'pending', interview_type = NULL, interview_datetime = NULL, interview_notes = NULL
         WHERE application_id = ?
         LIMIT 1"
    );
    if (!$updStmt) {
        header('Location: employer.php?error=Database+error');
        exit;
    }
    $updStmt->bind_param('i', $appId);
    $ok = $updStmt->execute();
    $updStmt->close();
    header('Location: employer.php?notice=Appointment+cancelled');
    exit;
}

header('Location: employer.php?error=Unknown+action');
exit;
