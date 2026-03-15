<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
ensure_application_scheduling_schema();
ensure_employer_profile_schema();

$conn = db();
$employerSession = $_SESSION['employer'] ?? null;
$userSession = $_SESSION['user'] ?? null;

if (!$employerSession && !$userSession) {
    header('Location: employer_login.php?error=Please%20log%20in%20as%20an%20employer');
    exit;
}

$employerId = 0;
$displayName = 'Employer';

if (is_array($employerSession)) {
    $employerId = (int) ($employerSession['id'] ?? 0);
    $displayName = (string) (($employerSession['company_name'] ?? '') ?: ($employerSession['contact_name'] ?? 'Employer'));
} elseif (is_array($userSession)) {
    $employerId = (int) ($userSession['user_id'] ?? 0);
    $displayName = (string) (($userSession['username'] ?? '') ?: ($userSession['full_name'] ?? 'Employer'));
}

// Load full employer record (for profile display)
$employerRecord = [];
if ($employerId > 0 && is_array($employerSession)) {
    $epStmt = $conn->prepare("SELECT * FROM employers WHERE id = ? LIMIT 1");
    if ($epStmt) {
        $epStmt->bind_param('i', $employerId);
        $epStmt->execute();
        $epRes = $epStmt->get_result();
        if ($epRes) $employerRecord = $epRes->fetch_assoc() ?: [];
        $epStmt->close();
    }
}

$flash = '';
$flashType = 'ok';

// Handle employer profile photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'employer_photo_update' && $employerId > 0 && is_array($employerSession)) {
    if (!isset($_FILES['employer_picture']) || (int)($_FILES['employer_picture']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $flash = 'Please choose a valid image file.'; $flashType = 'error';
    } else {
        $file = $_FILES['employer_picture'];
        $mime = function_exists('mime_content_type') ? (string)mime_content_type((string)($file['tmp_name'] ?? '')) : '';
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > 5 * 1024 * 1024 || !isset($allowed[$mime])) {
            $flash = 'Use JPG, PNG, WEBP, or GIF (max 5MB).'; $flashType = 'error';
        } else {
            $dir = __DIR__ . '/uploads/employer_pics';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $fname = 'e' . $employerId . '_' . time() . '_' . uniqid('', true) . '.' . $allowed[$mime];
            $target = $dir . '/' . $fname;
            if (!move_uploaded_file((string)($file['tmp_name'] ?? ''), $target)) {
                $flash = 'Could not save image.'; $flashType = 'error';
            } else {
                $webPath = 'uploads/employer_pics/' . $fname;
                $upStmt = $conn->prepare("UPDATE employers SET profile_picture = ? WHERE id = ? LIMIT 1");
                if ($upStmt) { $upStmt->bind_param('si', $webPath, $employerId); $upStmt->execute(); $upStmt->close(); }
                $_SESSION['employer']['profile_picture'] = $webPath;
                $flash = 'Profile picture updated.'; $flashType = 'ok';
            }
        }
    }
    header('Location: employer.php'); exit;
}

// Handle employer profile info update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'employer_profile_update' && $employerId > 0 && is_array($employerSession)) {
    $ep_company  = substr(trim((string)($_POST['company_name'] ?? '')), 0, 120);
    $ep_contact  = substr(trim((string)($_POST['contact_name'] ?? '')), 0, 100);
    $ep_industry = substr(trim((string)($_POST['industry'] ?? '')), 0, 100);
    $ep_location = substr(trim((string)($_POST['ep_location'] ?? '')), 0, 120);
    $ep_phone    = substr(trim((string)($_POST['phone'] ?? '')), 0, 30);
    $ep_website  = substr(trim((string)($_POST['website'] ?? '')), 0, 255);
    $ep_bio      = substr(trim((string)($_POST['employer_bio'] ?? '')), 0, 1000);

    $upStmt = $conn->prepare(
        "UPDATE employers SET company_name=?, contact_name=?, industry=?, location=?, phone=?, website=?, bio=? WHERE id=? LIMIT 1"
    );
    if ($upStmt) {
        $upStmt->bind_param('sssssssi', $ep_company, $ep_contact, $ep_industry, $ep_location, $ep_phone, $ep_website, $ep_bio, $employerId);
        $upStmt->execute();
        $upStmt->close();
        // Refresh session
        $reStmt = $conn->prepare("SELECT * FROM employers WHERE id = ? LIMIT 1");
        if ($reStmt) { $reStmt->bind_param('i', $employerId); $reStmt->execute(); $res = $reStmt->get_result(); if ($res) { $fresh = $res->fetch_assoc(); if ($fresh) { unset($fresh['password']); $_SESSION['employer'] = $fresh; } } $reStmt->close(); }
        $flash = 'Profile updated.'; $flashType = 'ok';
    } else {
        $flash = 'Could not update profile.'; $flashType = 'error';
    }
    header('Location: employer.php'); exit;
}

