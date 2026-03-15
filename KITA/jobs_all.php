<?php
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php?error=Please%20log%20in%20first');
    exit;
}

$conn = db();
$user = $_SESSION['user'];
$userId = (int) ($user['user_id'] ?? 0);

$idColumn = users_id_column();
if ($userId > 0 && users_has_column($idColumn)) {
    $freshSql = "SELECT " . users_select_sql() . " FROM users WHERE `{$idColumn}` = ? LIMIT 1";
    $freshStmt = $conn->prepare($freshSql);
    if ($freshStmt) {
        $freshStmt->bind_param('i', $userId);
        $freshStmt->execute();
        $freshRes = $freshStmt->get_result();
        $freshUser = $freshRes ? $freshRes->fetch_assoc() : null;
        $freshStmt->close();
        if (is_array($freshUser)) {
            unset($freshUser['password']);
            $_SESSION['user'] = $freshUser;
            $user = $freshUser;
        }
    }
}

$userName = trim((string) (($user['username'] ?? '') ?: ($user['full_name'] ?? 'KITA Student')));
$userStrand = trim((string) ($user['strand'] ?? ''));
$userLocation = trim((string) ($user['location'] ?? ''));
$userBio = trim((string) ($user['bio'] ?? ''));
$userProfilePicture = trim((string) ($user['profile_picture'] ?? ''));

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function normalize_text(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/\s+/', ' ', $value);
    return (string) $value;
}

function excerpt_text(string $value, int $max = 140): string
{
    $value = trim((string) preg_replace('/\s+/', ' ', $value));
    if ($value === '' || strlen($value) <= $max) {
        return $value;
    }
    return rtrim(substr($value, 0, $max - 3)) . '...';
}

function table_exists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

$userSkills = [];
if ($userId > 0 && table_exists($conn, 'user_skills') && table_exists($conn, 'skills')) {
    $skillSql = "SELECT s.skill_name
                 FROM user_skills us
                 INNER JOIN skills s ON s.skill_id = us.skill_id
                 WHERE us.user_id = ?";
    $skillStmt = $conn->prepare($skillSql);
    if ($skillStmt) {
        $skillStmt->bind_param('i', $userId);
        $skillStmt->execute();
        $skillRes = $skillStmt->get_result();
        while ($skillRes && ($row = $skillRes->fetch_assoc())) {
            $skill = trim((string) ($row['skill_name'] ?? ''));
            if ($skill !== '') {
                $userSkills[] = $skill;
            }
        }
        $skillStmt->close();
    }
}

// Fetch job IDs the current user has already applied to, with status and schedule info
$appliedJobIds = [];
$appliedJobInfo = []; // keyed by job_id
if ($userId > 0 && table_exists($conn, 'applications')) {
    ensure_application_scheduling_schema();
    $appCheckStmt = $conn->prepare(
        "SELECT job_id, status, interview_type, interview_datetime, interview_notes
         FROM applications WHERE student_id = ?"
    );
    if ($appCheckStmt) {
        $appCheckStmt->bind_param('i', $userId);
        $appCheckStmt->execute();
        $appCheckRes = $appCheckStmt->get_result();
        while ($appCheckRes && ($appRow = $appCheckRes->fetch_assoc())) {
            $jid = (int) $appRow['job_id'];
            $appliedJobIds[] = $jid;
            $appliedJobInfo[$jid] = $appRow;
        }
        $appCheckStmt->close();
    }
}

$hasEmployers = table_exists($conn, 'employers');
$jobsSql = "SELECT j.job_id, j.employer_id, j.job_title, j.description, j.strand_required, j.skills_required, j.location, j.salary, j.job_type, j.created_at, u.full_name AS employer_user_name";
if ($hasEmployers) {
    $jobsSql .= ", e.company_name AS employer_company_name, e.profile_picture AS employer_pic, e.bio AS employer_bio, e.industry AS employer_industry, e.website AS employer_website, e.location AS employer_location, e.email AS employer_email";
}
$jobsSql .= " FROM jobs j LEFT JOIN users u ON u.user_id = j.employer_id";
if ($hasEmployers) {
    $jobsSql .= " LEFT JOIN employers e ON e.id = j.employer_id";
}
$jobsSql .= " ORDER BY j.created_at DESC LIMIT 300";

