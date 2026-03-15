<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
ensure_career_plan_schema();

if (!isset($_SESSION['user'])) {
    header('Location: login.php?error=Please%20log%20in%20first');
    exit;
}

$conn = db();
$user = $_SESSION['user'];
$userId = (int) ($user['user_id'] ?? 0);
$idColumn = users_id_column();
$nameColumn = users_name_column();

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function table_exists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function parse_skills_input(string $skillsInput): array
{
    $parts = preg_split('/[\r\n,]+/', $skillsInput) ?: [];
    $out = [];
    $seen = [];

    foreach ($parts as $part) {
        $skill = trim((string) preg_replace('/\s+/', ' ', (string) $part));
        if ($skill === '') {
            continue;
        }
        $key = strtolower($skill);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = substr($skill, 0, 100);
    }

    return $out;
}

function refresh_user_session(mysqli $conn, int $userId, string $idColumn): array
{
    $current = $_SESSION['user'] ?? [];
    if ($userId <= 0 || !users_has_column($idColumn)) {
        return is_array($current) ? $current : [];
    }

    $freshSql = "SELECT " . users_select_sql() . " FROM users WHERE `{$idColumn}` = ? LIMIT 1";
    $freshStmt = $conn->prepare($freshSql);
    if (!$freshStmt) {
        return is_array($current) ? $current : [];
    }

    $freshStmt->bind_param('i', $userId);
    $freshStmt->execute();
    $res = $freshStmt->get_result();
    $freshUser = $res ? $res->fetch_assoc() : null;
    $freshStmt->close();

    if (is_array($freshUser)) {
        unset($freshUser['password']);
        $_SESSION['user'] = $freshUser;
        return $freshUser;
    }

    return is_array($current) ? $current : [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId > 0) {
    $action = trim((string) ($_POST['action'] ?? 'profile_update'));

    if ($action === 'profile_photo_update') {
        if (!users_has_column('profile_picture')) {
            header('Location: profile.php?status=error&message=' . urlencode('Profile picture is not available in database.'));
            exit;
        }

        if (!isset($_FILES['profile_picture']) || !is_array($_FILES['profile_picture'])) {
            header('Location: profile.php?status=error&message=' . urlencode('Please choose an image to upload.'));
            exit;
        }

        $file = $_FILES['profile_picture'];
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            header('Location: profile.php?status=error&message=' . urlencode('Image upload failed.'));
            exit;
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        $mime = function_exists('mime_content_type') ? (string) mime_content_type($tmpPath) : '';
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if ($size <= 0 || $size > 5 * 1024 * 1024 || !isset($allowed[$mime])) {
            header('Location: profile.php?status=error&message=' . urlencode('Use JPG, PNG, WEBP, or GIF (max 5MB).'));
            exit;
        }

        $dirFs = __DIR__ . '/uploads/profile_pics';
        if (!is_dir($dirFs) && !mkdir($dirFs, 0777, true) && !is_dir($dirFs)) {
            header('Location: profile.php?status=error&message=' . urlencode('Could not create upload folder.'));
            exit;
        }

        $fileName = 'u' . $userId . '_' . time() . '_' . uniqid('', true) . '.' . $allowed[$mime];
        $targetFs = $dirFs . '/' . $fileName;
        $targetWeb = 'uploads/profile_pics/' . $fileName;

        if (!move_uploaded_file($tmpPath, $targetFs)) {
            header('Location: profile.php?status=error&message=' . urlencode('Could not save uploaded image.'));
            exit;
        }

        $stmt = $conn->prepare("UPDATE users SET `profile_picture` = ? WHERE `{$idColumn}` = ? LIMIT 1");
        if (!$stmt) {
            header('Location: profile.php?status=error&message=' . urlencode('Could not prepare photo update query.'));
            exit;
        }
        $stmt->bind_param('si', $targetWeb, $userId);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            header('Location: profile.php?status=error&message=' . urlencode('Could not update profile picture.'));
            exit;
        }

        $user = refresh_user_session($conn, $userId, $idColumn);
        header('Location: profile.php?status=saved&message=' . urlencode('Profile picture updated.'));
        exit;
    }

    $name = trim((string) ($_POST['display_name'] ?? ''));
    $location = trim((string) ($_POST['location'] ?? ''));
    $strand = trim((string) ($_POST['strand'] ?? ''));
    $bio = trim((string) ($_POST['bio'] ?? ''));
    $careerPlanPost = trim((string) ($_POST['career_plan'] ?? ''));
    $skillsInput = trim((string) ($_POST['skills'] ?? ''));

    $set = [];
    $values = [];
    $types = '';

    if ($name !== '' && users_has_column($nameColumn)) {
        $set[] = "`{$nameColumn}` = ?";
        $values[] = substr($name, 0, 100);
        $types .= 's';
    }
    if (users_has_column('location')) {
        $set[] = "`location` = ?";
        $values[] = substr($location, 0, 100);
        $types .= 's';
    }
    if (users_has_column('strand')) {
        $set[] = "`strand` = ?";
        $values[] = substr($strand, 0, 100);
        $types .= 's';
    }
    if (users_has_column('bio')) {
        $set[] = "`bio` = ?";
        $values[] = substr($bio, 0, 1000);
        $types .= 's';
    }
    if (users_has_column('career_plan')) {
        $set[] = "`career_plan` = ?";
        $values[] = substr($careerPlanPost, 0, 2000);
        $types .= 's';
    }

    if (!empty($set)) {
        $sql = "UPDATE users SET " . implode(', ', $set) . " WHERE `{$idColumn}` = ? LIMIT 1";
        $types .= 'i';
        $values[] = $userId;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            header('Location: profile.php?status=error&message=' . urlencode('Could not prepare update query.'));
            exit;
        }

        $bind = [$types];
        foreach ($values as $i => $value) {
            $bind[] = &$values[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            header('Location: profile.php?status=error&message=' . urlencode('Could not save profile changes.'));
            exit;
        }
    }

    if (table_exists($conn, 'skills') && table_exists($conn, 'user_skills')) {
        $parsedSkills = parse_skills_input($skillsInput);

        $del = $conn->prepare("DELETE FROM user_skills WHERE user_id = ?");
        if ($del) {
            $del->bind_param('i', $userId);
            $del->execute();
            $del->close();
        }

        $find = $conn->prepare("SELECT skill_id FROM skills WHERE skill_name = ? LIMIT 1");
        $insertSkill = $conn->prepare("INSERT INTO skills (skill_name) VALUES (?)");
        $insertUserSkill = $conn->prepare("INSERT IGNORE INTO user_skills (user_id, skill_id) VALUES (?, ?)");

        if ($find && $insertSkill && $insertUserSkill) {
            foreach ($parsedSkills as $skillName) {
                $skillId = 0;

                $find->bind_param('s', $skillName);
                $find->execute();
                $res = $find->get_result();
                $row = $res ? $res->fetch_assoc() : null;

                if ($row && isset($row['skill_id'])) {
                    $skillId = (int) $row['skill_id'];
                } else {
                    $insertSkill->bind_param('s', $skillName);
                    if ($insertSkill->execute()) {
                        $skillId = (int) $insertSkill->insert_id;
                    }
                }

                if ($skillId > 0) {
                    $insertUserSkill->bind_param('ii', $userId, $skillId);
                    $insertUserSkill->execute();
                }
            }
        }

        if ($find) {
            $find->close();
        }
        if ($insertSkill) {
            $insertSkill->close();
        }
        if ($insertUserSkill) {
            $insertUserSkill->close();
        }
    }

    $user = refresh_user_session($conn, $userId, $idColumn);
    header('Location: profile.php?status=saved&message=' . urlencode('Profile updated successfully.'));
    exit;
}

