<?php
require_once __DIR__ . '/db.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function normalize_avatar_path(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    if (preg_match('/^https?:\/\//i', $raw)) {
        return $raw;
    }
    if (str_starts_with($raw, 'uploads/')) {
        return $raw;
    }
    if (str_starts_with($raw, '/uploads/')) {
        return ltrim($raw, '/');
    }
    return 'uploads/profile_pics/' . ltrim($raw, '/');
}

$userSession = $_SESSION['user'] ?? null;
$employerSession = $_SESSION['employer'] ?? null;

if (!is_array($userSession) && !is_array($employerSession)) {
    header('Location: login.php');
    exit;
}

$isEmployerView = !is_array($userSession) && is_array($employerSession);
$profileHref = $isEmployerView ? 'employer.php' : 'profile.php';
$viewerName = $isEmployerView
    ? trim((string) (($employerSession['company_name'] ?? '') ?: ($employerSession['contact_name'] ?? 'Employer')))
    : trim((string) (($userSession['username'] ?? '') ?: ($userSession['full_name'] ?? 'KITA User')));
if ($viewerName === '') {
    $viewerName = $isEmployerView ? 'Employer' : 'KITA User';
}

$viewerStorageSeed = $isEmployerView
    ? ('employer:' . (string) (($employerSession['id'] ?? '') ?: $viewerName))
    : ('user:' . (string) (($userSession['email'] ?? '') ?: (($userSession['username'] ?? '') ?: $viewerName)));
$viewerStorageId = strtolower((string) preg_replace('/[^a-zA-Z0-9_]+/', '_', $viewerStorageSeed));
$viewerStorageId = trim($viewerStorageId, '_');
if ($viewerStorageId === '') {
    $viewerStorageId = 'kita_user';
}

ensure_social_tables();

$viewerHandleSeed = $isEmployerView
    ? (string) (($employerSession['company_name'] ?? '') ?: ($employerSession['contact_name'] ?? $viewerName))
    : (string) (($userSession['username'] ?? '') ?: ($userSession['full_name'] ?? $viewerName));
$viewerHandleSlug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $viewerHandleSeed));
$viewerHandleSlug = trim($viewerHandleSlug, '_');
if ($viewerHandleSlug === '') {
    $viewerHandleSlug = 'kita_user';
}
$viewerHandle = '@' . $viewerHandleSlug;

$employerJobs = [];
if ($isEmployerView) {
    $conn = db();
    $employerId = (int) ($employerSession['id'] ?? 0);
    if ($employerId > 0) {
        $stmt = $conn->prepare(
            "SELECT job_id, job_title, strand_required, skills_required, location, job_type, created_at
             FROM jobs
             WHERE employer_id = ?
             ORDER BY created_at DESC
             LIMIT 8"
        );
        if ($stmt) {
            $stmt->bind_param('i', $employerId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $employerJobs[] = $row;
            }
            $stmt->close();
        }
    }
}

$directoryUsers = [];
if (!$isEmployerView) {
    $conn = db();
    $nameColumn = users_name_column();
    $idColumn = users_id_column();
    $selectParts = [];

    $selectParts[] = users_has_column($idColumn) ? "`{$idColumn}` AS user_id" : "NULL AS user_id";
    if (users_has_column($nameColumn)) {
        $selectParts[] = "`{$nameColumn}` AS username";
    } else {
        $selectParts[] = "NULL AS username";
    }
    $selectParts[] = users_has_column('email') ? "`email`" : "NULL AS email";
    $selectParts[] = users_has_column('strand') ? "`strand`" : "NULL AS strand";
    $selectParts[] = users_has_column('location') ? "`location`" : "NULL AS location";
    $selectParts[] = users_has_column('profile_picture') ? "`profile_picture`" : "NULL AS profile_picture";

    $sql = "SELECT " . implode(', ', $selectParts) . " FROM users ORDER BY username ASC LIMIT 200";
    $res = $conn->query($sql);
    $viewerEmail = strtolower(trim((string) ($userSession['email'] ?? '')));
    $viewerUsername = strtolower(trim((string) ($userSession['username'] ?? ($userSession['full_name'] ?? ''))));

    while ($res && ($row = $res->fetch_assoc())) {
        $username = trim((string) ($row['username'] ?? ''));
        $email = strtolower(trim((string) ($row['email'] ?? '')));
        if ($username === '') continue;
        if ($viewerEmail !== '' && $viewerEmail === $email) continue;
        if ($viewerUsername !== '' && strtolower($username) === $viewerUsername) continue;

        $handleBase = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $username) ?? '');
        $handleBase = trim($handleBase, '_');
        $handle = '@' . ($handleBase !== '' ? $handleBase : 'kita_user');

        $tags = [];
        $strand = trim((string) ($row['strand'] ?? ''));
        $location = trim((string) ($row['location'] ?? ''));
        $avatar = normalize_avatar_path((string) ($row['profile_picture'] ?? ''));
        if ($strand !== '') $tags[] = $strand;
        if ($location !== '') $tags[] = $location;

        $directoryUsers[] = [
            'id' => (int) ($row['user_id'] ?? 0),
            'name' => $username,
            'handle' => $handle,
            'status' => 'Available',
            'bio' => $location !== '' ? ('From ' . $location) : 'KITA member',
            'tags' => $tags,
            'media' => [],
            'avatar' => $avatar,
        ];
    }
}

$directoryCompanies = [];
if (!$isEmployerView && db_table_exists('employers')) {
    $conn = db();
    $res = $conn->query("SELECT id, COALESCE(company_name, contact_name, 'Employer') AS company_name,
                                COALESCE(industry, '') AS industry,
                                COALESCE(location, '') AS location,
                                COALESCE(bio, '') AS bio,
                                COALESCE(profile_picture, '') AS profile_picture
                         FROM employers
                         ORDER BY company_name ASC
                         LIMIT 200");
    while ($res && ($row = $res->fetch_assoc())) {
        $companyName = trim((string) ($row['company_name'] ?? ''));
        if ($companyName === '') $companyName = 'Employer';
        $handleBase = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $companyName) ?? '');
        $handleBase = trim($handleBase, '_');
        $handle = '@' . ($handleBase !== '' ? $handleBase : 'company');
        $industry = trim((string) ($row['industry'] ?? ''));
        $location = trim((string) ($row['location'] ?? ''));
        $bio = trim((string) ($row['bio'] ?? ''));
        $tags = array_values(array_filter([$industry, $location]));
        $avatar = normalize_avatar_path((string) ($row['profile_picture'] ?? ''));

        $directoryCompanies[] = [
            'id' => 0,
            'targetEmployerId' => (int) ($row['id'] ?? 0),
            'name' => $companyName,
            'handle' => $handle,
            'status' => 'Company',
            'bio' => $bio !== '' ? $bio : ($location !== '' ? ('Based in ' . $location) : 'Company'),
            'tags' => $tags,
            'media' => [],
            'avatar' => $avatar,
            'isCompany' => true
        ];
    }
}

$acceptedApplications = [];
if (!$isEmployerView) {
    $conn = db();
    $studentId = (int) ($userSession['user_id'] ?? 0);
    if (
        $studentId > 0 &&
        db_table_exists('applications') &&
        db_table_exists('jobs')
    ) {
        $hasEmployersTable = db_table_exists('employers');
        $sql = "SELECT a.application_id, a.status, a.applied_at, j.job_title, u.full_name AS employer_user_name";
        if ($hasEmployersTable) {
            $sql .= ", e.company_name AS employer_company_name";
        }
        $sql .= " FROM applications a
                  INNER JOIN jobs j ON j.job_id = a.job_id
                  LEFT JOIN users u ON u.user_id = j.employer_id";
        if ($hasEmployersTable) {
            $sql .= " LEFT JOIN employers e ON e.id = j.employer_id";
        }
        $sql .= " WHERE a.student_id = ? AND LOWER(a.status) = 'accepted'
                  ORDER BY a.applied_at DESC
                  LIMIT 50";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $studentId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $company = trim((string) (($row['employer_company_name'] ?? '') ?: ($row['employer_user_name'] ?? 'Employer')));
                if ($company === '') $company = 'Employer';
                $acceptedApplications[] = [
                    'application_id' => (int) ($row['application_id'] ?? 0),
                    'job_title' => (string) ($row['job_title'] ?? 'Job opening'),
                    'company' => $company,
                    'status' => (string) ($row['status'] ?? 'accepted'),
                    'applied_at' => (string) ($row['applied_at'] ?? ''),
                ];
            }
            $stmt->close();
        }
    }
}