$hasSkillsRequired = false;
$skillsColumnCheck = $conn->query("SHOW COLUMNS FROM jobs LIKE 'skills_required'");
if ($skillsColumnCheck instanceof mysqli_result && $skillsColumnCheck->num_rows > 0) {
    $hasSkillsRequired = true;
} else {
    $hasSkillsRequired = $conn->query("ALTER TABLE jobs ADD COLUMN skills_required TEXT NULL AFTER strand_required") === true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_job') {
    $jobTitle = trim((string) ($_POST['job_title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $strandRequired = trim((string) ($_POST['strand_required'] ?? ''));
    $skillsRequired = trim((string) ($_POST['skills_required'] ?? ''));
    $location = trim((string) ($_POST['location'] ?? ''));
    $salary = trim((string) ($_POST['salary'] ?? ''));
    $jobType = trim((string) ($_POST['job_type'] ?? ''));

    $allowedTypes = ['part-time', 'full-time', 'internship', 'temporary'];

    if ($employerId <= 0) {
        $flash = 'Your account is missing a valid user ID.';
        $flashType = 'error';
    } elseif ($jobTitle === '' || $description === '' || $strandRequired === '' || $skillsRequired === '' || $location === '' || $salary === '' || $jobType === '') {
        $flash = 'Please fill in all job fields.';
        $flashType = 'error';
    } elseif (!$hasSkillsRequired) {
        $flash = 'Database error: jobs.skills_required column is missing.';
        $flashType = 'error';
    } elseif (!in_array($jobType, $allowedTypes, true)) {
        $flash = 'Please select a valid job type.';
        $flashType = 'error';
    } else {
        $insert = $conn->prepare(
            "INSERT INTO jobs (employer_id, job_title, description, strand_required, skills_required, location, salary, job_type, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        if (!$insert) {
            $flash = 'Database error: ' . $conn->error;
            $flashType = 'error';
        } else {
            $insert->bind_param('isssssss', $employerId, $jobTitle, $description, $strandRequired, $skillsRequired, $location, $salary, $jobType);
            if ($insert->execute()) {
                $flash = 'Job posted successfully.';
                $flashType = 'ok';
            } else {
                $flash = 'Could not post job: ' . $insert->error;
                $flashType = 'error';
            }
            $insert->close();
        }
    }
}

$jobs = [];
$jobStmt = $conn->prepare(
    "SELECT job_id, job_title, description, strand_required, skills_required, location, salary, job_type, created_at
     FROM jobs
     WHERE employer_id = ?
     ORDER BY created_at DESC
     LIMIT 100"
);
if ($jobStmt) {
    $jobStmt->bind_param('i', $employerId);
    $jobStmt->execute();
    $res = $jobStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $jobs[] = $row;
    }
    $jobStmt->close();
}

$applications = [];
$appStmt = $conn->prepare(
    "SELECT
        a.application_id,
        a.status,
        a.applied_at,
        a.interview_type,
        a.interview_datetime,
        a.interview_notes,
        j.job_title,
        u.username AS student_name,
        u.email AS student_email,
        u.strand AS student_strand
     FROM applications a
     INNER JOIN jobs j ON j.job_id = a.job_id
     LEFT JOIN users u ON u.user_id = a.student_id
     WHERE j.employer_id = ?
     ORDER BY a.applied_at DESC
     LIMIT 100"
);
if ($appStmt) {
    $appStmt->bind_param('i', $employerId);
    $appStmt->execute();
    $res = $appStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $applications[] = $row;
    }
    $appStmt->close();
}

// Fetch upcoming scheduled interviews for this employer
$scheduledInterviews = [];
$schStmt = $conn->prepare(
    "SELECT a.interview_datetime, a.interview_type, a.interview_notes, j.job_title, u.username AS student_name, u.email AS student_email
     FROM applications a
     INNER JOIN jobs j ON j.job_id = a.job_id
     LEFT JOIN users u ON u.user_id = a.student_id
     WHERE j.employer_id = ? AND a.status IN ('interview_scheduled', 'call_scheduled') AND a.interview_datetime >= NOW()
     ORDER BY a.interview_datetime ASC
     LIMIT 10"
);
if ($schStmt) {
    $schStmt->bind_param('i', $employerId);
    $schStmt->execute();
    $res = $schStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $scheduledInterviews[] = $row;
    }
    $schStmt->close();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>KITA | Employer</title>
    <style>
        :root {
            --bg: #0b1118;
            --panel: #121c28;
            --panel-2: #162334;
            --line: #28374c;
            --text: #e8f0ff;
            --muted: #9bb0c9;
            --accent: #28c17c;
            --danger: #ef4444;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 12% 5%, rgba(40, 193, 124, 0.14), transparent 28%),
                radial-gradient(circle at 92% 95%, rgba(59, 130, 246, 0.14), transparent 28%),
                var(--bg);
            padding: 20px;
        }

        .shell {
            max-width: 1180px;
            margin: 0 auto;
            display: grid;
            gap: 14px;
        }

        .topbar {
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--panel);
            padding: 12px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .title h1 {
            font-size: 22px;
            line-height: 1.1;
            margin-bottom: 4px;
        }

        .title p {
            font-size: 13px;
            color: var(--muted);
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .pill {
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 7px 10px;
            font-size: 12px;
            color: var(--muted);
            background: var(--panel-2);
        }

        .btn {
            border: 0;
            border-radius: 10px;
            padding: 9px 12px;
            background: var(--accent);
            color: #fff;
            text-decoration: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
        }

        .btn.alt {
            background: #334155;
        }

        .alert {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 13px;
            background: var(--panel);
        }

        .alert.ok { border-color: rgba(40, 193, 124, 0.35); color: #9ae6c0; }
        .alert.error { border-color: rgba(239, 68, 68, 0.35); color: #fca5a5; }

        .grid {
            display: grid;
            grid-template-columns: 380px minmax(0, 1fr);
            gap: 14px;
        }

        .card {
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--panel);
            padding: 12px;
        }

        .card h2 {
            font-size: 16px;
            margin-bottom: 10px;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .card-header h2 {
            margin: 0;
        }
        .form {
            display: grid;
            gap: 8px;
        }

        .form input,
        .form textarea,
        .form select {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 9px;
            background: var(--panel-2);
            color: var(--text);
            padding: 9px 10px;
            font-size: 13px;
            outline: none;
        }

        .form textarea { min-height: 112px; resize: vertical; }

        .form-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            margin-top: 2px;
        }

        .table-wrap {
            overflow: auto;
            border: 1px solid var(--line);
            border-radius: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 670px;
            font-size: 13px;
        }

        th, td {
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid var(--line);
            vertical-align: top;
        }

        th {
            color: var(--muted);
            font-weight: 600;
            background: #101a27;
        }

        td small {
            color: var(--muted);
            display: block;
            margin-top: 3px;
        }

        .status {
            display: inline-block;
            border-radius: 999px;
            padding: 3px 8px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .status.pending { background: rgba(250, 204, 21, 0.18); color: #fde68a; }
        .status.accepted { background: rgba(40, 193, 124, 0.2); color: #9ae6c0; }
        .status.rejected { background: rgba(239, 68, 68, 0.2); color: #fca5a5; }

        .empty {
            border: 1px dashed var(--line);
            border-radius: 10px;
            text-align: center;
            color: var(--muted);
            padding: 24px 14px;
            font-size: 13px;
        }

        .schedule-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .schedule-item {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 12px;
            background: var(--panel-2);
        }

        .schedule-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
        }

        .schedule-time {
            font-size: 12px;
            color: var(--accent);
            margin-bottom: 4px;
        }

        .schedule-notes {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 4px;
        }

        .schedule-contact {
            font-size: 12px;
            color: var(--muted);
        }

        @media (max-width: 980px) {
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <main class="shell">
        <header class="topbar">
            <div class="title">
                <h1>Employer Dashboard</h1>
                <p>Welcome, <?php echo e($displayName); ?>. Manage your listings and applicants.</p>
            </div>
            <div class="top-actions">
                <span class="pill">Employer ID: <?php echo $employerId; ?></span>
                <?php
                    $epPic = (string)(($employerSession['profile_picture'] ?? '') ?: ($employerRecord['profile_picture'] ?? ''));
                ?>
                <button class="btn alt" id="openEpProfileBtn" type="button" style="display:flex;align-items:center;gap:7px;">
                    <?php if ($epPic !== ''): ?>
                        <img src="<?php echo e($epPic); ?>" style="width:26px;height:26px;border-radius:50%;object-fit:cover;" alt="Photo" />
                    <?php else: ?>
                        <span style="width:26px;height:26px;border-radius:50%;background:#28c17c;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#000;"><?php echo e(strtoupper(substr($displayName, 0, 1))); ?></span>
                    <?php endif; ?>
                    My Profile
                </button>
                <a class="btn alt" href="employer_home.php">Employer Home</a>
                <a class="btn" href="logout.php">Log out</a>
            </div>
        </header>

        <?php if ($flash !== ''): ?>
            <div class="alert <?php echo $flashType === 'ok' ? 'ok' : 'error'; ?>">
                <?php echo e($flash); ?>
            </div>
        <?php endif; ?>

        <section class="grid">
            <article class="card">
                <h2>Post New Job</h2>
                <form class="form" method="post">
                    <input type="hidden" name="action" value="create_job" />
                    <input name="job_title" type="text" maxlength="150" placeholder="Job title" required />
                    <textarea name="description" placeholder="Job description" required></textarea>
                    <select name="strand_required" required>
                        <option value="">Select strand / course</option>
                        <option value="STEM">STEM</option>
                        <option value="ABM">ABM</option>
                        <option value="HUMSS">HUMSS</option>
                        <option value="GAS">GAS</option>
                        <option value="TVL">TVL</option>
                        <option value="ICT">ICT</option>
                        <option value="Sports">Sports</option>
                        <option value="Arts and Design">Arts and Design</option>
                        <option value="Any">Any / Open to all strands</option>
                    </select>
                    <input name="skills_required" type="text" maxlength="500" placeholder="Required skills (comma separated)" required />
                    <select name="location" required>
                        <option value="">Select location</option>
                        <optgroup label="NCR">
                            <option value="Quezon City, Metro Manila">Quezon City, Metro Manila</option>
                            <option value="Manila, Metro Manila">Manila, Metro Manila</option>
                            <option value="Makati, Metro Manila">Makati, Metro Manila</option>
                            <option value="Taguig, Metro Manila">Taguig, Metro Manila</option>
                            <option value="Pasig, Metro Manila">Pasig, Metro Manila</option>
                        </optgroup>
                        <optgroup label="CAR">
                            <option value="Baguio City, Benguet">Baguio City, Benguet</option>
                            <option value="La Trinidad, Benguet">La Trinidad, Benguet</option>
                            <option value="Tabuk City, Kalinga">Tabuk City, Kalinga</option>
                            <option value="Bangued, Abra">Bangued, Abra</option>
                        </optgroup>
                        <optgroup label="Region I">
                            <option value="Dagupan City, Pangasinan">Dagupan City, Pangasinan</option>
                            <option value="Urdaneta City, Pangasinan">Urdaneta City, Pangasinan</option>
                            <option value="Laoag City, Ilocos Norte">Laoag City, Ilocos Norte</option>
                            <option value="Vigan City, Ilocos Sur">Vigan City, Ilocos Sur</option>
                        </optgroup>
                        <optgroup label="Region III">
                            <option value="Angeles City, Pampanga">Angeles City, Pampanga</option>
                            <option value="San Fernando City, Pampanga">San Fernando City, Pampanga</option>
                            <option value="Cabanatuan City, Nueva Ecija">Cabanatuan City, Nueva Ecija</option>
                            <option value="Olongapo City, Zambales">Olongapo City, Zambales</option>
                        </optgroup>
                        <optgroup label="Region IV-A">
                            <option value="Antipolo City, Rizal">Antipolo City, Rizal</option>
                            <option value="Calamba City, Laguna">Calamba City, Laguna</option>
                            <option value="Santa Rosa City, Laguna">Santa Rosa City, Laguna</option>
                            <option value="Batangas City, Batangas">Batangas City, Batangas</option>
                            <option value="Bacoor City, Cavite">Bacoor City, Cavite</option>
                        </optgroup>
                        <optgroup label="Region V">
                            <option value="Naga City, Camarines Sur">Naga City, Camarines Sur</option>
                            <option value="Legazpi City, Albay">Legazpi City, Albay</option>
                            <option value="Sorsogon City, Sorsogon">Sorsogon City, Sorsogon</option>
                        </optgroup>
                        <optgroup label="Region VI">
                            <option value="Iloilo City, Iloilo">Iloilo City, Iloilo</option>
                            <option value="Bacolod City, Negros Occidental">Bacolod City, Negros Occidental</option>
                            <option value="Roxas City, Capiz">Roxas City, Capiz</option>
                        </optgroup>
                        <optgroup label="Region VII">
                            <option value="Cebu City, Cebu">Cebu City, Cebu</option>
                            <option value="Mandaue City, Cebu">Mandaue City, Cebu</option>
                            <option value="Lapu-Lapu City, Cebu">Lapu-Lapu City, Cebu</option>
                            <option value="Tagbilaran City, Bohol">Tagbilaran City, Bohol</option>
                        </optgroup>
                        <optgroup label="Region VIII">
                            <option value="Tacloban City, Leyte">Tacloban City, Leyte</option>
                            <option value="Ormoc City, Leyte">Ormoc City, Leyte</option>
                            <option value="Catbalogan City, Samar">Catbalogan City, Samar</option>
                        </optgroup>
                        <optgroup label="Region IX">
                            <option value="Zamboanga City, Zamboanga del Sur">Zamboanga City, Zamboanga del Sur</option>
                            <option value="Pagadian City, Zamboanga del Sur">Pagadian City, Zamboanga del Sur</option>
                            <option value="Dipolog City, Zamboanga del Norte">Dipolog City, Zamboanga del Norte</option>
                        </optgroup>
                        <optgroup label="Region X">
                            <option value="Cagayan de Oro City, Misamis Oriental">Cagayan de Oro City, Misamis Oriental</option>
                            <option value="Iligan City, Lanao del Norte">Iligan City, Lanao del Norte</option>
                            <option value="Valencia City, Bukidnon">Valencia City, Bukidnon</option>
                        </optgroup>
                        <optgroup label="Region XI">
                            <option value="Davao City, Davao del Sur">Davao City, Davao del Sur</option>
                            <option value="Tagum City, Davao del Norte">Tagum City, Davao del Norte</option>
                            <option value="Panabo City, Davao del Norte">Panabo City, Davao del Norte</option>
                        </optgroup>
                        <optgroup label="Region XII">
                            <option value="General Santos City, South Cotabato">General Santos City, South Cotabato</option>
                            <option value="Koronadal City, South Cotabato">Koronadal City, South Cotabato</option>
                            <option value="Kidapawan City, Cotabato">Kidapawan City, Cotabato</option>
                        </optgroup>
                        <optgroup label="Other">
                            <option value="Remote / Work from Home">Remote / Work from Home</option>
                        </optgroup>
                    </select>
                    <input name="salary" type="text" maxlength="50" placeholder="Salary (e.g., PHP 18,000 - 24,000)" required />
                    <select name="job_type" required>
                        <option value="">Select job type</option>
                        <option value="internship">Internship</option>
                        <option value="part-time">Part-time</option>
                        <option value="full-time">Full-time</option>
                        <option value="temporary">Temporary</option>
                    </select>
                    <div class="form-actions">
                        <button class="btn" type="submit">Publish Job</button>
                    </div>
                </form>
            </article>

            <article class="card">
                <h2>My Job Listings (<?php echo count($jobs); ?>)</h2>
                <?php if (!$jobs): ?>
                    <div class="empty">No jobs posted yet.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Job</th>
                                    <th>Strand</th>
                                    <th>Skills</th>
                                    <th>Location</th>
                                    <th>Type</th>
                                    <th>Salary</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jobs as $job): ?>
                                    <tr>
                                        <td>
                                            <?php echo e((string) $job['job_title']); ?>
                                            <small><?php echo e((string) $job['description']); ?></small>
                                        </td>
                                        <td><?php echo e((string) $job['strand_required']); ?></td>
                                        <td><?php echo e((string) ($job['skills_required'] ?? '')); ?></td>
                                        <td><?php echo e((string) $job['location']); ?></td>
                                        <td><?php echo e((string) $job['job_type']); ?></td>
                                        <td><?php echo e((string) $job['salary']); ?></td>
                                        <td><?php echo e((string) $job['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>

            <article class="card">
                <div class="card-header">
                    <h2>Upcoming Interviews (<?php echo count($scheduledInterviews); ?>)</h2>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <a href="schedule.php" class="btn alt small">Calendar</a>
                        <a href="schedule.php" class="btn alt small">View All</a>
                    </div>
                </div>
                <?php if (!$scheduledInterviews): ?>
                    <div class="empty">No upcoming interviews scheduled.</div>
                <?php else: ?>
                    <div class="schedule-list">
                        <?php foreach ($scheduledInterviews as $interview): ?>
                        <div class="schedule-item">
                            <div class="schedule-title"><?php echo e($interview['job_title']); ?> - <?php echo e($interview['student_name']); ?></div>
                            <div class="schedule-time"><?php echo date('M j, Y g:i A', strtotime($interview['interview_datetime'])); ?> - <?php echo ucfirst(e($interview['interview_type'])); ?></div>
                            <?php if (!empty($interview['interview_notes'])): ?>
                            <div class="schedule-notes"><?php echo e($interview['interview_notes']); ?></div>
                            <?php endif; ?>
                            <div class="schedule-contact">Contact: <?php echo e($interview['student_email']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </section>

        <section class="card">
            <h2>Recent Applications (<?php echo count($applications); ?>)</h2>
            <?php if (!empty($_GET['notice'])): ?>
                <div style="background:#1a3a2a;color:#4ade80;border:1px solid #4ade80;border-radius:8px;padding:10px 16px;margin-bottom:12px;">
                    <?php echo e((string) $_GET['notice']); ?>
                </div>
            <?php elseif (!empty($_GET['error'])): ?>
                <div style="background:#3a1a1a;color:#f87171;border:1px solid #f87171;border-radius:8px;padding:10px 16px;margin-bottom:12px;">
                    <?php echo e((string) $_GET['error']); ?>
                </div>
            <?php endif; ?>
            <?php if (!$applications): ?>
                <div class="empty">No applications yet.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Applicant</th>
                                <th>Job</th>
                                <th>Status</th>
                                <th>Applied</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <?php
                                    $status = strtolower((string) ($app['status'] ?? 'pending'));
                                    $appId  = (int) $app['application_id'];
                                    $hasSchedule = !empty($app['interview_datetime']);
                                ?>
                                <tr>
                                    <td>
                                        <?php echo e((string) ($app['student_name'] ?: 'Unknown User')); ?>
                                        <small><?php echo e((string) ($app['student_email'] ?: '')); ?> <?php echo e((string) ($app['student_strand'] ?: '')); ?></small>
                                    </td>
                                    <td><?php echo e((string) $app['job_title']); ?></td>
                                    <td>
                                        <span class="status <?php echo e($status); ?>"><?php echo e(str_replace('_', ' ', (string) $app['status'])); ?></span>
                                        <?php if ($hasSchedule): ?>
                                            <br><small><?php echo e(ucfirst((string) ($app['interview_type'] ?? ''))); ?>: <?php echo e((string) $app['interview_datetime']); ?></small>
                                            <?php if (!empty($app['interview_notes'])): ?>
                                                <br><small><?php echo e((string) $app['interview_notes']); ?></small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo e((string) $app['applied_at']); ?></td>
                                    <td>
                                        <button class="btn" style="font-size:0.8rem;padding:4px 10px;" onclick="openScheduleModal(<?php echo $appId; ?>)">
                                            <?php echo $hasSchedule ? 'Reschedule' : 'Schedule'; ?>
                                        </button>
                                        <?php if ($hasSchedule): ?>
                                            <form method="post" action="application_action.php" style="display:inline;">
                                                <input type="hidden" name="application_id" value="<?php echo $appId; ?>" />
                                                <input type="hidden" name="action" value="cancel_schedule" />
                                                <button class="btn" style="font-size:0.8rem;padding:4px 10px;background:#c0392b;" type="submit" onclick="return confirm('Cancel this appointment?')">Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- Schedule Modal -->
    <div id="scheduleModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:1000;align-items:center;justify-content:center;">
        <div style="background:#1e2a38;border-radius:12px;padding:28px 32px;min-width:340px;max-width:480px;width:90%;">
            <h3 style="margin:0 0 18px;">Schedule Appointment</h3>
            <form method="post" action="application_action.php">
                <input type="hidden" name="action" value="schedule" />
                <input type="hidden" name="application_id" id="modalAppId" value="" />
                <div style="margin-bottom:14px;">
                    <label style="display:block;margin-bottom:6px;font-size:0.9rem;">Appointment Type</label>
                    <select name="interview_type" required style="width:100%;padding:8px 12px;border-radius:8px;background:#0f1923;border:1px solid #2d3f50;color:#e2e8f0;">
                        <option value="interview">Interview (in-person / video)</option>
                        <option value="call">Phone / Online Call</option>
                    </select>
                </div>
                <div style="margin-bottom:14px;">
                    <label style="display:block;margin-bottom:6px;font-size:0.9rem;">Date & Time</label>
                    <input type="datetime-local" id="interviewDatetime" name="interview_datetime" required min="" style="width:100%;padding:8px 12px;border-radius:8px;background:#0f1923;border:1px solid #2d3f50;color:#e2e8f0;" />
                </div>
                <div style="margin-bottom:18px;">
                    <label style="display:block;margin-bottom:6px;font-size:0.9rem;">Notes / Instructions (optional)</label>
                    <textarea name="interview_notes" rows="3" placeholder="e.g. Zoom link, address, what to bring..." style="width:100%;padding:8px 12px;border-radius:8px;background:#0f1923;border:1px solid #2d3f50;color:#e2e8f0;resize:vertical;"></textarea>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" class="btn" onclick="closeScheduleModal()" style="background:#2d3f50;">Cancel</button>
                    <button type="submit" class="btn" style="background:#22c55e;color:#000;">Confirm</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Employer Profile Modal -->
    <?php
        $ep = array_merge($employerRecord, is_array($employerSession) ? $employerSession : []);
        $epCompanyName  = (string)($ep['company_name'] ?? $displayName);
        $epContactName  = (string)($ep['contact_name'] ?? '');
        $epIndustry     = (string)($ep['industry'] ?? '');
        $epEpLocation   = (string)($ep['location'] ?? '');
        $epPhone        = (string)($ep['phone'] ?? '');
        $epWebsite      = (string)($ep['website'] ?? '');
        $epBio          = (string)($ep['bio'] ?? '');
        $epEmail        = (string)($ep['email'] ?? '');
        $epProfilePic   = (string)($ep['profile_picture'] ?? '');
    ?>
    <div id="epProfileModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:2000;align-items:flex-start;justify-content:center;padding:30px 16px;overflow-y:auto;">
        <div style="background:#121c28;border:1px solid #28374c;border-radius:16px;padding:28px 32px;width:100%;max-width:540px;margin:auto;position:relative;">
            <h3 style="margin:0 0 20px;font-size:18px;">My Employer Profile</h3>

            <!-- Profile Picture Section -->
            <div style="display:flex;align-items:center;gap:16px;margin-bottom:22px;">
                <?php if ($epProfilePic !== ''): ?>
                    <img src="<?php echo e($epProfilePic); ?>" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid #28374c;" alt="Company photo" />
                <?php else: ?>
                    <div style="width:80px;height:80px;border-radius:50%;background:#28c17c;display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:800;color:#000;flex-shrink:0;"><?php echo e(strtoupper(substr($epCompanyName, 0, 1))); ?></div>
                <?php endif; ?>
                <div>
                    <div style="font-size:16px;font-weight:700;"><?php echo e($epCompanyName); ?></div>
                    <div style="font-size:12px;color:#9bb0c9;margin-top:2px;"><?php echo e($epEmail); ?></div>
                    <button class="btn" onclick="openEpPhotoModal()" type="button" style="margin-top:8px;font-size:12px;padding:5px 12px;background:#334155;">📷 Change Photo</button>
                </div>
            </div>

            <!-- Profile Edit Form -->
            <form method="post" action="employer.php" id="epProfileForm" style="display:grid;gap:12px;">
                <input type="hidden" name="action" value="employer_profile_update" />
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label style="display:block;font-size:12px;color:#9bb0c9;margin-bottom:4px;">Company Name</label>
                        <input type="text" name="company_name" value="<?php echo e($epCompanyName); ?>" maxlength="120" required style="width:100%;padding:8px 10px;border-radius:8px;background:#0f1923;border:1px solid #28374c;color:#e8f0ff;font-size:13px;" />
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;color:#9bb0c9;margin-bottom:4px;">Contact Name</label>
                        <input type="text" name="contact_name" value="<?php echo e($epContactName); ?>" maxlength="100" style="width:100%;padding:8px 10px;border-radius:8px;background:#0f1923;border:1px solid #28374c;color:#e8f0ff;font-size:13px;" />
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label style="display:block;font-size:12px;color:#9bb0c9;margin-bottom:4px;">Industry</label>
                        <input type="text" name="industry" value="<?php echo e($epIndustry); ?>" maxlength="100" style="width:100%;padding:8px 10px;border-radius:8px;background:#0f1923;border:1px solid #28374c;color:#e8f0ff;font-size:13px;" />
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;color:#9bb0c9;margin-bottom:4px;">Phone</label>
                        <input type="text" name="phone" value="<?php echo e($epPhone); ?>" maxlength="30" style="width:100%;padding:8px 10px;border-radius:8px;background:#0f1923;border:1px solid #28374c;color:#e8f0ff;font-size:13px;" />
                    </div>
                </div>
                <div>
                    <label style="display:block;font-size:12px;color:#9bb0c9;margin-bottom:4px;">Location</label>
                    <input type="text" name="ep_location" value="<?php echo e($epEpLocation); ?>" maxlength="120" style="width:100%;padding:8px 10px;border-radius:8px;background:#0f1923;border:1px solid #28374c;color:#e8f0ff;font-size:13px;" />
                </div>
                <div>
                    <label style="display:block;font-size:12px;color:#9bb0c9;margin-bottom:4px;">Website</label>
                    <input type="url" name="website" value="<?php echo e($epWebsite); ?>" maxlength="255" placeholder="https://example.com" style="width:100%;padding:8px 10px;border-radius:8px;background:#0f1923;border:1px solid #28374c;color:#e8f0ff;font-size:13px;" />
                </div>
                <div>
                    <label style="display:block;font-size:12px;color:#9bb0c9;margin-bottom:4px;">About / Bio</label>
                    <textarea name="employer_bio" rows="3" maxlength="1000" placeholder="Describe your company..." style="width:100%;padding:8px 10px;border-radius:8px;background:#0f1923;border:1px solid #28374c;color:#e8f0ff;font-size:13px;resize:vertical;"><?php echo e($epBio); ?></textarea>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" class="btn" onclick="closeEpProfileModal()" style="background:#334155;">Close</button>
                    <button type="submit" class="btn" style="background:#28c17c;color:#000;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Employer Photo Upload Modal -->
    <div id="epPhotoModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:3000;align-items:center;justify-content:center;">
        <div style="background:#121c28;border:1px solid #28374c;border-radius:14px;padding:26px;max-width:380px;width:90%;">
            <h3 style="margin:0 0 14px;">Change Profile Picture</h3>
            <p style="font-size:12px;color:#9bb0c9;margin-bottom:14px;">Upload JPG, PNG, WEBP or GIF. Max 5MB.</p>
            <form method="post" action="employer.php" enctype="multipart/form-data" style="display:grid;gap:12px;">
                <input type="hidden" name="action" value="employer_photo_update" />
                <input type="file" name="employer_picture" accept="image/*" required id="epPhotoInput" style="padding:8px;border-radius:8px;background:#0f1923;border:1px solid #28374c;color:#e8f0ff;width:100%;" />
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" class="btn" onclick="closeEpPhotoModal()" style="background:#334155;">Cancel</button>
                    <button type="submit" class="btn" style="background:#28c17c;color:#000;">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openScheduleModal(appId) {
            document.getElementById('modalAppId').value = appId;
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            const localNow = now.toISOString().slice(0, 16);
            const dtInput = document.getElementById('interviewDatetime');
            dtInput.min = localNow;
            dtInput.value = localNow;
            const modal = document.getElementById('scheduleModal');
            modal.style.display = 'flex';
        }
        function closeScheduleModal() {
            document.getElementById('scheduleModal').style.display = 'none';
        }
        document.getElementById('scheduleModal').addEventListener('click', function(e) {
            if (e.target === this) closeScheduleModal();
        });

        function openEpProfileModal() {
            document.getElementById('epProfileModal').style.display = 'flex';
        }
        function closeEpProfileModal() {
            document.getElementById('epProfileModal').style.display = 'none';
        }
        document.getElementById('epProfileModal').addEventListener('click', function(e) {
            if (e.target === this) closeEpProfileModal();
        });
        document.getElementById('openEpProfileBtn')?.addEventListener('click', openEpProfileModal);

        function openEpPhotoModal() {
            document.getElementById('epPhotoModal').style.display = 'flex';
        }
        function closeEpPhotoModal() {
            document.getElementById('epPhotoModal').style.display = 'none';
        }
        document.getElementById('epPhotoModal').addEventListener('click', function(e) {
            if (e.target === this) closeEpPhotoModal();
        });
    </script>
</body>
</html>