$user = refresh_user_session($conn, $userId, $idColumn);
$displayName = (string) (($user['username'] ?? '') ?: ($user['full_name'] ?? 'KITA User'));
$email = (string) ($user['email'] ?? '');
$location = (string) ($user['location'] ?? '');
$strand = (string) ($user['strand'] ?? '');
$bio = (string) ($user['bio'] ?? '');
$careerPlan = (string) ($user['career_plan'] ?? '');
$profilePicture = trim((string) ($user['profile_picture'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$message = (string) ($_GET['message'] ?? '');

$userSkills = [];
if ($userId > 0 && table_exists($conn, 'skills') && table_exists($conn, 'user_skills')) {
    $skillStmt = $conn->prepare(
        "SELECT s.skill_name
         FROM user_skills us
         INNER JOIN skills s ON s.skill_id = us.skill_id
         WHERE us.user_id = ?
         ORDER BY s.skill_name ASC"
    );
    if ($skillStmt) {
        $skillStmt->bind_param('i', $userId);
        $skillStmt->execute();
        $res = $skillStmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $skill = trim((string) ($row['skill_name'] ?? ''));
            if ($skill !== '') {
                $userSkills[] = $skill;
            }
        }
        $skillStmt->close();
    }
}
$skillsInputValue = implode(', ', $userSkills);

$posts = [];
if ($userId > 0 && table_exists($conn, 'posts')) {
    $postStmt = $conn->prepare(
        "SELECT post_id, content, image, created_at
         FROM posts
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 25"
    );
    if ($postStmt) {
        $postStmt->bind_param('i', $userId);
        $postStmt->execute();
        $res = $postStmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $posts[] = $row;
        }
        $postStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>KITA | Profile</title>
    <style>
        :root {
            --bg: #f4f6f8;
            --surface: #ffffff;
            --line: #d8dee5;
            --text: #1f2937;
            --muted: #6b7280;
            --brand: #0ea765;
            --brand-dark: #0b8752;
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
            --brand-dark: #16a34a;
            --shadow: 0 14px 30px rgba(2, 6, 23, 0.45);
        }

        body {
            background:
                radial-gradient(circle at 10% 8%, rgba(14, 167, 101, 0.14), transparent 30%),
                radial-gradient(circle at 88% 90%, rgba(16, 185, 129, 0.12), transparent 35%),
                var(--bg);
            color: var(--text);
            font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 20;
            background: var(--surface);
            border-bottom: 1px solid var(--line);
            padding: 10px 16px;
        }

        .topbar-inner {
            max-width: 1120px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: var(--text);
            font-size: 24px;
            font-weight: 800;
        }

        .logo .brand-logo {
            width: 32px;
            height: 32px;
            object-fit: contain;
        }

        .logo .brand-word {
            line-height: 1;
        }

        .back-link {
            text-decoration: none;
            color: var(--brand);
            font-size: 13px;
            font-weight: 700;
            border: 1px solid #b6dccc;
            border-radius: 999px;
            padding: 8px 12px;
            background: #eef9f3;
        }

        body[data-theme="dark"] .back-link {
            background: rgba(34, 197, 94, 0.15);
            border-color: rgba(34, 197, 94, 0.45);
            color: #86efac;
        }

        .wrap {
            max-width: 1120px;
            margin: 0 auto;
            padding: 14px;
            display: grid;
            gap: 14px;
        }

        .hero {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 18px 16px;
            box-shadow: var(--shadow);
        }

        .hero-body {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .identity {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar {
            width: 132px;
            height: 132px;
            border-radius: 50%;
            border: 4px solid #fff;
            background: #d5e9df;
            display: grid;
            place-items: center;
            font-size: 44px;
            font-weight: 800;
            color: #425066;
            overflow: hidden;
            position: relative;
            cursor: pointer;
            padding: 0;
            appearance: none;
            -webkit-appearance: none;
        }

        body[data-theme="dark"] .avatar {
            background: #243244;
            color: #cbd5e1;
            border-color: #111827;
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .avatar-hint {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.56);
            color: #fff;
            font-size: 11px;
            text-align: center;
            padding: 3px 4px;
        }

        .name {
            font-size: 31px;
            font-weight: 800;
            line-height: 1.1;
        }

        .meta {
            color: var(--muted);
            font-size: 14px;
            margin-top: 3px;
        }

        .bio-text {
            margin-top: 8px;
            font-size: 14px;
            color: #34363a;
            line-height: 1.45;
            max-width: 700px;
        }

        body[data-theme="dark"] .bio-text {
            color: #d1d5db;
        }

        .identity-badges {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 9px;
        }

        .identity-badge {
            border-radius: 999px;
            padding: 4px 9px;
            font-size: 12px;
            border: 1px solid #c9e7d7;
            background: #eef9f3;
            color: #245f47;
        }

        body[data-theme="dark"] .identity-badge {
            border-color: #355b47;
            background: #1d3328;
            color: #9ae6c0;
        }

        .btn {
            text-decoration: none;
            border: 0;
            border-radius: 9px;
            padding: 10px 14px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn.primary {
            background: var(--brand);
            color: #fff;
        }

        .btn.primary:hover {
            background: var(--brand-dark);
        }

        .btn.secondary {
            background: #eaf1ee;
            color: #245f47;
        }

        .btn.secondary:hover {
            background: #ddebe5;
        }

        body[data-theme="dark"] .btn.secondary {
            background: #1f2937;
            color: #e5e7eb;
        }

        body[data-theme="dark"] .btn.secondary:hover {
            background: #334155;
        }

        .layout {
            display: grid;
            grid-template-columns: 360px minmax(0, 1fr);
            gap: 14px;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 14px;
            display: grid;
            gap: 10px;
            align-content: start;
            box-shadow: var(--shadow);
        }

        .card h2 {
            font-size: 18px;
            line-height: 1.1;
        }

        .saved-alert {
            color: #0b6a2b;
            font-size: 13px;
            border: 1px solid #b9e7c5;
            background: #ecf9f0;
            border-radius: 8px;
            padding: 9px 11px;
        }

        .form {
            display: grid;
            gap: 9px;
        }

        label {
            font-size: 12px;
            color: var(--muted);
            font-weight: 700;
        }

        input, textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 9px;
            padding: 10px;
            font-size: 14px;
            color: var(--text);
            background: #fff;
            font-family: inherit;
            outline: none;
        }

        body[data-theme="dark"] input,
        body[data-theme="dark"] textarea {
            background: #0f172a;
            color: #e5e7eb;
            border-color: #334155;
        }

        input[readonly],
        textarea[readonly] {
            background: #f0f2f5;
            color: #4b4f56;
            cursor: not-allowed;
        }

        body[data-theme="dark"] input[readonly],
        body[data-theme="dark"] textarea[readonly] {
            background: #1f2937;
            color: #9ca3af;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .pill-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .pill {
            border: 1px solid #d1e7da;
            background: #f1faf5;
            color: #245f47;
            border-radius: 999px;
            font-size: 12px;
            padding: 4px 9px;
        }

        body[data-theme="dark"] .pill {
            border-color: #355b47;
            background: #1d3328;
            color: #9ae6c0;
        }

        .section-title {
            font-size: 13px;
            color: var(--muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-top: 4px;
        }

        .post {
            border: 1px solid var(--line);
            border-radius: 11px;
            padding: 11px;
            display: grid;
            gap: 8px;
            background: #fff;
        }

        body[data-theme="dark"] .post {
            background: #0f172a;
        }

        .post-time {
            color: var(--muted);
            font-size: 12px;
        }

        .post-image {
            width: 100%;
            border-radius: 8px;
            border: 1px solid var(--line);
        }

        .empty {
            color: var(--muted);
            font-size: 13px;
            border: 1px dashed #ced4df;
            border-radius: 10px;
            padding: 18px 12px;
            text-align: center;
        }

        body[data-theme="dark"] .empty {
            border-color: #334155;
        }

        .modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 60;
            padding: 16px;
        }

        .modal.open {
            display: flex;
        }

        .modal-card {
            width: min(420px, 92vw);
            background: #fff;
            border-radius: 12px;
            border: 1px solid var(--line);
            padding: 14px;
            display: grid;
            gap: 10px;
            box-shadow: var(--shadow);
        }

        body[data-theme="dark"] .modal-card {
            background: #111827;
        }

        .modal-title {
            font-size: 18px;
            font-weight: 700;
        }

        .modal-sub {
            color: var(--muted);
            font-size: 13px;
        }

        .modal-preview {
            width: 106px;
            height: 106px;
            border-radius: 50%;
            border: 1px solid var(--line);
            object-fit: cover;
            background: #f2f4f7;
        }

        .modal-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .file-picker {
            border: 1px dashed #b9d8c7;
            border-radius: 10px;
            background: #f5fbf7;
            padding: 10px;
            display: grid;
            gap: 8px;
        }

        body[data-theme="dark"] .file-picker {
            border-color: #355b47;
            background: #13251d;
        }

        .file-picker-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .file-input-hidden {
            position: absolute;
            width: 1px;
            height: 1px;
            margin: -1px;
            padding: 0;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
        }

        .file-trigger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #b6dccc;
            background: #eef9f3;
            color: #245f47;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
        }

        body[data-theme="dark"] .file-trigger {
            border-color: #355b47;
            background: #1d3328;
            color: #9ae6c0;
        }

        .file-name {
            color: var(--muted);
            font-size: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @media (max-width: 980px) {
            .layout { grid-template-columns: 1fr; }
            .avatar {
                width: 100px;
                height: 100px;
                font-size: 34px;
            }
            .name { font-size: 24px; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="logo" href="index.php">
                <img class="brand-logo" src="uploads/kita_logo.png" alt="KITA logo" />
                <span class="brand-word">KITA</span>
            </a>
            <a class="back-link" href="index.php">Back to home</a>
        </div>
    </header>

    <main class="wrap">
        <section class="hero">
            <div class="hero-body">
                <div class="identity">
                    <button class="avatar" id="openPhotoModalBtn" type="button" aria-label="Change profile photo">
                        <?php if ($profilePicture !== ''): ?>
                            <img src="<?php echo e($profilePicture); ?>" alt="Profile picture" />
                        <?php else: ?>
                            <?php echo e(strtoupper(substr($displayName, 0, 1))); ?>
                        <?php endif; ?>
                        <span class="avatar-hint">Edit</span>
                    </button>
                    <div>
                        <h1 class="name"><?php echo e($displayName); ?></h1>
                        <p class="meta"><?php echo e($email !== '' ? $email : 'No email'); ?></p>
                        <p class="meta"><?php echo e($location !== '' ? $location : 'No location'); ?></p>
                        <p class="bio-text"><?php echo e($bio !== '' ? $bio : 'No bio yet. Add one from Edit profile.'); ?></p>
                        <?php if ($careerPlan !== ''): ?>
                            <p class="meta" style="margin-top:4px;font-style:italic;">🎯 <?php echo e($careerPlan); ?></p>
                        <?php endif; ?>
                        <div class="identity-badges">
                            <?php if ($strand !== ''): ?><span class="identity-badge">Strand: <?php echo e($strand); ?></span><?php endif; ?>
                            <?php if (count($userSkills) > 0): ?><span class="identity-badge"><?php echo count($userSkills); ?> skills</span><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button class="btn secondary" id="openPhotoModalBtn2" type="button">Change photo</button>
                    <button class="btn primary" id="editProfileBtn" type="button">Edit profile</button>
                </div>
            </div>
        </section>

        <section class="layout">
            <aside class="card" id="editCard">
                <h2>Intro</h2>
                <?php if ($status === 'saved'): ?>
                    <div class="saved-alert"><?php echo e($message !== '' ? $message : 'Profile updated successfully.'); ?></div>
                <?php elseif ($status === 'error'): ?>
                    <div class="saved-alert" style="color:#8f1d1d;border-color:#f1c2c2;background:#fff1f1;"><?php echo e($message !== '' ? $message : 'Could not update profile.'); ?></div>
                <?php endif; ?>

                <form class="form" method="post">
                    <input type="hidden" name="action" value="profile_update" />

                    <label for="display_name">Display name</label>
                    <input id="display_name" name="display_name" type="text" value="<?php echo e($displayName); ?>" maxlength="100" required readonly />

                    <label for="location">Location</label>
                    <input id="location" name="location" type="text" value="<?php echo e($location); ?>" maxlength="100" readonly />

                    <label for="strand">Strand</label>
                    <input id="strand" name="strand" type="text" value="<?php echo e($strand); ?>" maxlength="100" readonly />

                    <label for="bio">Bio</label>
                    <textarea id="bio" name="bio" maxlength="1000" readonly><?php echo e($bio); ?></textarea>

                    <label for="career_plan">Career Plan / Goals</label>
                    <textarea id="career_plan" name="career_plan" maxlength="2000" placeholder="e.g. I plan to pursue a career in web development after graduation..." readonly><?php echo e($careerPlan); ?></textarea>

                    <label for="skills">Skills (comma separated)</label>
                    <input id="skills" name="skills" type="text" value="<?php echo e($skillsInputValue); ?>" maxlength="1000" readonly />

                    <div style="display:flex;gap:8px;">
                        <button class="btn primary" id="saveProfileBtn" type="submit" disabled style="opacity:.6;cursor:not-allowed;">Save changes</button>
                        <button class="btn secondary" id="cancelEditBtn" type="button" style="display:none;">Cancel</button>
                    </div>
                </form>
            </aside>

            <section class="card">
                <h2>Posts</h2>
                <div class="pill-row">
                    <span class="pill"><?php echo count($posts); ?> posts</span>
                    <?php if ($strand !== ''): ?><span class="pill"><?php echo e($strand); ?></span><?php endif; ?>
                    <?php if ($location !== ''): ?><span class="pill"><?php echo e($location); ?></span><?php endif; ?>
                </div>

                <div class="section-title">Skills</div>
                <div class="pill-row">
                    <?php if (count($userSkills) > 0): ?>
                        <?php foreach ($userSkills as $skill): ?>
                            <span class="pill"><?php echo e($skill); ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="pill">No skills yet</span>
                    <?php endif; ?>
                </div>

                <?php if (!$posts): ?>
                    <div class="empty">No posts yet. Start sharing updates from your home feed.</div>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <article class="post">
                            <div class="post-time"><?php echo e((string) ($post['created_at'] ?? '')); ?></div>
                            <?php if (trim((string) ($post['content'] ?? '')) !== ''): ?>
                                <p><?php echo e((string) $post['content']); ?></p>
                            <?php endif; ?>
                            <?php if (trim((string) ($post['image'] ?? '')) !== ''): ?>
                                <img class="post-image" src="<?php echo e((string) $post['image']); ?>" alt="Post image" />
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </section>
    </main>

    <div class="modal" id="photoModal" aria-hidden="true">
        <div class="modal-card">
            <div class="modal-title">Change Profile Picture</div>
            <p class="modal-sub">Upload JPG, PNG, WEBP, or GIF. Max file size: 5MB.</p>
            <?php if ($profilePicture !== ''): ?>
                <img class="modal-preview" src="<?php echo e($profilePicture); ?>" alt="Current profile picture" />
            <?php else: ?>
                <div class="modal-preview" style="display:grid;place-items:center;font-size:30px;font-weight:700;color:#556;"><?php echo e(strtoupper(substr($displayName, 0, 1))); ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" style="display:grid;gap:10px;">
                <input type="hidden" name="action" value="profile_photo_update" />
                <div class="file-picker">
                    <div class="file-picker-row">
                        <input class="file-input-hidden" id="profilePictureInput" type="file" name="profile_picture" accept="image/*" required />
                        <label class="file-trigger" for="profilePictureInput">Choose file</label>
                        <span class="file-name" id="profilePictureName">No file selected</span>
                    </div>
                </div>
                <div class="modal-actions">
                    <button class="btn secondary" id="closePhotoModalBtn" type="button">Cancel</button>
                    <button class="btn primary" type="submit">Save photo</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const THEME_KEY = 'kita_theme';
            const body = document.body;
            const storedTheme = localStorage.getItem(THEME_KEY);
            const activeTheme = storedTheme === 'dark' ? 'dark' : 'light';
            body.dataset.theme = activeTheme;

            const form = document.querySelector('.form');
            const editBtn = document.getElementById('editProfileBtn');
            const saveBtn = document.getElementById('saveProfileBtn');
            const cancelBtn = document.getElementById('cancelEditBtn');
            const photoModal = document.getElementById('photoModal');
            const openPhotoBtn = document.getElementById('openPhotoModalBtn');
            const openPhotoBtn2 = document.getElementById('openPhotoModalBtn2');
            const closePhotoBtn = document.getElementById('closePhotoModalBtn');
            const profilePictureInput = document.getElementById('profilePictureInput');
            const profilePictureName = document.getElementById('profilePictureName');
            const fields = ['display_name', 'location', 'strand', 'bio', 'career_plan', 'skills']
                .map((id) => document.getElementById(id))
                .filter(Boolean);

            if (!form || !editBtn || !saveBtn || !cancelBtn || !fields.length) return;

            const initialValues = {};
            fields.forEach((el) => {
                initialValues[el.id] = el.value;
            });

            function setEditing(isEditing) {
                fields.forEach((el) => {
                    if (isEditing) {
                        el.removeAttribute('readonly');
                    } else {
                        el.setAttribute('readonly', 'readonly');
                    }
                });

                saveBtn.disabled = !isEditing;
                saveBtn.style.opacity = isEditing ? '1' : '.6';
                saveBtn.style.cursor = isEditing ? 'pointer' : 'not-allowed';
                cancelBtn.style.display = isEditing ? 'inline-block' : 'none';
                editBtn.textContent = isEditing ? 'Editing...' : 'Edit profile';
                editBtn.disabled = isEditing;
            }

            editBtn.addEventListener('click', () => {
                setEditing(true);
                fields[0].focus();
                document.getElementById('editCard')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });

            cancelBtn.addEventListener('click', () => {
                fields.forEach((el) => {
                    el.value = initialValues[el.id] ?? '';
                });
                setEditing(false);
            });

            function openPhotoModal() {
                if (!photoModal) return;
                photoModal.classList.add('open');
                photoModal.setAttribute('aria-hidden', 'false');
            }

            function closePhotoModal() {
                if (!photoModal) return;
                photoModal.classList.remove('open');
                photoModal.setAttribute('aria-hidden', 'true');
                if (profilePictureInput) profilePictureInput.value = '';
                if (profilePictureName) profilePictureName.textContent = 'No file selected';
            }

            openPhotoBtn?.addEventListener('click', openPhotoModal);
            openPhotoBtn2?.addEventListener('click', openPhotoModal);
            closePhotoBtn?.addEventListener('click', closePhotoModal);
            photoModal?.addEventListener('click', (event) => {
                if (event.target === photoModal) closePhotoModal();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closePhotoModal();
            });

            profilePictureInput?.addEventListener('change', () => {
                const file = profilePictureInput.files && profilePictureInput.files[0] ? profilePictureInput.files[0] : null;
                if (profilePictureName) {
                    profilePictureName.textContent = file ? file.name : 'No file selected';
                }
            });

            form.addEventListener('submit', (event) => {
                if (saveBtn.disabled) {
                    event.preventDefault();
                }
            });
        })();
    </script>
</body>
</html>