// Fetch real published jobs for the sidebar job picks
$sidebarJobs = [];
if (!$isEmployerView) {
    $conn = db();
    if (db_table_exists('jobs') && db_table_exists('employers')) {
        $jStmt = $conn->prepare(
            "SELECT j.job_id, j.employer_id, j.job_title, j.location, j.job_type, j.salary,
                    COALESCE(e.company_name, 'Employer') AS company_name,
                    COALESCE(e.profile_picture, '') AS employer_pic
             FROM jobs j
             LEFT JOIN employers e ON e.id = j.employer_id
             ORDER BY j.created_at DESC
             LIMIT 5"
        );
        if ($jStmt) {
            $jStmt->execute();
            $jRes = $jStmt->get_result();
            while ($jRes && ($jRow = $jRes->fetch_assoc())) {
                $sidebarJobs[] = [
                    'id'       => (int) $jRow['job_id'],
                    'employer_id' => (int) ($jRow['employer_id'] ?? 0),
                    'title'    => (string) $jRow['job_title'],
                    'company'  => (string) $jRow['company_name'],
                    'location' => (string) $jRow['location'],
                    'type'     => (string) $jRow['job_type'],
                    'salary'   => (string) $jRow['salary'],
                    'pic'      => (string) $jRow['employer_pic'],
                ];
            }
            $jStmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>KITAgram Feed</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <style>
        :root {
            --bg: #f4f6f8;
            --surface: #ffffff;
            --surface-2: #f8fafc;
            --text: #1f2937;
            --muted: #6b7280;
            --line: #d8dee5;
            --accent: #0ea765;
            --accent-2: #0b8752;
            --chip: #eef2f7;
            --shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
        }

        body[data-theme="dark"] {
            --bg: #0f172a;
            --surface: #111827;
            --surface-2: #1f2937;
            --text: #e5e7eb;
            --muted: #9ca3af;
            --line: #334155;
            --accent: #22c55e;
            --accent-2: #16a34a;
            --chip: #273449;
            --shadow: 0 14px 30px rgba(2, 6, 23, 0.45);
        }

        body[data-theme="dark"] {
            background: var(--bg);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 0;
            position: relative;
            overflow-x: hidden;
            transition: background-color 0.42s ease, color 0.42s ease;
        }

        .money-rain {
            position: fixed;
            inset: -20vh 0 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .money-note {
            position: absolute;
            top: -20vh;
            width: 42px;
            aspect-ratio: 1 / 1;
            background-image: url("a.jpg");
            background-size: cover;
            background-position: center;
            border-radius: 6px;
            opacity: 0.22;
            filter: saturate(1.1);
            animation-name: money-fall, money-sway;
            animation-timing-function: linear, ease-in-out;
            animation-iteration-count: infinite, infinite;
        }

        body[data-theme="dark"] .money-note {
            opacity: 0.12 !important;
            filter: brightness(0.8) saturate(0.9);
        }

        @keyframes money-fall {
            from { transform: translateY(0) rotate(0deg); }
            to { transform: translateY(140vh) rotate(360deg); }
        }

        @keyframes money-sway {
            0%, 100% { margin-left: 0; }
            50% { margin-left: 26px; }
        }

        .app {
            width: 100%;
            min-height: 100vh;
            max-width: none;
            margin: 0;
            border: 0;
            border-radius: 0;
            background: var(--surface);
            box-shadow: none;
            overflow: hidden;
            position: relative;
            z-index: 1;
            transition: background-color 0.42s ease, border-color 0.42s ease, box-shadow 0.42s ease;
        }

        body[data-theme="dark"] .app {
            background: var(--surface);
        }

        .content {
            display: grid;
            grid-template-columns: 92px minmax(0, 1fr) 320px;
            gap: 14px;
            padding: 14px;
            min-height: 100vh;
        }

        .content.messages-open {
            grid-template-columns: 92px minmax(0, 1fr);
        }

        .left-col {
            border-right: 1px solid var(--line);
            padding: 6px 6px 6px 2px;
            display: grid;
            align-content: start;
            gap: 10px;
        }

        .brand {
            width: 100%;
            min-height: 54px;
            display: grid;
            place-items: center;
            font-size: 30px;
            color: var(--text);
        }

        .brand .brand-word {
            display: none;
        }

        .menu { list-style: none; display: grid; gap: 4px; }

        .menu button,
        .menu a {
            width: 100%;
            border: 0;
            background: transparent;
            border-radius: 12px;
            padding: 11px 6px;
            display: flex;
            gap: 0;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            text-align: center;
            color: var(--text);
            cursor: pointer;
            transition: background 0.2s ease, transform 0.2s ease;
            text-decoration: none;
        }

        .menu button:hover,
        .menu button.active,
        .menu a:hover {
            background: var(--chip);
            transform: translateY(-1px);
        }

        .menu i {
            width: 22px;
            text-align: center;
            font-size: 22px;
        }

        .menu span {
            display: none;
        }

        .menu .logout-link {
            color: #b91c1c;
        }

        body[data-theme="dark"] .menu .logout-link {
            color: #f87171;
        }

        .quick-card {
            border: 1px solid var(--line);
            background: var(--surface-2);
            border-radius: 12px;
            padding: 11px;
            display: grid;
            gap: 8px;
            transition: background-color 0.35s ease, border-color 0.35s ease;
        }

        .left-col .quick-card {
            display: none;
        }

        .small-label { font-size: 12px; color: var(--muted); }

        .theme-switch {
            --toggle-size: 18px;
            --container-width: 5.625em;
            --container-height: 2.5em;
            --container-radius: 6.25em;
            --container-light-bg: #3D7EAE;
            --container-night-bg: #1D1F2C;
            --circle-container-diameter: 3.375em;
            --sun-moon-diameter: 2.125em;
            --sun-bg: #ECCA2F;
            --moon-bg: #C4C9D1;
            --spot-color: #959DB1;
            --circle-container-offset: calc((var(--circle-container-diameter) - var(--container-height)) / 2 * -1);
            --stars-color: #fff;
            --clouds-color: #F3FDFF;
            --back-clouds-color: #AACADF;
            --transition: .5s cubic-bezier(0, -0.02, 0.4, 1.25);
            --circle-transition: .3s cubic-bezier(0, -0.02, 0.35, 1.17);
            display: inline-block;
        }

        .theme-switch,
        .theme-switch *,
        .theme-switch *::before,
        .theme-switch *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-size: var(--toggle-size);
        }

        .theme-switch__container {
            width: var(--container-width);
            height: var(--container-height);
            background-color: var(--container-light-bg);
            border-radius: var(--container-radius);
            overflow: hidden;
            cursor: pointer;
            box-shadow: 0em -0.062em 0.062em rgba(0, 0, 0, 0.25), 0em 0.062em 0.125em rgba(255, 255, 255, 0.94);
            transition: var(--transition);
            position: relative;
        }

        .theme-switch__container::before {
            content: "";
            position: absolute;
            z-index: 1;
            inset: 0;
            box-shadow: 0em 0.05em 0.187em rgba(0, 0, 0, 0.25) inset, 0em 0.05em 0.187em rgba(0, 0, 0, 0.25) inset;
            border-radius: var(--container-radius);
        }

        .theme-switch__checkbox {
            display: none;
        }

        .theme-switch__circle-container {
            width: var(--circle-container-diameter);
            height: var(--circle-container-diameter);
            background-color: rgba(255, 255, 255, 0.1);
            position: absolute;
            left: var(--circle-container-offset);
            top: var(--circle-container-offset);
            border-radius: var(--container-radius);
            box-shadow: inset 0 0 0 3.375em rgba(255, 255, 255, 0.1), inset 0 0 0 3.375em rgba(255, 255, 255, 0.1), 0 0 0 0.625em rgba(255, 255, 255, 0.1), 0 0 0 1.25em rgba(255, 255, 255, 0.1);
            display: flex;
            transition: var(--circle-transition);
            pointer-events: none;
        }

        .theme-switch__sun-moon-container {
            pointer-events: auto;
            position: relative;
            z-index: 2;
            width: var(--sun-moon-diameter);
            height: var(--sun-moon-diameter);
            margin: auto;
            border-radius: var(--container-radius);
            background-color: var(--sun-bg);
            box-shadow: 0.062em 0.062em 0.062em 0em rgba(254, 255, 239, 0.61) inset, 0em -0.062em 0.062em 0em #a1872a inset;
            filter: drop-shadow(0.062em 0.125em 0.125em rgba(0, 0, 0, 0.25)) drop-shadow(0em 0.062em 0.125em rgba(0, 0, 0, 0.25));
            overflow: hidden;
            transition: var(--transition);
        }

        .theme-switch__moon {
            transform: translateX(100%);
            width: 100%;
            height: 100%;
            background-color: var(--moon-bg);
            border-radius: inherit;
            box-shadow: 0.062em 0.062em 0.062em 0em rgba(254, 255, 239, 0.61) inset, 0em -0.062em 0.062em 0em #969696 inset;
            transition: var(--transition);
            position: relative;
        }

        .theme-switch__spot {
            position: absolute;
            top: 0.75em;
            left: 0.312em;
            width: 0.75em;
            height: 0.75em;
            border-radius: var(--container-radius);
            background-color: var(--spot-color);
            box-shadow: 0em 0.0312em 0.062em rgba(0, 0, 0, 0.25) inset;
        }

        .theme-switch__spot:nth-of-type(2) {
            width: 0.375em;
            height: 0.375em;
            top: 0.937em;
            left: 1.375em;
        }

        .theme-switch__spot:nth-last-of-type(3) {
            width: 0.25em;
            height: 0.25em;
            top: 0.312em;
            left: 0.812em;
        }

        .theme-switch__clouds {
            width: 1.25em;
            height: 1.25em;
            background-color: var(--clouds-color);
            border-radius: var(--container-radius);
            position: absolute;
            bottom: -0.625em;
            left: 0.312em;
            box-shadow: 0.937em 0.312em var(--clouds-color), -0.312em -0.312em var(--back-clouds-color), 1.437em 0.375em var(--clouds-color), 0.5em -0.125em var(--back-clouds-color), 2.187em 0 var(--clouds-color), 1.25em -0.062em var(--back-clouds-color), 2.937em 0.312em var(--clouds-color), 2em -0.312em var(--back-clouds-color), 3.625em -0.062em var(--clouds-color), 2.625em 0em var(--back-clouds-color), 4.5em -0.312em var(--clouds-color), 3.375em -0.437em var(--back-clouds-color), 4.625em -1.75em 0 0.437em var(--clouds-color), 4em -0.625em var(--back-clouds-color), 4.125em -2.125em 0 0.437em var(--back-clouds-color);
            transition: 0.5s cubic-bezier(0, -0.02, 0.4, 1.25);
        }

        .theme-switch__stars-container {
            position: absolute;
            color: var(--stars-color);
            top: -100%;
            left: 0.312em;
            width: 2.75em;
            height: auto;
            transition: var(--transition);
        }

        .theme-switch__checkbox:checked + .theme-switch__container {
            background-color: var(--container-night-bg);
        }

        .theme-switch__checkbox:checked + .theme-switch__container .theme-switch__circle-container {
            left: calc(100% - var(--circle-container-offset) - var(--circle-container-diameter));
        }

        .theme-switch__checkbox:checked + .theme-switch__container .theme-switch__circle-container:hover {
            left: calc(100% - var(--circle-container-offset) - var(--circle-container-diameter) - 0.187em);
        }

        .theme-switch__circle-container:hover {
            left: calc(var(--circle-container-offset) + 0.187em);
        }

        .theme-switch__checkbox:checked + .theme-switch__container .theme-switch__moon {
            transform: translate(0);
        }

        .theme-switch__checkbox:checked + .theme-switch__container .theme-switch__clouds {
            bottom: -4.062em;
        }

        .theme-switch__checkbox:checked + .theme-switch__container .theme-switch__stars-container {
            top: 50%;
            transform: translateY(-50%);
        }

        .center-col {
            min-width: 0;
            display: grid;
            align-content: start;
            gap: 10px;
        }

        .toolbar {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--surface);
            padding: 10px;
            display: grid;
            gap: 8px;
            transition: background-color 0.35s ease, border-color 0.35s ease;
        }

        .toolbar-top {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 8px;
        }

        input[type="text"], textarea, select {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 9px;
            background: var(--surface);
            color: var(--text);
            font-size: 13px;
            padding: 8px 10px;
            outline: none;
        }

        textarea { resize: vertical; min-height: 62px; }

        .chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .chip {
            border: 1px solid var(--line);
            background: var(--chip);
            color: var(--text);
            border-radius: 999px;
            padding: 5px 9px;
            font-size: 12px;
            cursor: pointer;
        }

        .chip.active {
            background: color-mix(in oklab, var(--accent) 18%, var(--chip));
            border-color: color-mix(in oklab, var(--accent) 45%, var(--line));
            color: color-mix(in oklab, var(--text) 50%, var(--accent));
        }

        .composer {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--surface);
            padding: 10px;
            display: grid;
            gap: 8px;
            transition: background-color 0.35s ease, border-color 0.35s ease;
        }

        .composer-headline {
            font-size: 13px;
            color: var(--muted);
        }

        .create-trigger {
            width: 100%;
            border: 1px dashed var(--line);
            border-radius: 12px;
            background: var(--surface-2);
            color: var(--text);
            font-size: 13px;
            padding: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
        }

        .create-trigger:hover {
            background: var(--chip);
        }

        .btn {
            border: 0;
            background: var(--accent);
            color: #fff;
            border-radius: 9px;
            padding: 8px 11px;
            font-size: 13px;
            cursor: pointer;
            transition: background 0.2s ease;
            white-space: nowrap;
        }

        .btn:hover { background: var(--accent-2); }

        .btn.posting {
            opacity: 0.8;
            pointer-events: none;
        }

        .feed {
            display: grid;
            gap: 10px;
        }

        .is-hidden {
            display: none !important;
        }

        .messages-view {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--surface);
            min-height: 72vh;
            display: grid;
            width: 100%;
            grid-template-columns: minmax(280px, 360px) minmax(420px, 1fr);
            overflow: hidden;
            transition: background-color 0.35s ease, border-color 0.35s ease;
        }

        .chat-list-panel {
            border-right: 0;
            background: var(--surface-2);
            display: grid;
            grid-template-rows: auto auto 1fr;
            min-height: 72vh;
        }

        .chat-list-head {
            padding: 12px;
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
        }

        .chat-search-wrap {
            padding: 10px;
            border-bottom: 1px solid var(--line);
        }

        .chat-compose-launch {
            border: 1px solid var(--line);
            background: var(--surface);
            color: var(--text);
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .chat-compose-launch:hover {
            background: var(--chip);
        }

        .chat-list {
            overflow: auto;
            padding: 6px;
            display: grid;
            gap: 4px;
            align-content: start;
        }

        .chat-item {
            border: 1px solid transparent;
            background: transparent;
            border-radius: 10px;
            padding: 8px;
            text-align: left;
            display: grid;
            grid-template-columns: 42px 1fr;
            gap: 8px;
            cursor: pointer;
            color: var(--text);
        }

        .chat-item:hover {
            background: var(--chip);
        }

        .chat-item.active {
            background: color-mix(in oklab, var(--accent) 16%, var(--chip));
            border-color: color-mix(in oklab, var(--accent) 38%, var(--line));
        }

        .chat-item-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
        }

        .chat-item-name {
            font-size: 13px;
            font-weight: 700;
        }

        .chat-item-time {
            font-size: 11px;
            color: var(--muted);
        }

        .chat-item-preview {
            font-size: 12px;
            color: var(--muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dm-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #22c55e, #0ea765);
            color: #fff;
            font-weight: 700;
            font-size: 14px;
            display: grid;
            place-items: center;
            border: 2px solid color-mix(in oklab, var(--surface) 80%, var(--line));
            overflow: hidden;
        }

        .dm-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .dm-avatar.large {
            width: 74px;
            height: 74px;
            font-size: 24px;
            border-width: 3px;
        }

        .chat-item-main {
            min-width: 0;
            display: grid;
            gap: 2px;
            align-content: center;
        }

        .chat-thread-panel {
            display: grid;
            grid-template-rows: auto 1fr auto;
            min-width: 0;
            border-right: 0;
            min-height: 72vh;
        }

        .messages-view.dm-thread-open {
            grid-template-columns: minmax(280px, 360px) minmax(420px, 1fr);
        }

        .messages-view.dm-thread-open .chat-list-panel {
            display: grid;
        }

        .chat-thread-head {
            border-bottom: 1px solid var(--line);
            padding: 10px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .chat-thread-left {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .chat-thread-user {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chat-thread-title {
            font-size: 14px;
            font-weight: 700;
        }

        .chat-thread-sub {
            font-size: 12px;
            color: var(--muted);
        }

        .chat-thread-body {
            padding: 12px;
            display: grid;
            gap: 8px;
            align-content: start;
            overflow: auto;
            background: linear-gradient(180deg, color-mix(in oklab, var(--surface) 92%, var(--surface-2)), var(--surface));
        }

        .chat-bubble {
            max-width: 78%;
            border-radius: 12px;
            padding: 8px 10px;
            font-size: 13px;
            line-height: 1.35;
            border: 1px solid var(--line);
        }

        .chat-bubble.them {
            background: var(--surface-2);
            color: var(--text);
            justify-self: start;
        }

        .chat-bubble.me {
            background: color-mix(in oklab, var(--accent) 16%, var(--surface));
            border-color: color-mix(in oklab, var(--accent) 34%, var(--line));
            color: var(--text);
            justify-self: end;
        }

        .chat-bubble-time {
            display: block;
            margin-top: 5px;
            font-size: 10px;
            color: var(--muted);
        }

        .chat-thread-actions {
            display: inline-flex;
            gap: 6px;
        }

        .chat-compose {
            border-top: 1px solid var(--line);
            padding: 10px;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 8px;
        }

        .chat-profile-panel {
            background: var(--surface-2);
            display: none;
            align-content: start;
            gap: 12px;
            padding: 12px;
            overflow: auto;
        }

        .chat-profile-top {
            display: grid;
            justify-items: center;
            gap: 4px;
            text-align: center;
            padding: 8px 4px 10px;
            border-bottom: 1px solid var(--line);
        }

        .chat-profile-top h4 {
            margin: 0;
            font-size: 14px;
        }

        .chat-profile-top p {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
        }

        .chat-profile-status {
            font-size: 11px;
            color: var(--accent);
            font-weight: 600;
        }

        .chat-profile-section {
            display: grid;
            gap: 8px;
        }

        .chat-profile-section p {
            margin: 0;
            font-size: 12px;
            color: var(--muted);
            line-height: 1.4;
        }

        .chat-media-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
        }

        .chat-media-grid div {
            aspect-ratio: 1 / 1;
            border-radius: 8px;
            background: linear-gradient(135deg, color-mix(in oklab, var(--accent) 38%, var(--surface-2)), var(--chip));
            border: 1px solid var(--line);
        }

        .dm-compose-modal {
            z-index: 12;
        }

        .dm-compose-panel {
            width: min(92vw, 520px);
        }

        .dm-compose-body {
            padding: 12px;
            display: grid;
            gap: 10px;
        }

        .dm-compose-top {
            display: grid;
            grid-template-columns: auto 1fr;
            align-items: center;
            gap: 8px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--line);
        }

        .dm-compose-top strong {
            font-size: 12px;
            color: var(--muted);
            letter-spacing: 0.03em;
        }

        .dm-compose-top input {
            border: 0;
            outline: 0;
            background: transparent;
            color: var(--text);
            font-size: 13px;
            width: 100%;
        }

        .dm-suggest-title {
            font-size: 12px;
            color: var(--muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .dm-suggest-list {
            display: grid;
            gap: 6px;
            max-height: 360px;
            overflow: auto;
            padding-right: 2px;
        }

        .dm-suggest-item {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--surface-2);
            display: grid;
            grid-template-columns: 40px 1fr auto;
            align-items: center;
            gap: 8px;
            padding: 8px;
        }

        .dm-suggest-meta {
            min-width: 0;
            display: grid;
            gap: 2px;
        }

        .dm-suggest-name {
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dm-suggest-handle {
            font-size: 12px;
            color: var(--muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dm-suggest-action {
            border: 1px solid var(--line);
            background: var(--surface);
            color: var(--text);
            border-radius: 8px;
            padding: 6px 8px;
            font-size: 12px;
            cursor: pointer;
            white-space: nowrap;
        }

        .dm-suggest-action:hover {
            background: var(--chip);
        }

        .dm-suggest-action.requested {
            background: color-mix(in oklab, var(--accent) 12%, var(--surface));
            border-color: color-mix(in oklab, var(--accent) 34%, var(--line));
            color: var(--accent-2);
        }

        .post {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--surface);
            overflow: hidden;
            transition: background-color 0.35s ease, border-color 0.35s ease;
        }

        .post-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
        }

        .author { display: flex; align-items: center; gap: 9px; }

        .avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1dbd76, #0d9a5c);
            color: #fff;
            font-weight: 700;
            display: grid;
            place-items: center;
            font-size: 13px;
        }

        .author h4 { font-size: 14px; line-height: 1; }
        .post-time { color: var(--muted); font-size: 11px; margin-top: 4px; }

        .post-image {
            background: #ced5de;
            aspect-ratio: 4 / 3;
            display: grid;
            place-items: center;
            font-size: 66px;
            user-select: none;
            overflow: hidden;
        }

        body[data-theme="dark"] .post-image { background: #334155; }

        .post-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .post-body {
            padding: 9px 10px 10px;
            display: grid;
            gap: 8px;
        }

        .post-caption { font-size: 13px; color: var(--text); }
        .post-caption strong { margin-right: 5px; }

        .post-actions { display: flex; gap: 6px; flex-wrap: wrap; }

        .action-btn {
            border: 1px solid var(--line);
            background: var(--surface-2);
            color: var(--text);
            border-radius: 8px;
            padding: 6px 9px;
            font-size: 12px;
            cursor: pointer;
        }

        .action-btn.active.like { color: #ef4444; }

        .meta-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            color: var(--muted);
            font-size: 11px;
        }

        .post-comments {
            border-top: 1px solid var(--line);
            padding-top: 8px;
            display: grid;
            gap: 8px;
        }

        .comment-list {
            display: grid;
            gap: 5px;
            font-size: 12px;
        }

        .comment-item {
            color: var(--text);
            line-height: 1.3;
            word-break: break-word;
        }

        .comment-item strong {
            margin-right: 4px;
        }

        .comment-empty {
            color: var(--muted);
            font-size: 12px;
        }

        .comment-form {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 6px;
        }

        .comment-form input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--surface-2);
            color: var(--text);
            font-size: 12px;
            padding: 7px 9px;
            outline: none;
        }

        .empty {
            border: 1px dashed var(--line);
            border-radius: 10px;
            text-align: center;
            padding: 20px;
            color: var(--muted);
            background: var(--surface-2);
            font-size: 13px;
        }

        .right-col {
            display: grid;
            gap: 10px;
            align-content: start;
        }

        .side-card {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--surface);
            padding: 10px;
            display: grid;
            gap: 8px;
            transition: background-color 0.35s ease, border-color 0.35s ease;
        }

        .side-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 15px;
            font-weight: 700;
        }

        .side-title i { color: var(--accent); }

        .jobs-card {
            padding: 0;
            overflow: hidden;
        }

        .location-map {
            width: 100%;
            height: 200px;
            border-radius: 12px;
            border: 1px solid var(--line);
        }

        .location-status {
            font-size: 12px;
            color: var(--muted);
        }

        .jobs-head {
            padding: 12px 12px 10px;
            border-bottom: 1px solid var(--line);
            background: color-mix(in oklab, var(--surface) 92%, var(--surface-2));
        }

        .jobs-head h4 {
            font-size: 15px;
            margin-bottom: 3px;
        }

        .jobs-head p {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.35;
        }

        .job-list-item {
            display: grid;
            grid-template-columns: 38px 1fr auto;
            gap: 9px;
            padding: 10px 12px;
            border-bottom: 1px solid var(--line);
            align-items: start;
        }

        .job-actions {
            display: grid;
            gap: 6px;
            align-items: center;
            justify-content: end;
        }

        .job-actions button {
            border: 0;
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 12px;
            cursor: pointer;
        }

        .job-actions .job-message {
            background: var(--accent);
            color: #fff;
        }

        .job-actions .job-message:hover {
            background: var(--accent-2);
        }

        .job-actions .job-dismiss {
            background: transparent;
            border: 1px solid var(--line);
            color: var(--text);
        }

        .job-actions .job-dismiss:hover {
            background: var(--chip);
        }

        .job-logo {
            width: 38px;
            height: 38px;
            border-radius: 7px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: #fff;
            display: grid;
            place-items: center;
            font-size: 12px;
            font-weight: 700;
        }

        .job-main h5 {
            font-size: 13px;
            line-height: 1.25;
            color: #0a66c2;
            margin-bottom: 2px;
        }

        .job-main .meta {
            color: var(--text);
            font-size: 12px;
            margin-bottom: 2px;
        }

        .job-main .sub {
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 2px;
        }

        .job-main .foot {
            color: #008e5b;
            font-size: 12px;
        }

        .job-dismiss {
            border: 0;
            background: transparent;
            color: var(--muted);
            cursor: pointer;
            font-size: 14px;
            padding: 2px 4px;
            line-height: 1;
        }

        .job-dismiss:hover {
            color: var(--text);
        }

        .show-all-jobs {
            width: 100%;
            border: 0;
            background: var(--surface);
            color: var(--text);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            padding: 11px 10px;
        }

        .show-all-jobs:hover {
            background: var(--surface-2);
        }

        .create-modal {
            position: fixed;
            inset: 0;
            z-index: 8;
            display: none;
            place-items: center;
            padding: 16px;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(2px);
        }

        .create-modal.open {
            display: grid;
        }

        .create-panel {
            width: min(92vw, 660px);
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .create-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
            border-bottom: 1px solid var(--line);
            font-size: 13px;
            font-weight: 700;
        }

        .create-close {
            border: 0;
            background: transparent;
            color: var(--muted);
            cursor: pointer;
            font-size: 16px;
        }

        .create-step {
            display: none;
            padding: 12px;
            gap: 10px;
        }

        .create-step.active {
            display: grid;
        }

        .upload-drop {
            border: 2px dashed var(--line);
            border-radius: 12px;
            min-height: 220px;
            display: grid;
            place-items: center;
            text-align: center;
            color: var(--muted);
            padding: 14px;
            background: var(--surface-2);
        }

        .upload-drop strong {
            color: var(--text);
            display: block;
            margin-bottom: 4px;
        }

        .hidden-input {
            display: none;
        }

        .preview-image {
            width: 100%;
            max-height: 320px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: var(--surface-2);
        }

        .create-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .create-actions {
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }

        .btn.secondary {
            background: var(--surface-2);
            color: var(--text);
            border: 1px solid var(--line);
        }

        .apply-btn {
            justify-self: start;
            border: 0;
            background: var(--accent);
            color: #fff;
            border-radius: 8px;
            padding: 6px 9px;
            font-size: 12px;
            cursor: pointer;
        }

        .apply-btn.applied {
            background: #64748b;
            cursor: default;
        }

        .skill-item { display: grid; gap: 4px; }

        .skill-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
        }

        .bar {
            height: 7px;
            border-radius: 999px;
            background: var(--chip);
            overflow: hidden;
        }

        .fill {
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, #10b981, #22c55e);
            border-radius: inherit;
            transition: width 0.7s ease;
        }

        .skill-cta {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 5px 8px;
            font-size: 11px;
            background: var(--surface-2);
            color: var(--text);
            cursor: pointer;
            justify-self: start;
        }

        .toast {
            position: fixed;
            right: 16px;
            bottom: 16px;
            background: #111827;
            color: #fff;
            padding: 9px 12px;
            border-radius: 8px;
            font-size: 12px;
            opacity: 0;
            transform: translateY(8px);
            pointer-events: none;
            transition: all 0.2s ease;
        }

        .toast.show { opacity: 1; transform: translateY(0); }

        .floating-theme-toggle {
            position: fixed;
            top: 14px;
            left: 14px;
            z-index: 5;
            line-height: 0;
            display: none;
        }

        .quick-card .theme-switch {
            --toggle-size: 16px;
        }

        .login-entry {
            position: fixed;
            inset: 0;
            z-index: 6;
            display: grid;
            place-items: center;
            pointer-events: none;
            opacity: 0;
            visibility: hidden;
            background: #2d9c23;
            transform: translateY(0);
        }

        .login-entry.active {
            visibility: visible;
            opacity: 1;
            animation: login-entry-reveal 1.85s cubic-bezier(0.23, 0.8, 0.25, 1) forwards;
        }

        .login-entry-badge {
            display: grid;
            justify-items: center;
            gap: 10px;
            color: #ffffff;
        }

        .login-entry-logo {
            width: clamp(140px, 22vw, 220px);
            aspect-ratio: 1 / 1;
            object-fit: contain;
            filter: drop-shadow(0 10px 18px rgba(0, 0, 0, 0.2));
            transform-origin: 50% 58%;
        }

        .login-entry.active .login-entry-logo {
            animation:
                login-logo-pop 1.45s cubic-bezier(0.16, 0.84, 0.22, 1) forwards,
                login-logo-glow 1.55s ease-in-out 0.15s forwards;
        }

        .login-entry-text {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0.05em;
            line-height: 1;
            text-transform: uppercase;
            text-shadow: 0 4px 18px rgba(0, 0, 0, 0.22);
        }

        @keyframes login-entry-reveal {
            0% { transform: translateY(0); }
            62% { transform: translateY(0); }
            100% { transform: translateY(-100%); }
        }

        @keyframes login-logo-pop {
            0% { transform: translateY(14px) scale(0.82) rotate(-2deg); }
            38% { transform: translateY(-6px) scale(1.06) rotate(1deg); }
            62% { transform: translateY(2px) scale(0.98) rotate(-0.4deg); }
            82% { transform: translateY(-2px) scale(1.01) rotate(0.2deg); }
            100% { transform: translateY(0) scale(1) rotate(0deg); }
        }

        @keyframes login-logo-glow {
            0% {
                filter: drop-shadow(0 8px 14px rgba(0, 0, 0, 0.18)) drop-shadow(0 0 0 rgba(16, 185, 129, 0));
            }
            55% {
                filter: drop-shadow(0 14px 24px rgba(0, 0, 0, 0.24)) drop-shadow(0 0 18px rgba(110, 231, 183, 0.45));
            }
            100% {
                filter: drop-shadow(0 11px 19px rgba(0, 0, 0, 0.2)) drop-shadow(0 0 8px rgba(110, 231, 183, 0.25));
            }
        }

        .mode-transition {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 4;
            opacity: 0;
            overflow: hidden;
            --mode-duration: 1.15s;
            --mode-ease: cubic-bezier(0.2, 0.76, 0.22, 1);
            transition: opacity 0.22s ease;
            will-change: opacity;
        }

        .mode-transition.active {
            opacity: 1;
        }

        .mode-transition::before,
        .mode-transition::after {
            content: "";
            position: absolute;
            top: 0;
            pointer-events: none;
            will-change: transform, border-radius;
        }

        .mode-transition::before {
            width: 130%;
            height: 100%;
            left: -120%;
            transform: translateX(0);
        }

        .mode-transition::after {
            width: 36vw;
            min-width: 220px;
            max-width: 420px;
            height: 140vh;
            top: -20vh;
            left: -42vw;
            border-radius: 40% 60% 52% 48% / 52% 46% 54% 48%;
            filter: blur(1.6px);
            opacity: 0.82;
            transform: translateX(0) rotate(0deg);
        }

        .mode-transition.active.to-dark::before {
            background: linear-gradient(90deg, #0b1220 0%, #0f172a 40%, #1e293b 100%);
            animation: slide-night var(--mode-duration) var(--mode-ease) forwards;
        }

        .mode-transition.active.to-dark::after {
            background: radial-gradient(circle at 35% 45%, #4ade80, #0ea765 60%, #065f46 100%);
            animation: splash-night var(--mode-duration) var(--mode-ease) forwards;
        }

        .mode-transition.active.to-light::before {
            background: linear-gradient(90deg, #ffffff 0%, #ecfff4 42%, #34d399 100%);
            animation: slide-day var(--mode-duration) var(--mode-ease) forwards;
        }

        .mode-transition.active.to-light::after {
            background: radial-gradient(circle at 35% 45%, #ffffff, #d1fae5 58%, #34d399 100%);
            animation: splash-day var(--mode-duration) var(--mode-ease) forwards;
        }

        @keyframes slide-night {
            0% { transform: translateX(0); }
            100% { transform: translateX(188%); }
        }

        @keyframes slide-day {
            0% { transform: translateX(0); }
            100% { transform: translateX(188%); }
        }

        @keyframes splash-night {
            0% {
                transform: translateX(0) rotate(0deg);
                border-radius: 40% 60% 52% 48% / 52% 46% 54% 48%;
            }
            45% {
                border-radius: 50% 50% 56% 44% / 46% 56% 44% 54%;
            }
            100% {
                transform: translateX(198vw) rotate(14deg);
                border-radius: 56% 44% 48% 52% / 53% 42% 58% 47%;
            }
        }

        @keyframes splash-day {
            0% {
                transform: translateX(0) rotate(0deg);
                border-radius: 40% 60% 52% 48% / 52% 46% 54% 48%;
            }
            45% {
                border-radius: 49% 51% 45% 55% / 54% 44% 56% 46%;
            }
            100% {
                transform: translateX(198vw) rotate(12deg);
                border-radius: 60% 40% 52% 48% / 48% 58% 42% 52%;
            }
        }

        @media (max-width: 1080px) {
            .content { grid-template-columns: 92px minmax(0, 1fr); }
            .right-col { display: none; }
        }

        @media (max-width: 820px) {
            .content { grid-template-columns: 1fr; }
            .left-col { display: none; }
            .create-grid { grid-template-columns: 1fr; }
            .messages-view { grid-template-columns: 1fr; min-height: 520px; }
            .chat-list-panel { border-right: 0; border-bottom: 1px solid var(--line); }
            .chat-thread-panel { border-right: 0; }
            .chat-profile-panel { display: none; }
            .messages-view.dm-thread-open { grid-template-columns: 1fr; }
            .messages-view.dm-thread-open .chat-list-panel { display: none; }
            .messages-view.dm-thread-open .chat-thread-panel { display: grid; }
        }

        /* Home UX refresh */
        .content {
            grid-template-columns: 220px minmax(0, 1fr) 320px;
            gap: 18px;
            padding: 18px;
        }

        .content.messages-open {
            grid-template-columns: 220px minmax(0, 1fr);
        }

        .left-col {
            padding: 8px 10px 8px 0;
            gap: 14px;
        }

        .brand {
            min-height: auto;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
            padding: 8px 10px;
            font-size: 25px;
        }

        .brand .brand-word {
            display: inline;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .brand-logo {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            object-fit: cover;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
        }

        .menu button,
        .menu a {
            justify-content: flex-start;
            gap: 12px;
            font-size: 14px;
            padding: 10px 12px;
        }

        .menu i {
            width: 20px;
            font-size: 18px;
        }

        .menu span {
            display: inline;
        }

        .left-col .quick-card {
            display: grid;
        }

        .theme-card-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .theme-card-row .small-label {
            margin: 0;
        }

        .home-welcome {
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--surface);
            padding: 14px 16px;
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: center;
        }

        .home-welcome h2 {
            margin: 0;
            font-size: 22px;
            line-height: 1.1;
        }

        .home-welcome p {
            margin: 5px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .welcome-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .notif-toggle {
            position: relative;
        }

        .notif-badge {
            min-width: 18px;
            height: 18px;
            border-radius: 999px;
            background: #ef4444;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
        }

        .notif-badge.is-hidden {
            display: none;
        }

        .notif-panel {
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--surface);
            padding: 10px;
            display: grid;
            gap: 8px;
        }

        .notif-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .notif-head h3 {
            margin: 0;
            font-size: 15px;
        }

        .notif-list {
            display: grid;
            gap: 8px;
            max-height: 320px;
            overflow: auto;
            padding-right: 2px;
        }

        .notif-item {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--surface-2);
            padding: 10px;
            display: grid;
            gap: 6px;
        }

        .notif-item-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .notif-item-title {
            font-size: 13px;
            font-weight: 700;
        }

        .notif-item-time {
            color: var(--muted);
            font-size: 11px;
            white-space: nowrap;
        }

        .notif-item-body {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.35;
        }

        .notif-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .notif-btn {
            border: 1px solid var(--line);
            background: var(--surface);
            color: var(--text);
            border-radius: 8px;
            padding: 6px 9px;
            font-size: 12px;
            cursor: pointer;
        }

        .notif-btn.primary {
            border-color: var(--accent);
            background: color-mix(in oklab, var(--accent) 16%, var(--surface));
            color: var(--accent-2);
        }

        .notif-btn:hover {
            background: var(--chip);
        }

        .toolbar,
        .composer,
        .post,
        .side-card {
            border-radius: 14px;
        }

        input[type="text"], textarea, select {
            font-size: 14px;
            padding: 10px 12px;
        }

        .post-caption {
            font-size: 14px;
            line-height: 1.4;
        }

        .meta-row {
            font-size: 12px;
        }

        @media (max-width: 1080px) {
            .content {
                grid-template-columns: 84px minmax(0, 1fr);
            }

            .content.messages-open {
                grid-template-columns: 84px minmax(0, 1fr);
            }

            .brand .brand-word,
            .menu span,
            .left-col .quick-card {
                display: none;
            }

            .menu button,
            .menu a {
                justify-content: center;
                gap: 0;
                padding: 11px 6px;
            }
        }

        @media (max-width: 820px) {
            .home-welcome {
                display: grid;
                gap: 10px;
            }

            .welcome-actions {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body data-theme="light">
    <div class="login-entry" id="loginEntry" aria-hidden="true">
        <div class="login-entry-badge">
            <img class="login-entry-logo" src="uploads/kita_logo.png" alt="KITA logo" />
            <div class="login-entry-text">Welcome to KITA</div>
        </div>
    </div>
    <div class="mode-transition" id="modeTransition" aria-hidden="true"></div>
    <div class="money-rain" id="moneyRain" aria-hidden="true"></div>
    <div class="app">
        <div class="content">
            <aside class="left-col">
                <h1 class="brand">
                    <img class="brand-logo" src="uploads/kita_logo.png" alt="KITA logo" />
                    <span class="brand-word">KITA</span>
                </h1>
                <ul class="menu" id="menuList">
                    <li><button class="active" data-mode="home" title="Home"><i class="fa-solid fa-house"></i><span>Home</span></button></li>
                    <li><button data-mode="explore" title="Explore"><i class="fa-regular fa-compass"></i><span>Explore</span></button></li>
                    <li><button data-mode="messages" title="Messages"><i class="fa-regular fa-message"></i><span>Messages</span></button></li>
                    <li><button data-mode="saved" title="Saved"><i class="fa-regular fa-bookmark"></i><span>Saved</span></button></li>
                <li><a href="<?php echo e($profileHref); ?>" title="Profile"><i class="fa-solid fa-user"></i><span>Profile</span></a></li>
                    <li><a class="logout-link" href="logout.php" title="Logout"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a></li>
                </ul>
                <div class="quick-card">
                    <div class="theme-card-row">
                        <div class="small-label">Dark mode</div>
                        <label class="theme-switch" aria-label="Toggle dark mode">
                            <input type="checkbox" class="theme-switch__checkbox" id="themeBtn">
                            <div class="theme-switch__container">
                                <div class="theme-switch__clouds"></div>
                                <div class="theme-switch__stars-container">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 144 55" fill="none">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M135.831 3.00688C135.055 3.85027 134.111 4.29946 133 4.35447C134.111 4.40947 135.055 4.85867 135.831 5.71123C136.607 6.55462 136.996 7.56303 136.996 8.72727C136.996 7.95722 137.172 7.25134 137.525 6.59129C137.886 5.93124 138.372 5.39954 138.98 5.00535C139.598 4.60199 140.268 4.39114 141 4.35447C139.88 4.2903 138.936 3.85027 138.16 3.00688C137.384 2.16348 136.996 1.16425 136.996 0C136.996 1.16425 136.607 2.16348 135.831 3.00688ZM31 23.3545C32.1114 23.2995 33.0551 22.8503 33.8313 22.0069C34.6075 21.1635 34.9956 20.1642 34.9956 19C34.9956 20.1642 35.3837 21.1635 36.1599 22.0069C36.9361 22.8503 37.8798 23.2903 39 23.3545C38.2679 23.3911 37.5976 23.602 36.9802 24.0053C36.3716 24.3995 35.8864 24.9312 35.5248 25.5913C35.172 26.2513 34.9956 26.9572 34.9956 27.7273C34.9956 26.563 34.6075 25.5546 33.8313 24.7112C33.0551 23.8587 32.1114 23.4095 31 23.3545ZM0 36.3545C1.11136 36.2995 2.05513 35.8503 2.83131 35.0069C3.6075 34.1635 3.99559 33.1642 3.99559 32C3.99559 33.1642 4.38368 34.1635 5.15987 35.0069C5.93605 35.8503 6.87982 36.2903 8 36.3545C7.26792 36.3911 6.59757 36.602 5.98015 37.0053C5.37155 37.3995 4.88644 37.9312 4.52481 38.5913C4.172 39.2513 3.99559 39.9572 3.99559 40.7273C3.99559 39.563 3.6075 38.5546 2.83131 37.7112C2.05513 36.8587 1.11136 36.4095 0 36.3545ZM56.8313 24.0069C56.0551 24.8503 55.1114 25.2995 54 25.3545C55.1114 25.4095 56.0551 25.8587 56.8313 26.7112C57.6075 27.5546 57.9956 28.563 57.9956 29.7273C57.9956 28.9572 58.172 28.2513 58.5248 27.5913C58.8864 26.9312 59.3716 26.3995 59.9802 26.0053C60.5976 25.602 61.2679 25.3911 62 25.3545C60.8798 25.2903 59.9361 24.8503 59.1599 24.0069C58.3837 23.1635 57.9956 22.1642 57.9956 21C57.9956 22.1642 57.6075 23.1635 56.8313 24.0069ZM81 25.3545C82.1114 25.2995 83.0551 24.8503 83.8313 24.0069C84.6075 23.1635 84.9956 22.1642 84.9956 21C84.9956 22.1642 85.3837 23.1635 86.1599 24.0069C86.9361 24.8503 87.8798 25.2903 89 25.3545C88.2679 25.3911 87.5976 25.602 86.9802 26.0053C86.3716 26.3995 85.8864 26.9312 85.5248 27.5913C85.172 28.2513 84.9956 28.9572 84.9956 29.7273C84.9956 28.563 84.6075 27.5546 83.8313 26.7112C83.0551 25.8587 82.1114 25.4095 81 25.3545ZM136 36.3545C137.111 36.2995 138.055 35.8503 138.831 35.0069C139.607 34.1635 139.996 33.1642 139.996 32C139.996 33.1642 140.384 34.1635 141.16 35.0069C141.936 35.8503 142.88 36.2903 144 36.3545C143.268 36.3911 142.598 36.602 141.98 37.0053C141.372 37.3995 140.886 37.9312 140.525 38.5913C140.172 39.2513 139.996 39.9572 139.996 40.7273C139.996 39.563 139.607 38.5546 138.831 37.7112C138.055 36.8587 137.111 36.4095 136 36.3545ZM101.831 49.0069C101.055 49.8503 100.111 50.2995 99 50.3545C100.111 50.4095 101.055 50.8587 101.831 51.7112C102.607 52.5546 102.996 53.563 102.996 54.7273C102.996 53.9572 103.172 53.2513 103.525 52.5913C103.886 51.9312 104.372 51.3995 104.98 51.0053C105.598 50.602 106.268 50.3911 107 50.3545C105.88 50.2903 104.936 49.8503 104.16 49.0069C103.384 48.1635 102.996 47.1642 102.996 46C102.996 47.1642 102.607 48.1635 101.831 49.0069Z" fill="currentColor"></path>
                                    </svg>
                                </div>
                                <div class="theme-switch__circle-container">
                                    <div class="theme-switch__sun-moon-container">
                                        <div class="theme-switch__moon">
                                            <div class="theme-switch__spot"></div>
                                            <div class="theme-switch__spot"></div>
                                            <div class="theme-switch__spot"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="quick-card">
                    <div class="small-label">Tip</div>
                    <div style="font-size:12px;color:var(--muted);line-height:1.35;">Use the composer to add your own post. Likes, saves, applied jobs, and theme are persisted.</div>
                </div>
            </aside>

            <main class="center-col">
                <section class="home-welcome">
                    <div>
                        <h2>Welcome to KITA, <?php echo e($viewerName); ?></h2>
                        <p>Track opportunities, share updates, and manage your growth in one place.</p>
                    </div>
                    <div class="welcome-actions">
                        <button class="action-btn notif-toggle" id="notifToggleBtn" type="button">
                            <i class="fa-regular fa-bell"></i>
                            Notifications
                            <span class="notif-badge is-hidden" id="notifBadge">0</span>
                        </button>
                        <button class="btn" id="quickPostBtn" type="button"><i class="fa-regular fa-square-plus"></i> New Post</button>
                    </div>
                </section>
                <section class="notif-panel is-hidden" id="notifPanel">
                    <div class="notif-head">
                        <h3>Notifications</h3>
                        <button class="notif-btn" id="markNotifsReadBtn" type="button">Mark as read</button>
                    </div>
                    <div class="notif-list" id="notifList"></div>
                </section>

                <section class="toolbar">
                    <div class="toolbar-top">
                        <input id="postSearch" type="text" placeholder="Search posts by author or caption..." />
                        <select id="sortPosts" aria-label="Sort posts">
                            <option value="new">Newest</option>
                            <option value="likes">Most liked</option>
                        </select>
                    </div>
                    <div class="chip-row" id="categoryChips"></div>
                </section>

                <section class="composer">
                    <div class="composer-headline">Create a post</div>
                    <button class="create-trigger" id="openCreatePostBtn">
                        <i class="fa-regular fa-square-plus"></i>
                        Create Post
                    </button>
                </section>

                <section class="feed" id="feed"></section>

                <section class="messages-view is-hidden" id="messagesView">
                    <aside class="chat-list-panel">
                        <div class="chat-list-head">
                            <strong>kita_messages</strong>
                            <button class="chat-compose-launch" id="addFriendBtn" type="button" title="New chat">
                                <i class="fa-regular fa-pen-to-square"></i>
                                New chat
                            </button>
                        </div>
                        <div class="chat-search-wrap">
                            <input id="chatSearchInput" type="text" placeholder="Search chats..." />
                        </div>
                        <div class="chat-list" id="chatList"></div>
                    </aside>
                    <section class="chat-thread-panel">
                        <div class="chat-thread-head">
                            <div class="chat-thread-left">
                                <button class="action-btn" id="closeThreadBtn" type="button" title="Back to chats"><i class="fa-solid fa-arrow-left"></i></button>
                                <div class="chat-thread-user">
                                    <div class="dm-avatar" id="chatThreadAvatar">M</div>
                                    <div>
                                        <div class="chat-thread-title" id="chatThreadTitle">Messages</div>
                                        <div class="chat-thread-sub" id="chatThreadSub">Select a conversation</div>
                                    </div>
                                </div>
                            </div>
                            <div class="chat-thread-actions">
                                <button class="action-btn" id="startAudioCallBtn" type="button" title="Start audio call"><i class="fa-solid fa-phone"></i></button>
                                <button class="action-btn" id="startVideoCallBtn" type="button" title="Start video call"><i class="fa-solid fa-video"></i></button>
                            </div>
                        </div>
                        <div class="chat-thread-body" id="chatThreadBody"></div>
                        <div class="chat-compose">
                            <input id="chatMessageInput" type="text" placeholder="Write a message..." />
                            <button class="btn" id="sendChatBtn" type="button"><i class="fa-regular fa-paper-plane"></i> Send</button>
                        </div>
                    </section>
                    <aside class="chat-profile-panel">
                        <div class="chat-profile-top">
                            <div class="dm-avatar large" id="chatProfileAvatar">M</div>
                            <h4 id="chatProfileName">Select a chat</h4>
                            <p id="chatProfileHandle">@kita_user</p>
                            <div class="chat-profile-status" id="chatProfileStatus">No conversation selected</div>
                        </div>
                        <div class="chat-profile-section">
                            <div class="small-label">Bio</div>
                            <p id="chatProfileBio">Open a conversation to view profile details.</p>
                        </div>
                        <div class="chat-profile-section">
                            <div class="small-label">Shared Tags</div>
                            <div class="chip-row" id="chatProfileTags"></div>
                        </div>
                        <div class="chat-profile-section">
                            <div class="small-label">Shared Media</div>
                            <div class="chat-media-grid" id="chatMediaGrid"></div>
                        </div>
                    </aside>
                </section>
            </main>

            <aside class="right-col" id="rightCol">
                <?php if ($isEmployerView): ?>
                    <section class="side-card jobs-card">
                        <div class="jobs-head">
                            <h4>Your Job Offers</h4>
                            <p>These are the roles your company is currently offering.</p>
                        </div>
                        <div id="jobsWrap">
                            <?php if (count($employerJobs) === 0): ?>
                                <div class="empty" style="margin:10px;">No posted jobs yet.</div>
                            <?php else: ?>
                                <?php foreach ($employerJobs as $job): ?>
                                    <article class="job-list-item">
                                        <div class="job-logo"><?php echo e(strtoupper(substr((string) ($job['job_title'] ?? 'J'), 0, 2))); ?></div>
                                        <div class="job-main">
                                            <h5><?php echo e((string) ($job['job_title'] ?? 'Job Offer')); ?></h5>
                                            <div class="meta"><?php echo e((string) ($job['strand_required'] ?? 'General')); ?></div>
                                            <div class="sub"><?php echo e((string) ($job['location'] ?? 'Location N/A')); ?><?php if (!empty($job['job_type'])): ?> · <?php echo e(ucfirst((string) $job['job_type'])); ?><?php endif; ?></div>
                                            <div class="foot"><?php echo e((string) ($job['skills_required'] ?? '')); ?></div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button class="show-all-jobs" id="showAllJobsBtn">Manage jobs <i class="fa-solid fa-arrow-right"></i></button>
                    </section>
                <?php else: ?>
                    <section class="side-card jobs-card">
                        <div class="jobs-head">
                            <h4>Top job picks for you</h4>
                            <p>Based on your profile, preferences, and recent activity.</p>
                        </div>
                        <div id="jobsWrap"></div>
                        <button class="show-all-jobs" id="showAllJobsBtn">Show all <i class="fa-solid fa-arrow-right"></i></button>
                    </section>

                    <section class="side-card" id="locationCard">
                        <h3 class="side-title"><i class="fa-solid fa-location-dot"></i> Your Location</h3>
                        <div id="locationMap" class="location-map"></div>
                        <div id="locationStatus" class="location-status">Detecting your location…</div>
                    </section>

                    <section class="side-card" id="skillsCard">
                        <h3 class="side-title"><i class="fa-solid fa-chart-line"></i> Your Skill Journey</h3>
                    </section>
                <?php endif; ?>
            </aside>
        </div>
    </div>

    <div class="create-modal dm-compose-modal" id="friendComposeModal" aria-hidden="true">
        <div class="create-panel dm-compose-panel">
            <div class="create-panel-head">
                <span>New message</span>
                <button class="create-close" id="closeFriendComposeBtn" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="dm-compose-body">
                <div class="dm-compose-top">
                    <strong>To:</strong>
                    <input id="friendSearchInput" type="text" placeholder="Search people or companies..." />
                </div>
                <div class="dm-suggest-title">Suggested</div>
                <div class="dm-suggest-list" id="friendSuggestList"></div>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"></div>
    <div class="create-modal" id="createModal" aria-hidden="true">
        <div class="create-panel">
            <div class="create-panel-head">
                <span>Create new post</span>
                <button class="create-close" id="closeCreatePostBtn" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="create-step active" id="createStepUpload">
                <label class="upload-drop" for="createPhotoInput">
                    <div>
                        <strong>Upload a photo (optional)</strong>
                        <div>Select an image, or skip to post text only</div>
                    </div>
                </label>
                <input class="hidden-input" id="createPhotoInput" type="file" accept="image/*" />
                <div class="create-actions">
                    <span class="small-label">Step 1 of 2 - You can also post without an image</span>
                    <button class="btn secondary" id="skipPhotoBtn"><i class="fa-solid fa-arrow-right"></i> Skip Photo</button>
                    <button class="btn" id="goCaptionStepBtn"><i class="fa-solid fa-arrow-right"></i> Next</button>
                </div>
            </div>

            <div class="create-step" id="createStepCaption">
                <img class="preview-image" id="createPreviewImage" alt="Post preview" />
                <div class="create-grid">
                    <input id="createAuthorInput" type="text" maxlength="18" placeholder="Username (optional)" />
                    <select id="createCategoryInput">
                        <option value="ict">ICT</option>
                        <option value="dev">DEV</option>
                        <option value="design">DESIGN</option>
                        <option value="data">DATA</option>
                    </select>
                </div>
                <div class="create-grid">
                    <select id="createStrandInput">
                        <option value="">Target Audience (Strand)</option>
                        <option value="STEM">STEM</option>
                        <option value="ICT">ICT</option>
                        <option value="ABM">ABM</option>
                        <option value="HUMSS">HUMSS</option>
                        <option value="GAS">GAS</option>
                        <option value="STEM">STEM</option>
                        <option value="TVL">TVL</option>
                        <option value="Arts and Design">Arts and Design</option>
                        <option value="Sports">Sports</option>
                        <option value="All">All</option>
                    </select>
                    <input id="createLocationInput" type="text" maxlength="80" placeholder="Location (optional)" />
                </div>
                <textarea id="createCaptionInput" maxlength="220" placeholder="Write a caption..."></textarea>
                <div class="create-actions">
                    <button class="btn secondary" id="backToUploadBtn"><i class="fa-solid fa-arrow-left"></i> Back</button>
                    <button class="btn" id="publishPostBtn"><i class="fa-solid fa-paper-plane"></i> Publish</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script>
        const APP_CONTEXT = {
            isEmployer: <?php echo $isEmployerView ? 'true' : 'false'; ?>
        };
        const VIEWER_NAME = <?php echo json_encode($viewerName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const VIEWER_HANDLE = <?php echo json_encode($viewerHandle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const ACCEPTED_APPLICATIONS = <?php echo json_encode($acceptedApplications, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        const STORAGE_KEYS = {
            likes: "kita_likes",
            saves: "kita_saves",
            comments: "kita_comments",
            jobs: "kita_jobs_applied",
            dismissedJobs: "kita_jobs_dismissed",
            customPosts: "kita_custom_posts",
            theme: "kita_theme",
            skills: "kita_skills",
            messages: <?php echo json_encode('kita_messages_' . $viewerStorageId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            friendRequests: <?php echo json_encode('kita_friend_requests_' . $viewerStorageId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            clearedSeedMessages: <?php echo json_encode('kita_messages_seed_cleared_v1_' . $viewerStorageId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            globalFriendRequests: "kita_friend_requests_global_v1",
            seenApplicationNotifications: <?php echo json_encode('kita_notifications_seen_apps_' . $viewerStorageId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
        };

        const initialPosts = [
            { id: 1, author: "precious_ict", avatar: "P", time: "2h", image: "🌿", caption: "Built a mini portfolio homepage with clean responsive cards.", category: "ict", likes: 18 },
            { id: 2, author: "jay_codes", avatar: "J", time: "5h", image: "💻", caption: "Practiced API calls and loading states in JavaScript.", category: "dev", likes: 34 },
            { id: 3, author: "maria_ui", avatar: "M", time: "8h", image: "🎨", caption: "Refined spacing system and card hierarchy for our feed.", category: "design", likes: 25 },
            { id: 4, author: "ana_data", avatar: "A", time: "1d", image: "📊", caption: "Reviewed SQL joins and dashboard metrics today.", category: "data", likes: 29 }
        ];

        const jobs = <?php
            $jsJobs = array_map(function($j) {
                $initials = strtoupper(substr($j['company'], 0, 2));
                $sub = trim($j['location'] . ($j['type'] !== '' ? ' • ' . ucfirst($j['type']) : ''));
                $foot = $j['salary'] !== '' ? $j['salary'] : 'View details';
                return [
                    'id'      => $j['id'],
                    'employerId' => (int) ($j['employer_id'] ?? 0),
                    'logo'    => $j['pic'] !== '' ? '<img src="' . htmlspecialchars($j['pic'], ENT_QUOTES) . '" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">' : $initials,
                    'title'   => htmlspecialchars($j['title'], ENT_QUOTES),
                    'company' => htmlspecialchars($j['company'], ENT_QUOTES),
                    'sub'     => htmlspecialchars($sub, ENT_QUOTES),
                    'foot'    => htmlspecialchars($foot, ENT_QUOTES),
                ];
            }, $sidebarJobs);
            echo json_encode($jsJobs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ?>;

        const defaultSkills = [
            { key: "htmlcss", name: "HTML/CSS", value: 86 },
            { key: "python", name: "Python", value: 60 },
            { key: "sql", name: "SQL", value: 45 }
        ];

        const legacySeedConversations = [
            {
                id: 1,
                name: "Precious ICT",
                handle: "@precious_ict",
                status: "Active now",
                bio: "Front-end learner focused on responsive UI and clean layouts.",
                tags: ["UI", "HTML", "CSS"],
                media: [1, 2, 3, 4, 5, 6],
                messages: [
                    { from: "them", text: "Hi! Did you finish the portfolio section?", time: "09:12" },
                    { from: "me", text: "Yes, I just pushed the responsive cards update.", time: "09:15" }
                ]
            },
            {
                id: 2,
                name: "Maria UI",
                handle: "@maria_ui",
                status: "Last seen 10m ago",
                bio: "Design enthusiast. I love spacing systems and component polish.",
                tags: ["Design", "Figma", "UX"],
                media: [1, 2, 3, 4],
                messages: [
                    { from: "them", text: "Can you review the spacing in the feed cards?", time: "08:41" },
                    { from: "me", text: "Sure, I will check and send notes.", time: "08:44" }
                ]
            },
            {
                id: 3,
                name: "Jay Codes",
                handle: "@jay_codes",
                status: "Last seen 1h ago",
                bio: "JavaScript and APIs. Building useful mini projects every week.",
                tags: ["JavaScript", "API", "Node"],
                media: [1, 2, 3],
                messages: [
                    { from: "them", text: "API loading states are working now.", time: "07:31" }
                ]
            }
        ];

        const defaultConversations = [];

        const userDirectory = <?php echo json_encode($directoryUsers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const companyDirectory = <?php echo json_encode($directoryCompanies, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const combinedDirectory = [...(userDirectory || []), ...(companyDirectory || [])];
        const userDirectoryByName = Object.fromEntries(
            (combinedDirectory || []).map((user) => [String(user.name || "").toLowerCase(), user])
        );
        const userDirectoryByHandle = Object.fromEntries(
            (combinedDirectory || []).map((user) => [String(user.handle || "").toLowerCase(), user])
        );

        const loadJSON = (key, fallback) => {
            try {
                const raw = localStorage.getItem(key);
                return raw ? JSON.parse(raw) : fallback;
            } catch {
                return fallback;
            }
        };

        const saveJSON = (key, value) => localStorage.setItem(key, JSON.stringify(value));

        async function apiRequest(url, payload = null, method = "POST") {
            const options = {
                method,
                credentials: "same-origin",
                headers: {}
            };
            if (payload) {
                options.headers["Content-Type"] = "application/json";
                options.body = JSON.stringify(payload);
            }
            const response = await fetch(url, options);
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data?.ok) {
                throw new Error(data?.error || `Request failed (${response.status})`);
            }
            return data;
        }

        function normalizeConversation(raw) {
            const friendId = Number(raw?.targetUserId ?? 0);
            const targetEmployerId = Number(raw?.targetEmployerId ?? 0);
            const threadId = Number(raw?.threadId ?? 0);
            const messages = Array.isArray(raw?.messages) ? raw.messages.map((m) => ({
                from: m?.from === "them" ? "them" : "me",
                text: String(m?.text || ""),
                time: String(m?.time || "")
            })) : [];
            return {
                id: Number(raw?.id ?? (threadId > 0 ? threadId : (-1 * Math.max(1, friendId)))),
                threadId: threadId > 0 ? threadId : null,
                targetUserId: friendId > 0 ? friendId : null,
                targetEmployerId: targetEmployerId > 0 ? targetEmployerId : null,
                name: String(raw?.name || "KITA User"),
                handle: String(raw?.handle || slugifyHandle(String(raw?.name || "KITA User"))),
                status: String(raw?.status || "Connected"),
                bio: String(raw?.bio || "No bio provided."),
                tags: Array.isArray(raw?.tags) ? raw.tags : [],
                media: Array.isArray(raw?.media) ? raw.media : [],
                avatar: String(raw?.avatar || ""),
                isCompany: Boolean(raw?.isCompany),
                messages
            };
        }

        async function syncSocialState(showErrorToast = false) {
            try {
                const payload = await apiRequest("api/social_state.php", null, "GET");
                const fromServer = Array.isArray(payload.conversations) ? payload.conversations.map(normalizeConversation) : [];
                state.conversations = fromServer;
                state.incomingRequests = Array.isArray(payload.incoming_requests) ? payload.incoming_requests : [];
                state.outgoingRequests = Array.isArray(payload.outgoing_requests) ? payload.outgoing_requests : [];
                state.serverNotifications = Array.isArray(payload.notifications) ? payload.notifications : [];

                if (state.activeChatId !== null) {
                    const stillExists = state.conversations.some((c) => Number(c.id) === Number(state.activeChatId));
                    if (!stillExists) state.activeChatId = state.conversations[0]?.id ?? null;
                }
                saveJSON(STORAGE_KEYS.messages, state.conversations);
                renderMessages();
                renderNotifications();
                renderFriendSuggestions();
            } catch (error) {
                if (showErrorToast) toast("Could not sync chat data right now");
            }
        }

        function isLegacySeedMessages(conversations) {
            if (!Array.isArray(conversations) || conversations.length !== legacySeedConversations.length) return false;
            const key = (chat) => `${chat?.name || ""}|${chat?.messages?.[0]?.text || ""}`;
            const current = conversations.map(key).sort().join("::");
            const seed = legacySeedConversations.map(key).sort().join("::");
            return current === seed;
        }

        function loadConversations() {
            const stored = loadJSON(STORAGE_KEYS.messages, null);
            if (!Array.isArray(stored)) return defaultConversations;
            const alreadyCleared = localStorage.getItem(STORAGE_KEYS.clearedSeedMessages) === "1";
            if (!alreadyCleared && isLegacySeedMessages(stored)) {
                localStorage.setItem(STORAGE_KEYS.clearedSeedMessages, "1");
                saveJSON(STORAGE_KEYS.messages, []);
                return [];
            }
            return stored;
        }

        const state = {
            mode: "home",
            category: "all",
            postSearch: "",
            postSort: "new",
            dbPosts: [],
            likes: loadJSON(STORAGE_KEYS.likes, {}),
            saves: loadJSON(STORAGE_KEYS.saves, {}),
            comments: loadJSON(STORAGE_KEYS.comments, {}),
            applied: loadJSON(STORAGE_KEYS.jobs, {}),
            dismissedJobs: loadJSON(STORAGE_KEYS.dismissedJobs, {}),
            customPosts: loadJSON(STORAGE_KEYS.customPosts, []),
            skills: loadJSON(STORAGE_KEYS.skills, defaultSkills),
            conversations: loadConversations(),
            friendRequests: loadJSON(STORAGE_KEYS.friendRequests, {}),
            globalFriendRequests: loadJSON(STORAGE_KEYS.globalFriendRequests, {}),
            seenApplicationNotifications: loadJSON(STORAGE_KEYS.seenApplicationNotifications, {}),
            incomingRequests: [],
            outgoingRequests: [],
            serverNotifications: [],
            activeChatId: null,
            notificationsOpen: false,
            theme: localStorage.getItem(STORAGE_KEYS.theme) || "light"
        };

        const els = {
            body: document.body,
            content: document.querySelector(".content"),
            moneyRain: document.getElementById("moneyRain"),
            toolbar: document.querySelector(".toolbar"),
            composer: document.querySelector(".composer"),
            feed: document.getElementById("feed"),
            rightCol: document.getElementById("rightCol"),
            jobsWrap: document.getElementById("jobsWrap"),
            skillsCard: document.getElementById("skillsCard"),
            locationMap: document.getElementById("locationMap"),
            locationStatus: document.getElementById("locationStatus"),
            toast: document.getElementById("toast"),
            postSearch: document.getElementById("postSearch"),
            sortPosts: document.getElementById("sortPosts"),
            categoryChips: document.getElementById("categoryChips"),
            quickPostBtn: document.getElementById("quickPostBtn"),
            notifToggleBtn: document.getElementById("notifToggleBtn"),
            notifBadge: document.getElementById("notifBadge"),
            notifPanel: document.getElementById("notifPanel"),
            notifList: document.getElementById("notifList"),
            markNotifsReadBtn: document.getElementById("markNotifsReadBtn"),
            openCreatePostBtn: document.getElementById("openCreatePostBtn"),
            createModal: document.getElementById("createModal"),
            closeCreatePostBtn: document.getElementById("closeCreatePostBtn"),
            createPhotoInput: document.getElementById("createPhotoInput"),
            createPreviewImage: document.getElementById("createPreviewImage"),
            createAuthorInput: document.getElementById("createAuthorInput"),
            createCategoryInput: document.getElementById("createCategoryInput"),
            createStrandInput: document.getElementById("createStrandInput"),
            createLocationInput: document.getElementById("createLocationInput"),
            createCaptionInput: document.getElementById("createCaptionInput"),
            goCaptionStepBtn: document.getElementById("goCaptionStepBtn"),
            backToUploadBtn: document.getElementById("backToUploadBtn"),
            publishPostBtn: document.getElementById("publishPostBtn"),
            createStepUpload: document.getElementById("createStepUpload"),
            createStepCaption: document.getElementById("createStepCaption"),
            showAllJobsBtn: document.getElementById("showAllJobsBtn"),
            messagesView: document.getElementById("messagesView"),
            chatSearchInput: document.getElementById("chatSearchInput"),
            chatList: document.getElementById("chatList"),
            addFriendBtn: document.getElementById("addFriendBtn"),
            friendComposeModal: document.getElementById("friendComposeModal"),
            closeFriendComposeBtn: document.getElementById("closeFriendComposeBtn"),
            friendSearchInput: document.getElementById("friendSearchInput"),
            friendSuggestList: document.getElementById("friendSuggestList"),
            chatThreadAvatar: document.getElementById("chatThreadAvatar"),
            chatThreadTitle: document.getElementById("chatThreadTitle"),
            chatThreadSub: document.getElementById("chatThreadSub"),
            closeThreadBtn: document.getElementById("closeThreadBtn"),
            chatThreadBody: document.getElementById("chatThreadBody"),
            chatMessageInput: document.getElementById("chatMessageInput"),
            sendChatBtn: document.getElementById("sendChatBtn"),
            startAudioCallBtn: document.getElementById("startAudioCallBtn"),
            startVideoCallBtn: document.getElementById("startVideoCallBtn"),
            chatProfileAvatar: document.getElementById("chatProfileAvatar"),
            chatProfileName: document.getElementById("chatProfileName"),
            chatProfileHandle: document.getElementById("chatProfileHandle"),
            chatProfileStatus: document.getElementById("chatProfileStatus"),
            chatProfileBio: document.getElementById("chatProfileBio"),
            chatProfileTags: document.getElementById("chatProfileTags"),
            chatMediaGrid: document.getElementById("chatMediaGrid"),
            themeBtn: document.getElementById("themeBtn"),
            floatingThemeBtn: document.getElementById("floatingThemeBtn"),
            loginEntry: document.getElementById("loginEntry"),
            modeTransition: document.getElementById("modeTransition"),
            menuButtons: Array.from(document.querySelectorAll("#menuList button"))
        };

        const categories = ["all", "ict", "dev", "design", "data"];
        let themeAnimating = false;
        let createPostDraft = { imageData: "" };
        let locationMap = null;
        let locationMarker = null;

        function toast(message) {
            els.toast.textContent = message;
            els.toast.classList.add("show");
            clearTimeout(toast.timer);
            toast.timer = setTimeout(() => els.toast.classList.remove("show"), 1400);
        }

        function allPosts() {
            if (state.dbPosts.length) {
                return [...state.dbPosts, ...state.customPosts];
            }
            return [...state.customPosts];
        }

        function formatWhen(time) {
            if (time === "now") return "Just now";
            const parsed = new Date(time);
            if (!Number.isNaN(parsed.getTime())) {
                const diffSec = Math.max(1, Math.floor((Date.now() - parsed.getTime()) / 1000));
                if (diffSec < 60) return `${diffSec}s`;
                const diffMin = Math.floor(diffSec / 60);
                if (diffMin < 60) return `${diffMin}m`;
                const diffHour = Math.floor(diffMin / 60);
                if (diffHour < 24) return `${diffHour}h`;
                const diffDay = Math.floor(diffHour / 24);
                return `${diffDay}d`;
            }
            return time;
        }

        function escapeHtml(value) {
            return String(value ?? "")
                .replaceAll("&", "&amp;")
                .replaceAll("<", "&lt;")
                .replaceAll(">", "&gt;")
                .replaceAll('"', "&quot;")
                .replaceAll("'", "&#39;");
        }

        function applyTheme() {
            els.body.dataset.theme = state.theme;
            const isDark = state.theme === "dark";
            if (els.themeBtn) els.themeBtn.checked = isDark;
            if (els.floatingThemeBtn) els.floatingThemeBtn.checked = isDark;
            localStorage.setItem(STORAGE_KEYS.theme, state.theme);
        }

        function toggleThemeWithAnimation() {
            if (themeAnimating) return;
            themeAnimating = true;

            const nextTheme = state.theme === "light" ? "dark" : "light";
            const directionClass = nextTheme === "dark" ? "to-dark" : "to-light";
            els.modeTransition.classList.remove("to-dark", "to-light");
            els.modeTransition.classList.add("active", directionClass);

            setTimeout(() => {
                state.theme = nextTheme;
                applyTheme();
            }, 330);

            setTimeout(() => {
                els.modeTransition.classList.remove("active", "to-dark", "to-light");
                toast(`Switched to ${state.theme} mode`);
                themeAnimating = false;
            }, 1210);
        }

        function renderCategoryChips() {
            els.categoryChips.innerHTML = categories
                .map((cat) => `<button class="chip ${state.category === cat ? "active" : ""}" data-cat="${cat}">${cat.toUpperCase()}</button>`)
                .join("");

            els.categoryChips.querySelectorAll(".chip").forEach((chip) => {
                chip.addEventListener("click", () => {
                    state.category = chip.dataset.cat;
                    renderCategoryChips();
                    renderFeed();
                });
            });
        }

        function filteredPosts() {
            let posts = allPosts();

            if (state.mode === "saved") {
                posts = posts.filter((post) => state.saves[post.id]);
            }

            if (state.category !== "all") {
                posts = posts.filter((post) => post.category === state.category);
            }

            const q = state.postSearch.trim().toLowerCase();
            if (q) {
                posts = posts.filter((post) =>
                    post.author.toLowerCase().includes(q) || post.caption.toLowerCase().includes(q)
                );
            }

            if (state.postSort === "likes") {
                posts.sort((a, b) => {
                    const aLikes = a.likes + (state.likes[a.id] ? 1 : 0);
                    const bLikes = b.likes + (state.likes[b.id] ? 1 : 0);
                    return bLikes - aLikes;
                });
            }

            return posts;
        }

        async function loadFeedFromDb() {
            try {
                const response = await fetch("api/feed.php", { credentials: "same-origin" });
                if (!response.ok) return;
                const payload = await response.json();
                if (!payload?.ok || !Array.isArray(payload.posts)) return;

                state.dbPosts = payload.posts.map((row) => {
                    const username = String(row.username || "kita_user");
                    const comments = Array.isArray(row.comments)
                        ? row.comments.map((c) => ({
                            author: String(c.username || "user"),
                            text: String(c.comment || ""),
                        }))
                        : [];
                    return {
                        id: Number(row.post_id),
                        author: username,
                        avatar: username.charAt(0).toUpperCase(),
                        time: String(row.created_at || "now"),
                        image: "🖼️",
                        imageUrl: String(row.image || ""),
                        caption: String(row.content || ""),
                        category: "ict",
                        likes: 0,
                        comments,
                        fromDb: true,
                    };
                });
            } catch {
                // Keep fallback feed
            }
        }

        function renderFeed() {
            const posts = filteredPosts();
            if (!posts.length) {
                els.feed.innerHTML = '<div class="empty">No posts matched this filter.</div>';
                return;
            }

            els.feed.innerHTML = posts
                .map((post) => {
                    const liked = !!state.likes[post.id];
                    const saved = !!state.saves[post.id];
                    const likes = post.likes + (liked ? 1 : 0);
                    const comments = Array.isArray(post.comments) && post.comments.length
                        ? post.comments
                        : (state.comments[post.id] || []);
                    const commentsHtml = comments.length
                        ? comments
                            .slice(-3)
                            .map((comment) => `<div class="comment-item"><strong>${escapeHtml(comment.author)}</strong>${escapeHtml(comment.text)}</div>`)
                            .join("")
                        : '<div class="comment-empty">No comments yet. Be the first to comment.</div>';
                    const postVisual = post.imageUrl
                        ? `<img src="${escapeHtml(post.imageUrl)}" alt="Post image" loading="lazy" />`
                        : (post.imageData
                            ? `<img src="${post.imageData}" alt="Post image" loading="lazy" />`
                            : post.image);
                    return `
                        <article class="post" data-id="${post.id}" data-db="${post.fromDb ? "1" : "0"}">
                            <div class="post-head">
                                <div class="author">
                                    <div class="avatar">${post.avatar}</div>
                                    <div>
                                        <h4>${post.author}</h4>
                                        <div class="post-time">${formatWhen(post.time)}</div>
                                    </div>
                                </div>
                                <button class="action-btn" data-action="next" title="Next"><i class="fa-solid fa-arrow-right"></i></button>
                            </div>
                            <div class="post-image">${postVisual}</div>
                            <div class="post-body">
                                <p class="post-caption"><strong>${post.author}</strong>${post.caption}</p>
                                <div class="post-actions">
                                    <button class="action-btn ${liked ? "active like" : ""}" data-action="like">${liked ? '<i class="fa-solid fa-heart"></i>' : '<i class="fa-regular fa-heart"></i>'} ${liked ? "Liked" : "Like"}</button>
                                    <button class="action-btn ${saved ? "active" : ""}" data-action="save">${saved ? '<i class="fa-solid fa-bookmark"></i>' : '<i class="fa-regular fa-bookmark"></i>'} ${saved ? "Saved" : "Save"}</button>
                                </div>
                                <div class="meta-row">
                                    <span>${likes} likes</span>
                                    <span>${post.category.toUpperCase()}</span>
                                </div>
                                <div class="post-comments">
                                    <div class="comment-list">${commentsHtml}</div>
                                    <div class="comment-form">
                                        <input type="text" data-comment-input="${post.id}" maxlength="120" placeholder="Add a comment..." />
                                        <button class="action-btn" data-action="comment">Post</button>
                                    </div>
                                </div>
                            </div>
                        </article>
                    `;
                })
                .join("");

            els.feed.querySelectorAll(".post").forEach((postEl, idx) => {
                const id = Number(postEl.dataset.id);

                postEl.querySelector('[data-action="like"]').addEventListener("click", () => {
                    state.likes[id] = !state.likes[id];
                    saveJSON(STORAGE_KEYS.likes, state.likes);
                    renderFeed();
                });

                postEl.querySelector('[data-action="save"]').addEventListener("click", () => {
                    state.saves[id] = !state.saves[id];
                    saveJSON(STORAGE_KEYS.saves, state.saves);
                    renderFeed();
                });

                const commentInput = postEl.querySelector(`[data-comment-input="${id}"]`);
                const submitComment = async () => {
                    if (!commentInput) return;
                    const text = commentInput.value.trim();
                    if (!text) return;

                    const isDbPost = postEl.dataset.db === "1";
                    if (isDbPost) {
                        const formData = new FormData();
                        formData.append("post_id", String(id));
                        formData.append("comment", text);
                        try {
                            const response = await fetch("api/comment_create.php", {
                                method: "POST",
                                credentials: "same-origin",
                                body: formData,
                            });
                            const payload = await response.json();
                            if (!response.ok || !payload?.ok) {
                                throw new Error(payload?.error || "Could not post comment");
                            }
                            await loadFeedFromDb();
                            renderFeed();
                            toast("Comment posted");
                            return;
                        } catch {
                            toast("Could not post comment");
                            return;
                        }
                    }

                    const existing = state.comments[id] || [];
                    existing.push({ author: "You", text });
                    state.comments[id] = existing.slice(-40);
                    saveJSON(STORAGE_KEYS.comments, state.comments);
                    renderFeed();
                    toast("Comment posted");
                };

                postEl.querySelector('[data-action="comment"]').addEventListener("click", submitComment);
                commentInput?.addEventListener("keydown", (event) => {
                    if (event.key === "Enter") {
                        event.preventDefault();
                        submitComment();
                    }
                });

                postEl.querySelector('[data-action="next"]').addEventListener("click", () => {
                    const list = filteredPosts();
                    if (idx < list.length - 1) {
                        postEl.nextElementSibling?.scrollIntoView({ behavior: "smooth", block: "start" });
                    } else {
                        els.feed.firstElementChild?.scrollIntoView({ behavior: "smooth", block: "start" });
                    }
                });
            });
        }

        function filteredJobs() {
            return jobs.filter((job) => !state.dismissedJobs[job.id]);
        }

        function renderJobs() {
            const list = filteredJobs();
            if (!list.length) {
                els.jobsWrap.innerHTML = '<div class="empty" style="margin:10px;">No more picks right now.</div>';
                return;
            }

            els.jobsWrap.innerHTML = list
                .map((job) => {
                    return `
                        <article class="job-list-item">
                            <div class="job-logo">${job.logo}</div>
                            <div class="job-main">
                                <h5>${job.title}</h5>
                                <div class="meta">${job.company}</div>
                                <div class="sub">${job.sub}</div>
                                <div class="foot">${job.foot}</div>
                            </div>
                            <div class="job-actions">
                                <button class="job-message" type="button" data-company="${escapeHtml(job.company)}" data-employer-id="${Number(job.employerId || 0)}">Message</button>
                                <button class="job-dismiss" data-id="${job.id}" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        </article>
                    `;
                })
                .join("");

            els.jobsWrap.querySelectorAll(".job-dismiss").forEach((btn) => {
                btn.addEventListener("click", () => {
                    const id = Number(btn.dataset.id);
                    state.dismissedJobs[id] = true;
                    saveJSON(STORAGE_KEYS.dismissedJobs, state.dismissedJobs);
                    renderJobs();
                    toast("Job removed from picks");
                });
            });

            els.jobsWrap.querySelectorAll(".job-message").forEach((btn) => {
                btn.addEventListener("click", () => {
                    const company = btn.dataset.company;
                    const employerId = Number(btn.dataset.employerId || 0);
                    if (!company) return;
                    openChatWithCompany(company, employerId);
                });
            });
        }

        function showLocationStatus(message) {
            if (els.locationStatus) {
                els.locationStatus.textContent = message;
            }
        }

        function initLocationMap() {
            if (!els.locationMap || !els.locationStatus) return;
            if (!("geolocation" in navigator)) {
                showLocationStatus("Geolocation not supported in your browser.");
                return;
            }
            showLocationStatus("Locating you…");
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    const { latitude, longitude } = pos.coords;
                    if (!locationMap) {
                        locationMap = L.map(els.locationMap).setView([latitude, longitude], 13);
                        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                            attribution: '© OpenStreetMap contributors',
                        }).addTo(locationMap);
                    } else {
                        locationMap.setView([latitude, longitude], 13);
                    }
                    if (locationMarker) {
                        locationMarker.setLatLng([latitude, longitude]);
                    } else {
                        locationMarker = L.marker([latitude, longitude]).addTo(locationMap);
                    }
                    showLocationStatus(`Latitude ${latitude.toFixed(4)}, Longitude ${longitude.toFixed(4)}`);
                },
                (err) => {
                    console.warn("Geolocation error", err);
                    if (err.code === err.PERMISSION_DENIED) {
                        showLocationStatus("Location access denied. Allow location to see map.");
                    } else if (err.code === err.POSITION_UNAVAILABLE) {
                        showLocationStatus("Location unavailable.");
                    } else {
                        showLocationStatus("Unable to get location.");
                    }
                },
                { timeout: 12000, maximumAge: 60000 }
            );
        }

        function renderSkills() {
            const items = state.skills
                .map((skill) => `
                    <div class="skill-item" data-key="${skill.key}">
                        <div class="skill-row"><span>${skill.name}</span><strong>${skill.value}%</strong></div>
                        <div class="bar"><div class="fill" style="width:${skill.value}%"></div></div>
                        <button class="skill-cta" data-key="${skill.key}">Practice +5</button>
                    </div>
                `)
                .join("");

            els.skillsCard.innerHTML = `<h3 class="side-title"><i class="fa-solid fa-chart-line"></i> Your Skill Journey</h3>${items}`;

            els.skillsCard.querySelectorAll(".skill-cta").forEach((btn) => {
                btn.addEventListener("click", () => {
                    const key = btn.dataset.key;
                    const target = state.skills.find((s) => s.key === key);
                    if (!target) return;
                    target.value = Math.min(100, target.value + 5);
                    saveJSON(STORAGE_KEYS.skills, state.skills);
                    renderSkills();
                    toast(`${target.name} improved to ${target.value}%`);
                });
            });
        }

        function filteredConversations() {
            const q = (els.chatSearchInput?.value || "").trim().toLowerCase();
            if (!q) return state.conversations;
            return state.conversations.filter((c) => {
                const last = c.messages[c.messages.length - 1]?.text || "";
                return c.name.toLowerCase().includes(q) || last.toLowerCase().includes(q);
            });
        }

        function getActiveConversation() {
            let target = state.conversations.find((c) => c.id === state.activeChatId);
            if (!target && state.conversations.length) {
                state.activeChatId = state.conversations[0].id;
                target = state.conversations[0];
            }
            return target || null;
        }

        function syncChatComposerState() {
            const hasActiveChat = Boolean(getActiveConversation());
            if (els.chatMessageInput) {
                els.chatMessageInput.disabled = !hasActiveChat;
                els.chatMessageInput.placeholder = hasActiveChat ? "Write a message..." : "Add a friend to start messaging...";
            }
            if (els.sendChatBtn) {
                els.sendChatBtn.disabled = !hasActiveChat;
            }
            if (els.startAudioCallBtn) {
                els.startAudioCallBtn.disabled = !hasActiveChat;
            }
            if (els.startVideoCallBtn) {
                els.startVideoCallBtn.disabled = !hasActiveChat;
            }
        }

        function avatarLetters(name) {
            const parts = String(name || "").trim().split(" ").filter(Boolean);
            if (!parts.length) return "?";
            if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
            return `${parts[0].charAt(0)}${parts[1].charAt(0)}`.toUpperCase();
        }

        function avatarUrl(profile) {
            const fromProfile = String(profile?.avatar || profile?.avatarUrl || "").trim();
            if (fromProfile) return fromProfile;

            const byName = userDirectoryByName[String(profile?.name || "").toLowerCase()];
            if (byName?.avatar) return String(byName.avatar).trim();

            const byHandle = userDirectoryByHandle[String(profile?.handle || "").toLowerCase()];
            if (byHandle?.avatar) return String(byHandle.avatar).trim();

            return "";
        }

        function avatarMarkup(profile, name) {
            const src = avatarUrl(profile);
            if (src) {
                return `<img src="${escapeHtml(src)}" alt="${escapeHtml(name || "Avatar")}">`;
            }
            return escapeHtml(avatarLetters(name || profile?.name || ""));
        }

        function renderChatList() {
            if (!els.chatList) return;
            const items = filteredConversations();
            if (!items.length) {
                els.chatList.innerHTML = '<div class="empty" style="margin:8px;">No chats yet. Tap <strong>New chat</strong> to start messaging.</div>';
                return;
            }

            els.chatList.innerHTML = items
                .map((chat) => {
                    const last = chat.messages[chat.messages.length - 1] || { text: "No messages yet", time: "" };
                    return `
                        <button class="chat-item ${chat.id === state.activeChatId ? "active" : ""}" data-chat-id="${chat.id}">
                            <div class="dm-avatar">${chat.isGroup ? '<i class="fa-solid fa-users"></i>' : avatarMarkup(chat, chat.name)}</div>
                            <div class="chat-item-main">
                                <div class="chat-item-top">
                                    <span class="chat-item-name">${escapeHtml(chat.name)}</span>
                                    <span class="chat-item-time">${escapeHtml(last.time)}</span>
                                </div>
                                <div class="chat-item-preview">${escapeHtml(last.text)}</div>
                            </div>
                        </button>
                    `;
                })
                .join("");

            els.chatList.querySelectorAll(".chat-item").forEach((btn) => {
                btn.addEventListener("click", () => {
                    state.activeChatId = Number(btn.dataset.chatId);
                    openThreadView();
                    renderMessages();
                });
            });
        }

        function renderChatThread() {
            if (!els.chatThreadBody || !els.chatThreadTitle || !els.chatThreadSub) return;
            const chat = getActiveConversation();
            if (!chat) {
                if (els.chatThreadAvatar) els.chatThreadAvatar.textContent = "M";
                els.chatThreadTitle.textContent = "Messages";
                els.chatThreadSub.textContent = "Select a conversation";
                els.chatThreadBody.innerHTML = '<div class="empty">No conversation available. Add a friend to start.</div>';
                return;
            }

            if (els.chatThreadAvatar) els.chatThreadAvatar.innerHTML = avatarMarkup(chat, chat.name);
            els.chatThreadTitle.textContent = chat.name;
            els.chatThreadSub.textContent = chat.status;
            if (!chat.messages.length) {
                els.chatThreadBody.innerHTML = '<div class="empty">No messages yet. Say hello.</div>';
                return;
            }
            els.chatThreadBody.innerHTML = chat.messages
                .map((msg) => `
                    <div class="chat-bubble ${msg.from === "me" ? "me" : "them"}">
                        ${escapeHtml(msg.text)}
                        <span class="chat-bubble-time">${escapeHtml(msg.time)}</span>
                    </div>
                `)
                .join("");

            els.chatThreadBody.scrollTop = els.chatThreadBody.scrollHeight;
        }

        function renderChatProfile() {
            const chat = getActiveConversation();
            if (!els.chatProfileName) return;
            if (!chat) {
                if (els.chatProfileAvatar) els.chatProfileAvatar.textContent = "M";
                if (els.chatProfileName) els.chatProfileName.textContent = "Select a chat";
                if (els.chatProfileHandle) els.chatProfileHandle.textContent = "@kita_user";
                if (els.chatProfileStatus) els.chatProfileStatus.textContent = "No conversation selected";
                if (els.chatProfileBio) els.chatProfileBio.textContent = "Open a conversation to view profile details.";
                if (els.chatProfileTags) els.chatProfileTags.innerHTML = "";
                if (els.chatMediaGrid) els.chatMediaGrid.innerHTML = Array.from({ length: 6 }).map(() => "<div></div>").join("");
                return;
            }
            if (els.chatProfileAvatar) els.chatProfileAvatar.innerHTML = avatarMarkup(chat, chat.name);
            if (els.chatProfileName) els.chatProfileName.textContent = chat.name;
            if (els.chatProfileHandle) els.chatProfileHandle.textContent = chat.handle || "@kita_user";
            if (els.chatProfileStatus) els.chatProfileStatus.textContent = chat.status || "";
            if (els.chatProfileBio) els.chatProfileBio.textContent = chat.bio || "No bio provided.";
            if (els.chatProfileTags) {
                els.chatProfileTags.innerHTML = (chat.tags || []).map((tag) => `<span class="chip">${tag}</span>`).join("");
            }
            if (els.chatMediaGrid) {
                const count = Math.max(3, Math.min(9, (chat.media || []).length || 3));
                els.chatMediaGrid.innerHTML = Array.from({ length: count }).map(() => "<div></div>").join("");
            }
        }

        function renderMessages() {
            renderChatList();
            renderChatThread();
            renderChatProfile();
            syncChatComposerState();
        }

        function openThreadView() {
            els.messagesView?.classList.add("dm-thread-open");
        }

        function closeThreadView() {
            els.messagesView?.classList.remove("dm-thread-open");
        }

        async function sendChatMessage() {
            const input = els.chatMessageInput;
            if (!input) return;
            const text = input.value.trim();
            if (!text) return;

            const chat = getActiveConversation();
            if (!chat) {
                toast("Add a friend first");
                return;
            }

            const targetUserId = Number(chat.targetUserId || 0);
            const targetEmployerId = Number(chat.targetEmployerId || 0);
            if (targetUserId <= 0 && targetEmployerId <= 0) {
                toast("Select a valid chat recipient");
                return;
            }

            try {
                const payload = {
                    message: text,
                    thread_id: Number(chat.threadId || 0),
                    target_user_id: targetUserId > 0 ? targetUserId : undefined,
                    target_employer_id: targetEmployerId > 0 ? targetEmployerId : undefined
                };
                const res = await apiRequest("api/chat_send.php", payload);
                if (res?.thread_id) {
                    chat.threadId = Number(res.thread_id);
                    chat.id = Number(res.thread_id);
                }
                input.value = "";
                await syncSocialState();
                return;
            } catch (error) {
                // Fallback to local (offline) messaging when server API fails.
                const now = new Date();
                const time = `${String(now.getHours()).padStart(2, "0")}:${String(now.getMinutes()).padStart(2, "0")}`;
                chat.messages.push({ from: "me", text, time });
                saveJSON(STORAGE_KEYS.messages, state.conversations);
                renderMessages();
                toast("Message saved locally (offline)");
                console.warn("Chat send failed, saved locally", error);
                input.value = "";
                return;
            }
        }

        function callRoomSlug(value) {
            return String(value || "")
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, "-")
                .replace(/^-+|-+$/g, "") || "kita-user";
        }

        function launchChatCall(type) {
            const chat = getActiveConversation();
            if (!chat) {
                toast("Open a chat first");
                return;
            }

            const participants = [callRoomSlug(VIEWER_NAME), callRoomSlug(chat.name)].sort();
            const room = `kita-${participants.join("-")}`;
            const mode = type === "audio" ? "audio" : "video";
            const url = `call.php?room=${encodeURIComponent(room)}&mode=${encodeURIComponent(mode)}`;
            const opened = window.open(url, "_blank", "noopener,noreferrer");
            if (!opened) {
                toast("Popup blocked. Allow popups to start calls.");
                return;
            }
            toast(mode === "audio" ? `Starting KITA audio call with ${chat.name}` : `Starting KITA video call with ${chat.name}`);
        }

        function slugifyHandle(name) {
            const slug = String(name || "")
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, "_")
                .replace(/^_+|_+$/g, "");
            return `@${slug || "kita_friend"}`;
        }

        function nextConversationId() {
            return state.conversations.length ? Math.max(...state.conversations.map((chat) => Number(chat.id) || 0)) + 1 : 1;
        }

        function friendRequestKey(profile) {
            const handle = String(profile?.handle || "").trim().toLowerCase();
            if (handle) return handle;
            return String(profile?.name || "").trim().toLowerCase();
        }

        function normalizeHandle(value) {
            const clean = String(value || "").trim().toLowerCase();
            if (!clean) return "";
            if (clean.startsWith("@")) return clean;
            return `@${clean.replace(/[^a-z0-9_]+/g, "_").replace(/^_+|_+$/g, "")}`;
        }

        function globalRequestId(fromHandle, toHandle) {
            const from = normalizeHandle(fromHandle);
            const to = normalizeHandle(toHandle);
            if (!from || !to) return "";
            return `${from}->${to}`;
        }

        function saveGlobalFriendRequests() {
            saveJSON(STORAGE_KEYS.globalFriendRequests, state.globalFriendRequests);
        }

        function getPendingIncomingRequests() {
            if (Array.isArray(state.incomingRequests) && state.incomingRequests.length) {
                return state.incomingRequests.map((req) => ({
                    id: String(req.request_id || ""),
                    from: String(req.from_handle || ""),
                    fromName: String(req.from_name || "KITA User"),
                    to: normalizeHandle(VIEWER_HANDLE),
                    status: "pending",
                    updatedAt: String(req.created_at || "")
                }));
            }
            const mine = normalizeHandle(VIEWER_HANDLE);
            return Object.values(state.globalFriendRequests || {}).filter((req) =>
                normalizeHandle(req?.to) === mine && String(req?.status || "").toLowerCase() === "pending"
            );
        }

        function getOutgoingRequestForProfile(profile) {
            const targetId = Number(profile?.id || profile?.targetUserId || 0);
            if (targetId > 0 && Array.isArray(state.outgoingRequests)) {
                const req = state.outgoingRequests.find((row) => Number(row?.to_user_id || 0) === targetId && String(row?.status || "").toLowerCase() === "pending");
                if (req) {
                    return {
                        id: String(req.request_id || ""),
                        from: normalizeHandle(VIEWER_HANDLE),
                        to: normalizeHandle(profile?.handle || slugifyHandle(profile?.name || "")),
                        status: "pending"
                    };
                }
            }
            const target = normalizeHandle(profile?.handle || slugifyHandle(profile?.name || ""));
            const id = globalRequestId(VIEWER_HANDLE, target);
            const req = id ? state.globalFriendRequests[id] : null;
            if (!req) return null;
            return String(req.status || "").toLowerCase() === "pending" ? req : null;
        }

        function getIncomingRequestForProfile(profile) {
            const targetId = Number(profile?.id || profile?.targetUserId || 0);
            if (targetId > 0 && Array.isArray(state.incomingRequests)) {
                const req = state.incomingRequests.find((row) => Number(row?.from_user_id || 0) === targetId);
                if (req) {
                    return {
                        id: String(req.request_id || ""),
                        from: String(req.from_handle || ""),
                        fromName: String(req.from_name || profile?.name || "KITA User"),
                        to: normalizeHandle(VIEWER_HANDLE),
                        status: "pending"
                    };
                }
            }
            const from = normalizeHandle(profile?.handle || slugifyHandle(profile?.name || ""));
            const id = globalRequestId(from, VIEWER_HANDLE);
            const req = id ? state.globalFriendRequests[id] : null;
            if (!req) return null;
            return String(req.status || "").toLowerCase() === "pending" ? req : null;
        }

        function getFriendRequestStatus(profile) {
            const outgoing = getOutgoingRequestForProfile(profile);
            if (outgoing) return "sent";
            const incoming = getIncomingRequestForProfile(profile);
            if (incoming) return "incoming";
            const target = normalizeHandle(profile?.handle || slugifyHandle(profile?.name || ""));
            const outAny = state.globalFriendRequests[globalRequestId(VIEWER_HANDLE, target)];
            const inAny = state.globalFriendRequests[globalRequestId(target, VIEWER_HANDLE)];
            const settled = String(outAny?.status || inAny?.status || "").toLowerCase();
            if (settled === "accepted") return "accepted";
            const key = friendRequestKey(profile);
            return key ? (state.friendRequests[key] || "") : "";
        }

        function setFriendRequestStatus(profile, status) {
            const key = friendRequestKey(profile);
            if (!key || !profile) return;
            const targetHandle = normalizeHandle(profile.handle || slugifyHandle(profile.name || ""));
            const outgoingId = globalRequestId(VIEWER_HANDLE, targetHandle);
            if (!status) {
                delete state.friendRequests[key];
                if (outgoingId) delete state.globalFriendRequests[outgoingId];
            } else {
                state.friendRequests[key] = status;
                if (status === "sent" && outgoingId) {
                    state.globalFriendRequests[outgoingId] = {
                        id: outgoingId,
                        from: normalizeHandle(VIEWER_HANDLE),
                        fromName: VIEWER_NAME,
                        to: targetHandle,
                        toName: profile.name || "",
                        status: "pending",
                        updatedAt: new Date().toISOString()
                    };
                }
            }
            saveJSON(STORAGE_KEYS.friendRequests, state.friendRequests);
            saveGlobalFriendRequests();
        }

        function resolveProfileByHandleOrName(handle, name) {
            const normalized = normalizeHandle(handle);
            if (normalized) {
                const fromHandle = userDirectoryByHandle[normalized];
                if (fromHandle) return { ...fromHandle };
            }
            const cleanName = String(name || "").trim();
            if (cleanName) {
                const byName = userDirectoryByName[cleanName.toLowerCase()];
                if (byName) return { ...byName };
                return profileFromName(cleanName);
            }
            return {
                name: "KITA User",
                handle: normalized || "@kita_user",
                status: "New friend",
                bio: "No bio provided.",
                tags: [],
                media: []
            };
        }

        async function acceptIncomingRequest(profile) {
            const incoming = getIncomingRequestForProfile(profile);
            if (!incoming?.id) return;
            const incomingId = Number(incoming.id);
            if (!APP_CONTEXT.isEmployer && incomingId > 0) {
                await apiRequest("api/friend_request_action.php", { action: "accept", request_id: incomingId });
                await syncSocialState();
                const resolved = resolveProfileByHandleOrName(incoming.from, incoming.fromName || profile?.name || "");
                const matched = state.conversations.find((chat) =>
                    Number(chat.targetUserId || 0) === Number(profile?.id || 0) ||
                    String(chat.name || "").toLowerCase() === String(resolved.name || "").toLowerCase()
                );
                if (matched) {
                    state.activeChatId = matched.id;
                }
                toast(`You accepted ${resolved.name}`);
                return;
            }
            state.globalFriendRequests[incoming.id] = {
                ...incoming,
                status: "accepted",
                updatedAt: new Date().toISOString()
            };
            saveGlobalFriendRequests();
            const resolved = resolveProfileByHandleOrName(incoming.from, incoming.fromName || profile?.name || "");
            setFriendRequestStatus(resolved, "accepted");
            openConversationWithProfile({
                ...resolved,
                status: "Connected"
            });
            toast(`You accepted ${resolved.name}`);
            renderNotifications();
        }

        async function declineIncomingRequestById(requestId) {
            const numericId = Number(requestId);
            if (!APP_CONTEXT.isEmployer && numericId > 0) {
                await apiRequest("api/friend_request_action.php", { action: "decline", request_id: numericId });
                await syncSocialState();
                toast("Connection request declined");
                return;
            }
            const req = state.globalFriendRequests[requestId];
            if (!req) return;
            state.globalFriendRequests[requestId] = {
                ...req,
                status: "declined",
                updatedAt: new Date().toISOString()
            };
            saveGlobalFriendRequests();
            toast("Connection request declined");
            renderNotifications();
            renderFriendSuggestions();
        }

        function profileFromName(name) {
            const cleanName = String(name || "").trim();
            const existingProfile = combinedDirectory.find((profile) => profile.name.toLowerCase() === cleanName.toLowerCase());
            if (existingProfile) return existingProfile;
            return {
                name: cleanName,
                handle: slugifyHandle(cleanName),
                status: "New friend",
                bio: "No bio provided.",
                tags: [],
                media: []
            };
        }

        function openConversationWithProfile(profile) {
            const targetName = String(profile?.name || "").trim();
            if (!targetName) return;
            const isCompany = Boolean(profile?.isCompany);
            const targetEmployerId = isCompany
                ? Number(profile?.targetEmployerId || profile?.id || 0)
                : Number(profile?.targetEmployerId || 0);
            const targetUserId = isCompany
                ? 0
                : Number(profile?.targetUserId || profile?.id || 0);
            const existing = state.conversations.find((chat) => {
                const chatEmployerId = Number(chat.targetEmployerId || 0);
                const chatUserId = Number(chat.targetUserId || 0);
                if (targetEmployerId && chatEmployerId === targetEmployerId) return true;
                if (targetUserId && chatUserId === targetUserId) return true;
                return chat.name.toLowerCase() === targetName.toLowerCase();
            });
            if (existing) {
                if (targetUserId && !existing.targetUserId) {
                    existing.targetUserId = targetUserId;
                }
                if (targetEmployerId && !existing.targetEmployerId) {
                    existing.targetEmployerId = targetEmployerId;
                }
                if (isCompany) {
                    existing.isCompany = true;
                }
                state.activeChatId = existing.id;
                closeFriendComposeModal();
                openThreadView();
                renderMessages();
                toast(`${existing.name} chat opened`);
                return;
            }

            const newChat = {
                id: nextConversationId(),
                threadId: null,
                targetUserId: targetUserId || null,
                targetEmployerId: targetEmployerId || null,
                name: targetName,
                handle: profile.handle || slugifyHandle(targetName),
                status: profile.status || (isCompany ? "Company" : "New friend"),
                bio: profile.bio || (isCompany ? "Chat with this company." : "No bio provided."),
                tags: Array.isArray(profile.tags) ? profile.tags : [],
                media: Array.isArray(profile.media) ? profile.media : [],
                avatar: avatarUrl(profile),
                isGroup: Boolean(profile.isGroup),
                isCompany,
                messages: []
            };
            state.conversations.unshift(newChat);
            state.activeChatId = newChat.id;
            saveJSON(STORAGE_KEYS.messages, state.conversations);
            closeFriendComposeModal();
            openThreadView();
            renderMessages();
            toast(`${newChat.name} added`);
        }

        function openChatWithCompany(companyName, targetEmployerId) {
            if (!companyName) return;
            openConversationWithProfile({
                targetEmployerId: Number(targetEmployerId || 0),
                name: companyName,
                handle: slugifyHandle(companyName),
                status: "Company",
                bio: "Chat with this company.",
                tags: [],
                media: [],
                isGroup: false,
                isCompany: true
            });
            state.mode = "messages";
            setActiveModeButton();
            renderModeViews();
        }

        function createGroupChatFromInput() {
            const raw = (els.friendSearchInput?.value || "").trim();
            if (!raw) return false;
            const parts = raw.split(",").map((p) => p.trim()).filter(Boolean);
            if (parts.length < 2) return false;
            const groupName = parts[0];
            const members = parts.slice(1);
            openConversationWithProfile({
                name: groupName,
                handle: slugifyHandle(groupName),
                status: "Group chat",
                bio: `Group with ${members.join(", ")}`,
                tags: members,
                media: [],
                isGroup: true
            });
            return true;
        }

        async function sendFriendRequest(profile) {
            if (!profile || !profile.name) return;
            const targetId = Number(profile.id || profile.targetUserId || 0);
            if (!APP_CONTEXT.isEmployer && targetId > 0) {
                await apiRequest("api/friend_request_action.php", { action: "send", target_user_id: targetId });
                await syncSocialState();
                toast(`Friend request sent to ${profile.name}`);
                return;
            }
            setFriendRequestStatus(profile, "sent");
            renderFriendSuggestions();
            renderNotifications();
            toast(`Friend request sent to ${profile.name}`);
        }

        async function cancelFriendRequest(profile) {
            if (!profile || !profile.name) return;
            const targetId = Number(profile.id || profile.targetUserId || 0);
            if (!APP_CONTEXT.isEmployer && targetId > 0) {
                await apiRequest("api/friend_request_action.php", { action: "cancel", target_user_id: targetId });
                await syncSocialState();
                toast(`Friend request cancelled for ${profile.name}`);
                return;
            }
            setFriendRequestStatus(profile, "");
            renderFriendSuggestions();
            renderNotifications();
            toast(`Friend request cancelled for ${profile.name}`);
        }

        function friendSuggestions() {
            const q = (els.friendSearchInput?.value || "").trim().toLowerCase();
            const existingByName = new Set(state.conversations.map((chat) => chat.name.toLowerCase()));
            const merged = [];

            state.conversations.forEach((chat) => {
                merged.push({
                    name: chat.name,
                    handle: chat.handle || slugifyHandle(chat.name),
                    status: chat.status || "Message",
                    bio: chat.bio || "",
                    tags: chat.tags || [],
                    media: chat.media || [],
                    avatar: avatarUrl(chat),
                    existing: true,
                    targetUserId: chat.targetUserId || null,
                    targetEmployerId: chat.targetEmployerId || null,
                    isCompany: Boolean(chat.isCompany)
                });
            });

            combinedDirectory.forEach((profile) => {
                if (existingByName.has(profile.name.toLowerCase())) return;
                merged.push({ ...profile, existing: false });
            });

            if (!q) return merged;
            return merged.filter((profile) => {
                const name = String(profile.name || "").toLowerCase();
                const handle = String(profile.handle || "").toLowerCase();
                return name.includes(q) || handle.includes(q);
            });
        }

        function renderFriendSuggestions() {
            if (!els.friendSuggestList) return;
            const list = friendSuggestions();
            if (!list.length) {
                const q = (els.friendSearchInput?.value || "").trim();
                if (!q) {
                    els.friendSuggestList.innerHTML = '<div class="empty" style="margin:8px;">No people or companies found yet.</div>';
                } else {
                    els.friendSuggestList.innerHTML = '<div class="empty" style="margin:8px;">No matching people or companies.</div>';
                }
                return;
            }

            els.friendSuggestList.innerHTML = list.map((profile, index) => `
                <div class="dm-suggest-item">
                    <div class="dm-avatar">${avatarMarkup(profile, profile.name)}</div>
                    <div class="dm-suggest-meta">
                        <div class="dm-suggest-name">${escapeHtml(profile.name)}</div>
                        <div class="dm-suggest-handle">${escapeHtml(profile.handle || "")}</div>
                    </div>
                    ${(() => {
                        const requestStatus = getFriendRequestStatus(profile);
                        if (profile.isCompany) {
                            return `<button class="dm-suggest-action" data-profile-index="${index}" data-action="message">Message</button>`;
                        }
                        if (profile.existing) {
                            return `<button class="dm-suggest-action" data-profile-index="${index}" data-action="message">Message</button>`;
                        }
                        if (requestStatus === "accepted") {
                            return `<button class="dm-suggest-action" data-profile-index="${index}" data-action="message">Message</button>`;
                        }
                        if (requestStatus === "incoming") {
                            return `<button class="dm-suggest-action" data-profile-index="${index}" data-action="accept-request">Accept</button>`;
                        }
                        if (requestStatus === "sent") {
                            return `<button class="dm-suggest-action requested" data-profile-index="${index}" data-action="requested">Requested</button>`;
                        }
                        return `<button class="dm-suggest-action" data-profile-index="${index}" data-action="request">Send Request</button>`;
                    })()}
                </div>
            `).join("");

            els.friendSuggestList.querySelectorAll("[data-profile-index]").forEach((btn) => {
                btn.addEventListener("click", () => {
                    const index = Number(btn.getAttribute("data-profile-index"));
                    const action = String(btn.getAttribute("data-action") || "");
                    const profile = list[index];
                    if (!profile) return;
                    if (action === "message") {
                        openConversationWithProfile(profile);
                        return;
                    }
                    if (action === "request") {
                        sendFriendRequest(profile).catch(() => toast("Could not send request"));
                        return;
                    }
                    if (action === "accept-request") {
                        acceptIncomingRequest(profile).catch(() => toast("Could not accept request"));
                        return;
                    }
                    if (action === "requested") {
                        cancelFriendRequest(profile).catch(() => toast("Could not cancel request"));
                    }
                });
            });
        }

        function unseenAcceptedApplications() {
            return (ACCEPTED_APPLICATIONS || []).filter((app) => {
                const id = String(app?.application_id || "");
                if (!id) return false;
                return !state.seenApplicationNotifications[id];
            });
        }

        async function markAllNotificationsRead() {
            if (!APP_CONTEXT.isEmployer) {
                try {
                    await apiRequest("api/notifications_read.php", { action: "all" });
                } catch {
                    toast("Could not update notifications");
                    return;
                }
            }
            (ACCEPTED_APPLICATIONS || []).forEach((app) => {
                const id = String(app?.application_id || "");
                if (id) state.seenApplicationNotifications[id] = true;
            });
            saveJSON(STORAGE_KEYS.seenApplicationNotifications, state.seenApplicationNotifications);
            state.serverNotifications = (state.serverNotifications || []).map((item) => ({ ...item, is_read: true }));
            renderNotifications();
            toast("Notifications marked as read");
        }

        function renderNotificationBadge() {
            if (!els.notifBadge) return;
            const incomingCount = getPendingIncomingRequests().length;
            const serverUnread = (state.serverNotifications || []).filter((item) => !item?.is_read && String(item?.type || "") !== "connection_request").length;
            const acceptedCount = unseenAcceptedApplications().length;
            const total = incomingCount + serverUnread + acceptedCount;
            els.notifBadge.textContent = String(total);
            els.notifBadge.classList.toggle("is-hidden", total <= 0);
        }

        async function markNotificationRead(notificationId) {
            const id = Number(notificationId);
            if (!id) return;
            if (!APP_CONTEXT.isEmployer) {
                await apiRequest("api/notifications_read.php", { action: "one", notification_id: id });
            }
            state.serverNotifications = (state.serverNotifications || []).map((n) =>
                Number(n?.id || 0) === id ? { ...n, is_read: true } : n
            );
        }

        function renderNotifications() {
            renderNotificationBadge();
            if (!els.notifList) return;

            const incoming = getPendingIncomingRequests();
            const serverItems = (state.serverNotifications || []).filter((item) => String(item?.type || "") !== "connection_request");
            const acceptedApps = ACCEPTED_APPLICATIONS || [];
            const hasAny = incoming.length > 0 || serverItems.length > 0 || acceptedApps.length > 0;

            if (!hasAny) {
                els.notifList.innerHTML = '<div class="empty" style="margin:0;">No notifications yet.</div>';
                return;
            }

            const requestItems = incoming.map((req) => {
                const requestId = escapeHtml(String(req.id || ""));
                const fromName = escapeHtml(String(req.fromName || "KITA User"));
                const fromHandle = escapeHtml(String(req.from || ""));
                return `
                    <article class="notif-item">
                        <div class="notif-item-head">
                            <div class="notif-item-title">Connection request</div>
                            <div class="notif-item-time">Pending</div>
                        </div>
                        <div class="notif-item-body"><strong>${fromName}</strong> (${fromHandle || "@kita_user"}) wants to connect with you.</div>
                        <div class="notif-actions">
                            <button class="notif-btn primary" data-action="accept-connection" data-request-id="${requestId}">Accept</button>
                            <button class="notif-btn" data-action="decline-connection" data-request-id="${requestId}">Decline</button>
                        </div>
                    </article>
                `;
            });

            const databaseItems = serverItems.map((item) => {
                const notifId = Number(item?.id || 0);
                const type = String(item?.type || "");
                const title = escapeHtml(String(item?.title || "Notification"));
                const body = escapeHtml(String(item?.body || "")).replace(/\n/g, "<br>");
                const unreadMark = item?.is_read ? "" : "• New";
                const isAppAccepted = type === "application_accepted";
                return `
                    <article class="notif-item">
                        <div class="notif-item-head">
                            <div class="notif-item-title">${title} ${unreadMark}</div>
                            <div class="notif-item-time">${escapeHtml(formatWhen(String(item?.created_at || "")))}</div>
                        </div>
                        <div class="notif-item-body">${body}</div>
                        <div class="notif-actions">
                            ${isAppAccepted
                                ? `<button class="notif-btn primary" data-action="view-jobs" data-notification-id="${notifId}">View jobs</button>`
                                : `<button class="notif-btn" data-action="mark-read" data-notification-id="${notifId}">Mark read</button>`}
                        </div>
                    </article>
                `;
            });

            const localApplicationFallbackItems = acceptedApps.map((app) => {
                const appId = String(app?.application_id || "");
                const unseen = !state.seenApplicationNotifications[appId];
                const title = escapeHtml(String(app?.job_title || "Job opening"));
                const company = escapeHtml(String(app?.company || "Employer"));
                return `
                    <article class="notif-item">
                        <div class="notif-item-head">
                            <div class="notif-item-title">Application accepted ${unseen ? "• New" : ""}</div>
                            <div class="notif-item-time">${escapeHtml(formatWhen(String(app?.applied_at || "")))}</div>
                        </div>
                        <div class="notif-item-body">Your application for <strong>${title}</strong> at <strong>${company}</strong> was accepted.</div>
                        <div class="notif-actions">
                            <button class="notif-btn primary" data-action="view-jobs" data-app-id="${escapeHtml(appId)}">View jobs</button>
                        </div>
                    </article>
                `;
            });

            els.notifList.innerHTML = [...requestItems, ...databaseItems, ...localApplicationFallbackItems].join("");

            els.notifList.querySelectorAll("[data-action='accept-connection']").forEach((btn) => {
                btn.addEventListener("click", () => {
                    const reqId = String(btn.getAttribute("data-request-id") || "");
                    const req = state.globalFriendRequests[reqId];
                    const serverReq = (state.incomingRequests || []).find((r) => String(r.request_id) === reqId);
                    const profile = serverReq
                        ? resolveProfileByHandleOrName(serverReq.from_handle, serverReq.from_name || "")
                        : (req ? resolveProfileByHandleOrName(req.from, req.fromName || "") : null);
                    if (!profile) return;
                    acceptIncomingRequest(profile)
                        .then(() => renderFriendSuggestions())
                        .catch(() => toast("Could not accept request"));
                });
            });

            els.notifList.querySelectorAll("[data-action='decline-connection']").forEach((btn) => {
                btn.addEventListener("click", () => {
                    const reqId = String(btn.getAttribute("data-request-id") || "");
                    declineIncomingRequestById(reqId).catch(() => toast("Could not decline request"));
                });
            });

            els.notifList.querySelectorAll("[data-action='view-jobs']").forEach((btn) => {
                btn.addEventListener("click", () => {
                    const appId = String(btn.getAttribute("data-app-id") || "");
                    const notificationId = Number(btn.getAttribute("data-notification-id") || 0);
                    if (appId) {
                        state.seenApplicationNotifications[appId] = true;
                        saveJSON(STORAGE_KEYS.seenApplicationNotifications, state.seenApplicationNotifications);
                    }
                    const done = notificationId > 0 ? markNotificationRead(notificationId).catch(() => {}) : Promise.resolve();
                    done.finally(() => {
                        renderNotifications();
                        window.location.href = "jobs_all.php";
                    });
                });
            });

            els.notifList.querySelectorAll("[data-action='mark-read']").forEach((btn) => {
                btn.addEventListener("click", () => {
                    const notificationId = Number(btn.getAttribute("data-notification-id") || 0);
                    markNotificationRead(notificationId)
                        .then(() => renderNotifications())
                        .catch(() => toast("Could not mark notification"));
                });
            });
        }

        function toggleNotificationsPanel() {
            if (!els.notifPanel) return;
            state.notificationsOpen = !state.notificationsOpen;
            els.notifPanel.classList.toggle("is-hidden", !state.notificationsOpen);
            if (state.notificationsOpen) renderNotifications();
        }

        function openFriendComposeModal() {
            if (!els.friendComposeModal) return;
            els.friendComposeModal.classList.add("open");
            els.friendComposeModal.setAttribute("aria-hidden", "false");
            if (els.friendSearchInput) els.friendSearchInput.value = "";
            renderFriendSuggestions();
            els.friendSearchInput?.focus();
        }

        function closeFriendComposeModal() {
            if (!els.friendComposeModal) return;
            els.friendComposeModal.classList.remove("open");
            els.friendComposeModal.setAttribute("aria-hidden", "true");
        }

        function addFriend() {
            openFriendComposeModal();
        }

        function renderModeViews() {
            const isMessages = state.mode === "messages";
            els.toolbar?.classList.toggle("is-hidden", isMessages);
            els.composer?.classList.toggle("is-hidden", isMessages);
            els.feed?.classList.toggle("is-hidden", isMessages);
            els.messagesView?.classList.toggle("is-hidden", !isMessages);
            els.rightCol?.classList.toggle("is-hidden", isMessages);
            els.content?.classList.toggle("messages-open", isMessages);
            if (isMessages) {
                // Keep the thread view open once in messages mode
                if (state.activeChatId !== null) {
                    openThreadView();
                }
                renderMessages();
            }
        }

        function openCreatePostModal() {
            els.createModal.classList.add("open");
            els.createModal.setAttribute("aria-hidden", "false");
            els.createStepUpload.classList.add("active");
            els.createStepCaption.classList.remove("active");
            createPostDraft = { imageData: "" };
            els.createPhotoInput.value = "";
            els.createPreviewImage.removeAttribute("src");
            els.createCaptionInput.value = "";
            els.createAuthorInput.value = "";
            els.createCategoryInput.value = "ict";
            els.publishPostBtn.classList.remove('posting');
            els.publishPostBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Publish';
        }

        function closeCreatePostModal() {
            els.createModal.classList.remove("open");
            els.createModal.setAttribute("aria-hidden", "true");
        }

        function fileToDataUrl(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(String(reader.result || ""));
                reader.onerror = reject;
                reader.readAsDataURL(file);
            });
        }

        async function handlePhotoSelected() {
            const file = els.createPhotoInput.files?.[0];
            if (!file) return;
            if (!file.type.startsWith("image/")) {
                toast("Please upload an image file");
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                toast("Image is too large (max 5MB)");
                return;
            }

            createPostDraft.imageData = await fileToDataUrl(file);
            els.createPreviewImage.src = createPostDraft.imageData;
        }

        function goToCaptionStep() {
            els.createStepUpload.classList.remove("active");
            els.createStepCaption.classList.add("active");
            els.createCaptionInput.focus();
        }

        async function publishCreatedPost() {
            console.log("Attempting to publish post...");
            const btn = els.publishPostBtn;
            if (btn.classList.contains('posting')) return;

            const caption = els.createCaptionInput.value.trim();
            if (!caption) {
                toast("Write a caption");
                return;
            }
            const uploadFile = els.createPhotoInput.files?.[0];

            btn.classList.add('posting');
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Publishing...';

            const formData = new FormData();
            formData.append("content", caption);
            if (uploadFile) {
                formData.append("image", uploadFile);
            }
            formData.append("strand", els.createStrandInput?.value || '');
            formData.append("location", els.createLocationInput?.value || '');

            try {
                const response = await fetch("api/post_create.php", { method: "POST", credentials: "same-origin", body: formData });
                const text = await response.text();
                console.log("Server response raw:", text);
                let payload;
                
                try {
                    payload = JSON.parse(text);
                } catch (e) {
                    console.error("Server response not JSON:", text);
                    throw new Error("Server error. Check console for details.");
                }

                if (!response.ok || !payload?.ok) {
                    throw new Error(payload?.error || `Request failed with status ${response.status}`);
                }
                await loadFeedFromDb();
                closeCreatePostModal();
                renderFeed();
                toast("Post published");
            } catch (error) {
                console.error('Publish post error:', error);
                toast(error.message || "Could not publish post");
            } finally {
                btn.classList.remove('posting');
                btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Publish';
            }
        }

        function setActiveModeButton() {
            els.menuButtons.forEach((btn) => {
                btn.classList.toggle("active", btn.dataset.mode === state.mode);
            });
        }

        function bindEvents() {
            els.postSearch.addEventListener("input", () => {
                state.postSearch = els.postSearch.value;
                renderFeed();
            });

            els.sortPosts.addEventListener("change", () => {
                state.postSort = els.sortPosts.value;
                renderFeed();
            });

            els.openCreatePostBtn.addEventListener("click", openCreatePostModal);
            els.quickPostBtn?.addEventListener("click", openCreatePostModal);
            els.notifToggleBtn?.addEventListener("click", toggleNotificationsPanel);
            els.markNotifsReadBtn?.addEventListener("click", () => {
                markAllNotificationsRead().catch(() => toast("Could not mark notifications"));
            });
            els.closeCreatePostBtn.addEventListener("click", closeCreatePostModal);
            els.createModal.addEventListener("click", (event) => {
                if (event.target === els.createModal) closeCreatePostModal();
            });
            document.addEventListener("click", (event) => {
                if (!els.notifPanel || !els.notifToggleBtn) return;
                if (!state.notificationsOpen) return;
                const target = event.target;
                if (!(target instanceof Element)) return;
                if (els.notifPanel.contains(target) || els.notifToggleBtn.contains(target)) return;
                state.notificationsOpen = false;
                els.notifPanel.classList.add("is-hidden");
            });
            els.friendComposeModal?.addEventListener("click", (event) => {
                if (event.target === els.friendComposeModal) closeFriendComposeModal();
            });
            document.addEventListener("keydown", (event) => {
                if (event.key === "Escape") {
                    closeCreatePostModal();
                    closeFriendComposeModal();
                    state.notificationsOpen = false;
                    els.notifPanel?.classList.add("is-hidden");
                }
            });
            els.createPhotoInput.addEventListener("change", () => {
                handlePhotoSelected().catch(() => toast("Could not read image"));
            });
            els.goCaptionStepBtn.addEventListener("click", goToCaptionStep);
            els.skipPhotoBtn?.addEventListener("click", goToCaptionStep);
            els.backToUploadBtn.addEventListener("click", () => {
                els.createStepCaption.classList.remove("active");
                els.createStepUpload.classList.add("active");
            });
            els.publishPostBtn.addEventListener("click", publishCreatedPost);
            els.createCaptionInput.addEventListener("keydown", (event) => {
                if ((event.ctrlKey || event.metaKey) && event.key === "Enter") {
                    event.preventDefault();
                    publishCreatedPost();
                }
            });

            els.showAllJobsBtn?.addEventListener("click", () => {
                window.location.href = APP_CONTEXT.isEmployer ? "employer.php" : "jobs_all.php";
            });

            els.chatSearchInput?.addEventListener("input", renderChatList);
            els.closeThreadBtn?.addEventListener("click", closeThreadView);
            els.addFriendBtn?.addEventListener("click", addFriend);
            els.closeFriendComposeBtn?.addEventListener("click", closeFriendComposeModal);
            els.friendSearchInput?.addEventListener("input", renderFriendSuggestions);
            els.friendSearchInput?.addEventListener("keydown", (event) => {
                if (event.key !== "Enter") return;
                event.preventDefault();
                if (createGroupChatFromInput()) return;
                const firstAction =
                    els.friendSuggestList?.querySelector('.dm-suggest-action[data-action="message"]') ||
                    els.friendSuggestList?.querySelector('.dm-suggest-action[data-action="request"]');
                firstAction?.click();
            });
            els.sendChatBtn?.addEventListener("click", () => {
                sendChatMessage().catch(() => toast("Could not send message"));
            });
            els.startAudioCallBtn?.addEventListener("click", () => launchChatCall("audio"));
            els.startVideoCallBtn?.addEventListener("click", () => launchChatCall("video"));
            els.chatMessageInput?.addEventListener("keydown", (event) => {
                if (event.key === "Enter") {
                    event.preventDefault();
                    sendChatMessage().catch(() => toast("Could not send message"));
                }
            });

            if (els.themeBtn) els.themeBtn.addEventListener("change", toggleThemeWithAnimation);
            if (els.floatingThemeBtn) els.floatingThemeBtn.addEventListener("change", toggleThemeWithAnimation);

            els.menuButtons.forEach((btn) => {
                btn.addEventListener("click", () => {
                    state.mode = btn.dataset.mode;
                    state.notificationsOpen = false;
                    els.notifPanel?.classList.add("is-hidden");
                    setActiveModeButton();
                    renderModeViews();
                    if (state.mode === "saved") {
                        toast("Showing saved posts");
                    } else if (state.mode === "messages") {
                        toast("Messages opened");
                    } else if (state.mode === "profile") {
                        toast("Profile view coming soon");
                    } else {
                        toast(`${btn.textContent.trim()} selected`);
                    }
                    if (state.mode !== "messages") renderFeed();
                });
            });

            window.addEventListener("storage", (event) => {
                if (event.key === STORAGE_KEYS.globalFriendRequests) {
                    state.globalFriendRequests = loadJSON(STORAGE_KEYS.globalFriendRequests, {});
                    renderNotifications();
                    renderFriendSuggestions();
                }
            });
        }

        function renderMoneyRain() {
            const count = 22;
            els.moneyRain.innerHTML = "";

            for (let i = 0; i < count; i += 1) {
                const note = document.createElement("div");
                const fallDuration = 8 + Math.random() * 8;
                const swayDuration = 2 + Math.random() * 2.5;
                const delay = Math.random() * 10;
                const size = 30 + Math.random() * 24;
                const left = Math.random() * 100;

                note.className = "money-note";
                note.style.left = `${left}%`;
                note.style.width = `${size}px`;
                note.style.animationDuration = `${fallDuration}s, ${swayDuration}s`;
                note.style.animationDelay = `-${delay}s, -${delay / 2}s`;
                note.style.opacity = `${0.14 + Math.random() * 0.18}`;
                note.style.transform = `rotate(${Math.random() * 360}deg)`;
                els.moneyRain.appendChild(note);
            }
        }

        function playLoginEntryAnimationIfNeeded() {
            const params = new URLSearchParams(window.location.search);
            if (params.get("auth") !== "1") return;

            els.loginEntry.classList.add("active");
            setTimeout(() => {
                els.loginEntry.classList.remove("active");
            }, 1900);

            params.delete("auth");
            const cleanQuery = params.toString();
            const cleanUrl = `${window.location.pathname}${cleanQuery ? `?${cleanQuery}` : ""}${window.location.hash}`;
            window.history.replaceState({}, "", cleanUrl);
        }

        async function init() {
            applyTheme();
            renderMoneyRain();
            playLoginEntryAnimationIfNeeded();
            bindEvents();
            renderCategoryChips();
            await loadFeedFromDb();
            renderFeed();
            if (!APP_CONTEXT.isEmployer) {
                renderJobs();
                renderSkills();
            }
            await syncSocialState();
            setInterval(() => {
                syncSocialState().catch(() => {});
            }, 12000);
            renderNotifications();
            renderModeViews();
            initLocationMap();
        }

        window.addEventListener("DOMContentLoaded", init);
    </script>
</body>
</html>