$jobRows = [];
$jobsRes = $conn->query($jobsSql);
if ($jobsRes) {
    while ($row = $jobsRes->fetch_assoc()) {
        $jobRows[] = $row;
    }
}

$recommendations = [];
$todayTs = time();
$userLocationNorm = normalize_text($userLocation);
$userStrandNorm = normalize_text($userStrand);
$userSkillNorm = array_map('normalize_text', $userSkills);

foreach ($jobRows as $row) {
    $title = trim((string) ($row['job_title'] ?? 'Untitled Job'));
    $description = trim((string) ($row['description'] ?? ''));
    $jobStrand = trim((string) ($row['strand_required'] ?? ''));
    $jobSkillsRequired = trim((string) ($row['skills_required'] ?? ''));
    $jobLocation = trim((string) ($row['location'] ?? ''));
    $jobType = trim((string) ($row['job_type'] ?? ''));
    $salary = trim((string) ($row['salary'] ?? ''));
    $createdAt = trim((string) ($row['created_at'] ?? ''));

    $companyName = trim((string) (($row['employer_company_name'] ?? '') ?: ($row['employer_user_name'] ?? '')));
    if ($companyName === '') {
        $companyName = 'Employer #' . (int) ($row['employer_id'] ?? 0);
    }

    $score = 35;
    $matchedSkills = [];

    $jobStrandNorm = normalize_text($jobStrand);
    if ($userStrandNorm !== '' && $jobStrandNorm !== '') {
        if ($userStrandNorm === $jobStrandNorm) {
            $score += 30;
        } elseif (str_contains($jobStrandNorm, $userStrandNorm) || str_contains($userStrandNorm, $jobStrandNorm)) {
            $score += 18;
        }
    } elseif ($jobStrandNorm === '') {
        $score += 6;
    }

    $jobLocationNorm = normalize_text($jobLocation);
    if ($userLocationNorm !== '' && $jobLocationNorm !== '') {
        if ($userLocationNorm === $jobLocationNorm) {
            $score += 20;
        } elseif (str_contains($jobLocationNorm, $userLocationNorm) || str_contains($userLocationNorm, $jobLocationNorm)) {
            $score += 12;
        }
    }

    $haystack = normalize_text($title . ' ' . $description . ' ' . $jobSkillsRequired);
    $jobSkillsNorm = normalize_text($jobSkillsRequired);
    foreach ($userSkillNorm as $i => $skillNorm) {
        if ($skillNorm === '') {
            continue;
        }
        if (str_contains($haystack, $skillNorm)) {
            $score += 8;
            if ($jobSkillsNorm !== '' && str_contains($jobSkillsNorm, $skillNorm)) {
                $score += 4;
            }
            $matchedSkills[] = $userSkills[$i] ?? $skillNorm;
            if (count($matchedSkills) >= 4) {
                break;
            }
        }
    }

    if ($jobType === 'internship' || $jobType === 'part-time') {
        $score += 6;
    }

    $jobTs = strtotime($createdAt);
    if ($jobTs !== false) {
        $ageDays = (int) floor(($todayTs - $jobTs) / 86400);
        if ($ageDays <= 3) {
            $score += 8;
        } elseif ($ageDays <= 10) {
            $score += 4;
        }
    }

    $score = max(0, min(99, $score));

    $tags = [];
    if ($jobType !== '') {
        $tags[] = ucfirst($jobType);
    }
    if ($jobStrand !== '') {
        $tags[] = $jobStrand;
    }
    if ($jobSkillsRequired !== '') {
        foreach (explode(',', $jobSkillsRequired) as $rawSkill) {
            $clean = trim($rawSkill);
            if ($clean !== '') {
                $tags[] = $clean;
            }
            if (count($tags) >= 7) {
                break;
            }
        }
    }
    foreach ($matchedSkills as $skill) {
        $tags[] = $skill;
    }
    if (empty($tags)) {
        $tags = ['General'];
    }

    $recommendations[] = [
        'job_id'           => (int) ($row['job_id'] ?? 0),
        'employer_id'      => (int) ($row['employer_id'] ?? 0),
        'title'            => $title,
        'company'          => $companyName,
        'employer_pic'     => trim((string) ($row['employer_pic'] ?? '')),
        'employer_bio'     => trim((string) ($row['employer_bio'] ?? '')),
        'employer_industry'=> trim((string) ($row['employer_industry'] ?? '')),
        'employer_website' => trim((string) ($row['employer_website'] ?? '')),
        'employer_location'=> trim((string) ($row['employer_location'] ?? '')),
        'employer_email'   => trim((string) ($row['employer_email'] ?? '')),
        'location'         => $jobLocation,
        'salary'           => $salary,
        'job_type'         => $jobType,
        'score'            => $score,
        'description'      => $description,
        'tags'             => array_values(array_unique($tags)),
    ];
}

usort($recommendations, static function (array $a, array $b): int {
    if ($a['score'] === $b['score']) {
        return $b['job_id'] <=> $a['job_id'];
    }
    return $b['score'] <=> $a['score'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>KITA | All Job Recommendations</title>
    <style>
        :root {
            --bg: #f4f6f8;
            --surface: #ffffff;
            --line: #d8dee5;
            --text: #1f2937;
            --muted: #6b7280;
            --brand: #0ea765;
            --brand-2: #0b8752;
            --shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body[data-theme="dark"] {
            --bg: #0f172a;
            --surface: #111827;
            --line: #334155;
            --text: #e5e7eb;
            --muted: #9ca3af;
            --brand: #22c55e;
            --brand-2: #16a34a;
            --shadow: 0 14px 30px rgba(2, 6, 23, 0.45);
        }

        body {
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background:
                radial-gradient(circle at 12% 8%, rgba(14, 167, 101, 0.12), transparent 35%),
                radial-gradient(circle at 88% 92%, rgba(16, 185, 129, 0.1), transparent 35%),
                var(--bg);
            color: var(--text);
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 20;
            background: var(--surface);
            border-bottom: 1px solid var(--line);
        }

        .topbar-inner {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 16px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            color: var(--text);
            text-decoration: none;
        }

        .brand-logo {
            width: 30px;
            height: 30px;
            object-fit: contain;
        }

        .search {
            flex: 1;
            max-width: 360px;
        }

        .search input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: #f3f6f9;
            color: var(--text);
            padding: 10px 14px;
            font-size: 13px;
            outline: none;
        }

        body[data-theme="dark"] .search input {
            background: #1f2937;
            color: var(--text);
        }

        .back-link {
            text-decoration: none;
            border-radius: 999px;
            padding: 8px 12px;
            color: var(--brand);
            font-size: 13px;
            font-weight: 700;
            border: 1px solid var(--brand);
            background: #eef9f3;
        }

        .back-link:hover {
            background: #e2f4ea;
        }

        body[data-theme="dark"] .back-link {
            background: rgba(34, 197, 94, 0.14);
        }

        .shell {
            max-width: 1100px;
            margin: 16px auto;
            padding: 0 16px 18px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 260px;
            gap: 16px;
        }

        .profile-card-top {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 18px;
        }

        .profile-card-top .profile-cover-wrap {
            position: relative;
            flex-shrink: 0;
        }

        .profile-card-top .avatar {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: #d5e9df;
            border: 3px solid #fff;
            display: grid;
            place-items: center;
            font-weight: 700;
            color: #245f47;
            overflow: hidden;
            flex-shrink: 0;
            margin-top: 0;
        }

        .profile-card-top .profile-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .profile-card-top .profile-tags {
            margin-top: 4px;
        }

        .panel {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--surface);
            box-shadow: var(--shadow);
        }

        .profile-card {
            padding: 14px;
            display: grid;
            gap: 10px;
            height: fit-content;
        }

        .profile-cover {
            height: 60px;
            border-radius: 8px;
            background: linear-gradient(120deg, #0ea765, #22c55e);
        }

        .avatar {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: #d5e9df;
            margin-top: -28px;
            border: 3px solid #fff;
            display: grid;
            place-items: center;
            font-weight: 700;
            color: #245f47;
            overflow: hidden;
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .profile-name {
            font-size: 16px;
            font-weight: 700;
        }

        .profile-sub {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.4;
        }

        .profile-meta {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.35;
        }

        .profile-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 2px;
        }

        .main-head {
            padding: 12px 14px;
            border-bottom: 1px solid var(--line);
        }

        .main-head h1 {
            font-size: 22px;
            line-height: 1.2;
            margin-bottom: 4px;
        }

        .main-head p {
            color: var(--muted);
            font-size: 13px;
        }

        .jobs-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            padding: 12px;
        }

        .job-card {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--surface);
            padding: 14px;
            display: grid;
            gap: 8px;
        }

        .job-head {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: start;
        }

        .job-head h2 {
            font-size: 16px;
            line-height: 1.25;
        }

        .company {
            color: var(--brand);
            font-size: 13px;
            margin-top: 2px;
            font-weight: 600;
        }

        .match {
            font-size: 12px;
            font-weight: 700;
            color: #11643f;
            border: 1px solid rgba(14, 167, 101, 0.35);
            background: rgba(14, 167, 101, 0.12);
            border-radius: 999px;
            padding: 3px 8px;
            white-space: nowrap;
        }

        .meta {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }

        .tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .tag {
            font-size: 12px;
            background: #eef9f3;
            color: #245f47;
            border-radius: 999px;
            padding: 4px 8px;
        }

        .job-actions {
            display: flex;
            gap: 8px;
            margin-top: 2px;
        }

        .btn {
            border: 1px solid var(--brand);
            color: var(--brand);
            border-radius: 999px;
            padding: 6px 11px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            background: #fff;
        }

        .btn.primary {
            background: var(--brand);
            color: #fff;
        }

        body[data-theme="dark"] .btn {
            background: transparent;
        }

        .empty {
            margin: 12px;
            border: 1px dashed var(--line);
            border-radius: 10px;
            color: var(--muted);
            padding: 22px 14px;
            text-align: center;
            font-size: 13px;
        }

        .right-card {
            padding: 12px;
            display: grid;
            gap: 8px;
            height: fit-content;
        }

        .right-card h3 {
            font-size: 15px;
        }

        .right-card p {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
        }

        @media (max-width: 900px) {
            .shell { grid-template-columns: 1fr; }
            .profile-card-top { flex-wrap: wrap; }
            .search { display: none; }
        }
    </style>
</head>
<body data-theme="light">
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="index.php">
                <img class="brand-logo" src="uploads/kita_logo.png" alt="KITA logo" />
                <span class="brand-word">KITA</span>
            </a>
            <div class="search">
                <input type="text" placeholder="Search jobs, companies, skills" />
            </div>
            <a class="back-link" href="index.php">Back to feed</a>
        </div>
    </header>

    <main class="shell">
        <aside class="panel profile-card-top">
            <div class="avatar">
                <?php if ($userProfilePicture !== ''): ?>
                    <img src="<?php echo e($userProfilePicture); ?>" alt="Profile picture" />
                <?php else: ?>
                    <?php echo e(strtoupper(substr($userName, 0, 1))); ?>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <div class="profile-name"><?php echo e($userName); ?></div>
                <div class="profile-sub"><?php echo e($userBio !== '' ? $userBio : 'Add a bio in your profile so recommendations can feel more personal.'); ?></div>
                <div class="profile-meta"><?php echo e($userLocation !== '' ? $userLocation : 'No location yet'); ?></div>
                <div class="profile-tags">
                    <?php if ($userStrand !== ''): ?><span class="tag"><?php echo e($userStrand); ?></span><?php endif; ?>
                    <?php if (count($userSkills) > 0): ?>
                        <?php foreach (array_slice($userSkills, 0, 4) as $skill): ?>
                            <span class="tag"><?php echo e($skill); ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="tag">No skills yet</span>
                    <?php endif; ?>
                </div>
            </div>
        </aside>

        <section class="panel">
            <?php if (!empty($_GET['notice'])): ?>
                <div style="background:#1a3a2a;color:#4ade80;border:1px solid #4ade80;border-radius:8px;padding:10px 16px;margin-bottom:12px;">
                    <?php echo htmlspecialchars((string) $_GET['notice'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php elseif (!empty($_GET['error'])): ?>
                <div style="background:#3a1a1a;color:#f87171;border:1px solid #f87171;border-radius:8px;padding:10px 16px;margin-bottom:12px;">
                    <?php echo htmlspecialchars((string) $_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <div class="main-head">
                <h1>All Job Recommendations</h1>
                <p><?php echo count($recommendations); ?> matched jobs from your database</p>
            </div>

            <?php if (empty($recommendations)): ?>
                <div class="empty">No jobs found yet. Ask an employer to post openings first.</div>
            <?php else: ?>
                <div class="jobs-grid">
                    <?php foreach ($recommendations as $job): ?>
                        <article class="job-card">
                            <div class="job-head">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <?php if ((string)$job['employer_pic'] !== ''): ?>
                                        <img src="<?php echo e((string)$job['employer_pic']); ?>" style="width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid #2d3f50;flex-shrink:0;" alt="Employer" />
                                    <?php else: ?>
                                        <div style="width:38px;height:38px;border-radius:50%;background:#28c17c;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;color:#000;flex-shrink:0;"><?php echo e(strtoupper(substr((string)$job['company'], 0, 1))); ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <button class="company" onclick="openEmployerModal(<?php echo (int)$job['employer_id']; ?>)" style="background:none;border:none;color:var(--brand);cursor:pointer;padding:0;font-size:13px;text-decoration:none;font-weight:600;display:block;margin-bottom:2px;"><?php echo e((string) $job['company']); ?></button>
                                        <h2 style="margin:0;"><?php echo e((string) $job['title']); ?></h2>
                                    </div>
                                </div>
                                <span class="match"><?php echo (int) $job['score']; ?>% match</span>
                            </div>
                            <p class="meta">
                                <?php echo e((string) (($job['location'] !== '' ? $job['location'] : 'Location N/A'))); ?>
                                <?php if ((string) $job['job_type'] !== ''): ?>
                                    · <?php echo e(ucfirst((string) $job['job_type'])); ?>
                                <?php endif; ?>
                                <?php if ((string) $job['salary'] !== ''): ?>
                                    · <?php echo e((string) $job['salary']); ?>
                                <?php endif; ?>
                            </p>
                            <?php if ((string) $job['description'] !== ''): ?>
                                <p class="meta"><?php echo e(excerpt_text((string) $job['description'], 140)); ?></p>
                            <?php endif; ?>
                            <div class="tags">
                                <?php foreach ($job['tags'] as $tag): ?>
                                    <span class="tag"><?php echo e((string) $tag); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php
                                $jid = (int) $job['job_id'];
                                $appInfo = $appliedJobInfo[$jid] ?? null;
                            ?>
                            <div class="job-actions">
                                <?php if (in_array($jid, $appliedJobIds, true)): ?>
                                    <span class="btn primary" style="opacity:0.6;cursor:default;">Applied</span>
                                <?php else: ?>
                                    <form method="post" action="apply.php" style="display:inline;">
                                        <input type="hidden" name="job_id" value="<?php echo (int) $job['job_id']; ?>" />
                                        <button class="btn primary" type="submit">Apply</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <?php if ($appInfo && !empty($appInfo['interview_datetime'])): ?>
                                <?php
                                    $typeLabel = ucfirst((string) ($appInfo['interview_type'] ?? 'appointment'));
                                    $dtRaw = (string) $appInfo['interview_datetime'];
                                    $dtFormatted = '';
                                    try {
                                        $dt = new DateTime($dtRaw);
                                        $dtFormatted = $dt->format('M j, Y \a\t g:i A');
                                    } catch (Exception $ex) {
                                        $dtFormatted = $dtRaw;
                                    }
                                ?>
                                <div style="margin-top:10px;background:#0f2237;border-left:3px solid #28c17c;border-radius:6px;padding:10px 14px;font-size:0.82rem;color:#c7d8eb;">
                                    <div style="font-weight:700;color:#28c17c;margin-bottom:4px;">?? Employer Response: <?php echo e($typeLabel); ?> Scheduled</div>
                                    <div><?php echo e($dtFormatted); ?></div>
                                    <?php if (!empty($appInfo['interview_notes'])): ?>
                                        <div style="margin-top:4px;color:#9bb0c9;"><?php echo e((string) $appInfo['interview_notes']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <aside class="panel right-card">
            <h3>How Matching Works</h3>
            <p>Higher score if strand matches the job requirement.</p>
            <p>Higher score if location is close or similar to your profile.</p>
            <p>Higher score if your saved skills appear in the employer's required skills.</p>
        </aside>
    </main>
    <!-- Employer Profile Modal -->
    <div id="employerModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:1000;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#111827;border:1px solid #1f3347;border-radius:16px;padding:0;max-width:460px;width:100%;overflow:hidden;">
            <!-- Banner + Avatar -->
            <div id="epBanner" style="height:80px;background:linear-gradient(135deg,#0e4d2e,#1a6b48);position:relative;">
                <div id="epAvatar" style="position:absolute;bottom:-28px;left:20px;width:60px;height:60px;border-radius:50%;border:3px solid #111827;overflow:hidden;background:#28c17c;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:800;color:#000;"></div>
            </div>
            <div style="padding:40px 20px 20px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:14px;">
                    <div>
                        <div id="epName" style="font-size:18px;font-weight:700;"></div>
                        <div id="epIndustry" style="font-size:12px;color:#9bb0c9;margin-top:2px;"></div>
                    </div>
                    <button onclick="closeEmployerModal()" style="background:#1f3347;border:none;color:#9bb0c9;border-radius:8px;padding:6px 12px;cursor:pointer;font-size:13px;">? Close</button>
                </div>
                <div id="epDetails" style="font-size:13px;color:#c7d8eb;display:grid;gap:6px;"></div>
                <div id="epBioSection" style="margin-top:14px;padding-top:14px;border-top:1px solid #1f3347;font-size:13px;color:#c7d8eb;display:none;">
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:#9bb0c9;margin-bottom:6px;">About</div>
                    <p id="epBio" style="line-height:1.6;"></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const storedTheme = localStorage.getItem('kita_theme');
            document.body.dataset.theme = storedTheme === 'dark' ? 'dark' : 'light';
        })();

        const employerData = <?php
            $epDataMap = [];
            foreach ($recommendations as $rec) {
                $eid = (int)$rec['employer_id'];
                if ($eid > 0 && !isset($epDataMap[$eid])) {
                    $epDataMap[$eid] = [
                        'name'     => $rec['company'],
                        'pic'      => $rec['employer_pic'],
                        'bio'      => $rec['employer_bio'],
                        'industry' => $rec['employer_industry'],
                        'website'  => $rec['employer_website'],
                        'location' => $rec['employer_location'],
                        'email'    => $rec['employer_email'],
                    ];
                }
            }
            echo json_encode($epDataMap, JSON_HEX_TAG | JSON_HEX_AMP);
        ?>;

        function openEmployerModal(eid) {
            const ep = employerData[eid];
            if (!ep) return;
            const modal = document.getElementById('employerModal');
            const avatar = document.getElementById('epAvatar');
            if (ep.pic) {
                avatar.innerHTML = `<img src="${ep.pic}" style="width:100%;height:100%;object-fit:cover;" />`;
            } else {
                avatar.textContent = (ep.name || '?')[0].toUpperCase();
            }
            document.getElementById('epName').textContent = ep.name || '';
            document.getElementById('epIndustry').textContent = ep.industry || '';
            const details = [];
            if (ep.location) details.push(`?? ${ep.location}`);
            if (ep.email) details.push(`?? ${ep.email}`);
            if (ep.website) details.push(`?? <a href="${ep.website}" target="_blank" rel="noopener" style="color:#28c17c;">${ep.website}</a>`);
            document.getElementById('epDetails').innerHTML = details.join('<br>');
            const bioSection = document.getElementById('epBioSection');
            if (ep.bio) {
                document.getElementById('epBio').textContent = ep.bio;
                bioSection.style.display = 'block';
            } else {
                bioSection.style.display = 'none';
            }
            modal.style.display = 'flex';
        }
        function closeEmployerModal() {
            document.getElementById('employerModal').style.display = 'none';
        }
        document.getElementById('employerModal').addEventListener('click', function(e) {
            if (e.target === this) closeEmployerModal();
        });
    </script>
</body>
</html>


