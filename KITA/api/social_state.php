<?php
declare(strict_types=1);

require_once __DIR__ . '/social_common.php';

ensure_social_tables();
$auth = social_require_user();
$user = $auth['user'];
$viewerId = (int) $auth['user_id'];
$viewerType = (string) ($auth['type'] ?? 'user');
$viewerName = trim((string) (($user['username'] ?? '') ?: ($user['full_name'] ?? '') ?: ($user['company_name'] ?? '') ?: 'KITA User'));
if ($viewerName === '') {
    $viewerName = $viewerType === 'employer' ? 'Employer' : 'KITA User';
}
$viewerCombined = $viewerType === 'employer' ? -$viewerId : $viewerId;

$conn = db();

// Sync accepted applications into notifications table once per application/user.
if ($viewerType === 'user' && db_table_exists('applications') && db_table_exists('jobs')) {
    $appSql = "SELECT a.application_id, j.job_title, COALESCE(e.company_name, u.full_name, 'Employer') AS company
               FROM applications a
               INNER JOIN jobs j ON j.job_id = a.job_id
               LEFT JOIN employers e ON e.id = j.employer_id
               LEFT JOIN users u ON u.user_id = j.employer_id
               WHERE a.student_id = ? AND LOWER(a.status) = 'accepted'
               ORDER BY a.applied_at DESC
               LIMIT 100";
    $appStmt = $conn->prepare($appSql);
    if ($appStmt) {
        $appStmt->bind_param('i', $viewerId);
        $appStmt->execute();
        $appRes = $appStmt->get_result();
        $insNotif = $conn->prepare(
            "INSERT IGNORE INTO notifications (user_id, type, title, body, data_json, external_key, is_read)
             VALUES (?, 'application_accepted', ?, ?, ?, ?, 0)"
        );
        while ($appRes && ($row = $appRes->fetch_assoc())) {
            $appId = (int) ($row['application_id'] ?? 0);
            if ($appId <= 0 || !$insNotif) continue;
            $title = 'Application accepted';
            $job = trim((string) ($row['job_title'] ?? 'Job opening'));
            $company = trim((string) ($row['company'] ?? 'Employer'));
            $body = "Your application for {$job} at {$company} was accepted.";
            $data = json_encode(['application_id' => $appId, 'job_title' => $job, 'company' => $company], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $externalKey = 'app_accept_' . $appId;
            $insNotif->bind_param('issss', $viewerId, $title, $body, $data, $externalKey);
            $insNotif->execute();
        }
        $insNotif?->close();
        $appStmt->close();
    }
}

// Sync scheduled appointments into notifications table (backfills existing + new ones).
if ($viewerType === 'user' && db_table_exists('applications') && db_table_exists('jobs')) {
    $schedSql = "SELECT a.application_id, a.interview_type, a.interview_datetime, a.interview_notes,
                        j.job_title, COALESCE(e.company_name, 'Employer') AS company
                 FROM applications a
                 INNER JOIN jobs j ON j.job_id = a.job_id
                 LEFT JOIN employers e ON e.id = j.employer_id
                 WHERE a.student_id = ? AND a.interview_datetime IS NOT NULL AND a.interview_datetime != ''
                 ORDER BY a.applied_at DESC LIMIT 100";
    $schedStmt = $conn->prepare($schedSql);
    if ($schedStmt) {
        $schedStmt->bind_param('i', $viewerId);
        $schedStmt->execute();
        $schedRes = $schedStmt->get_result();
        $insAppt = $conn->prepare(
            "INSERT INTO notifications (user_id, type, title, body, data_json, external_key, is_read)
             VALUES (?, 'appointment_scheduled', ?, ?, ?, ?, 0)
             ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body), data_json=VALUES(data_json)"
        );
        while ($schedRes && ($row = $schedRes->fetch_assoc())) {
            $aId = (int) ($row['application_id'] ?? 0);
            if ($aId <= 0 || !$insAppt) continue;
            $typeLabel   = ($row['interview_type'] ?? '') === 'interview' ? 'Interview' : 'Phone / Online Call';
            $nTitle      = '📅 Appointment Scheduled';
            $nBody       = "Your application for \"{$row['job_title']}\" at {$row['company']} has been scheduled.\nType: {$typeLabel}\nDate/Time: {$row['interview_datetime']}" . (!empty($row['interview_notes']) ? "\nNotes: {$row['interview_notes']}" : '');
            $nData       = json_encode(['application_id' => $aId, 'interview_datetime' => $row['interview_datetime'], 'interview_type' => $row['interview_type']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $extKey      = "appt_{$aId}";
            $insAppt->bind_param('issss', $viewerId, $nTitle, $nBody, $nData, $extKey);
            $insAppt->execute();
        }
        $insAppt?->close();
        $schedStmt->close();
    }
}

// Friends (users only)
$friends = [];
if ($viewerType === 'user') {
    $friendSql = "SELECT
                    CASE WHEN f.user_one_id = ? THEN u2.user_id ELSE u1.user_id END AS friend_id,
                    CASE WHEN f.user_one_id = ? THEN COALESCE(u2.username, u2.full_name, 'KITA User') ELSE COALESCE(u1.username, u1.full_name, 'KITA User') END AS friend_name,
                    CASE WHEN f.user_one_id = ? THEN COALESCE(u2.strand, '') ELSE COALESCE(u1.strand, '') END AS strand,
                    CASE WHEN f.user_one_id = ? THEN COALESCE(u2.location, '') ELSE COALESCE(u1.location, '') END AS location,
                    CASE WHEN f.user_one_id = ? THEN COALESCE(u2.bio, '') ELSE COALESCE(u1.bio, '') END AS bio,
                    CASE WHEN f.user_one_id = ? THEN COALESCE(u2.profile_picture, '') ELSE COALESCE(u1.profile_picture, '') END AS profile_picture
                  FROM friendships f
                  INNER JOIN users u1 ON u1.user_id = f.user_one_id
                  INNER JOIN users u2 ON u2.user_id = f.user_two_id
                  WHERE f.user_one_id = ? OR f.user_two_id = ?";
    $friendStmt = $conn->prepare($friendSql);
    if ($friendStmt) {
        $friendStmt->bind_param('iiiiiiii', $viewerId, $viewerId, $viewerId, $viewerId, $viewerId, $viewerId, $viewerId, $viewerId);
        $friendStmt->execute();
        $friendRes = $friendStmt->get_result();
        while ($friendRes && ($row = $friendRes->fetch_assoc())) {
            $fid = (int) ($row['friend_id'] ?? 0);
            if ($fid <= 0) continue;
            $name = trim((string) ($row['friend_name'] ?? 'KITA User'));
            $location = trim((string) ($row['location'] ?? ''));
            $strand = trim((string) ($row['strand'] ?? ''));
            $bio = trim((string) ($row['bio'] ?? ''));
            $friends[$fid] = [
                'id' => $fid,
                'name' => $name,
                'handle' => social_user_handle($name),
                'status' => 'Connected',
                'bio' => $bio !== '' ? $bio : ($location !== '' ? 'From ' . $location : 'KITA member'),
                'tags' => array_values(array_filter([$strand, $location])),
                'media' => [],
                'avatar' => social_avatar_path((string) ($row['profile_picture'] ?? '')),
                'threadId' => null,
                'messages' => []
            ];
        }
        $friendStmt->close();
    }
}

// Threads for this user (supports employers via signed IDs)
$threadRows = [];
$threadSql = "SELECT t.thread_id, t.updated_at, otherm.user_id AS other_combined
              FROM chat_threads t
              INNER JOIN chat_thread_members mine ON mine.thread_id = t.thread_id AND mine.user_id = ?
              INNER JOIN chat_thread_members otherm ON otherm.thread_id = t.thread_id AND otherm.user_id <> ?
              ORDER BY t.updated_at DESC
              LIMIT 200";
$threadStmt = $conn->prepare($threadSql);
if ($threadStmt) {
    $threadStmt->bind_param('ii', $viewerCombined, $viewerCombined);
    $threadStmt->execute();
    $threadRes = $threadStmt->get_result();
    while ($threadRes && ($row = $threadRes->fetch_assoc())) {
        $threadRows[] = [
            'thread_id' => (int) ($row['thread_id'] ?? 0),
            'other_combined' => (int) ($row['other_combined'] ?? 0),
            'updated_at' => (string) ($row['updated_at'] ?? '')
        ];
    }
    $threadStmt->close();
}

$threadIds = array_values(array_filter(array_map(static fn($r) => (int) ($r['thread_id'] ?? 0), $threadRows)));
$messagesByThread = [];
if (!empty($threadIds)) {
    $in = implode(',', array_map('intval', $threadIds));
    $msgSql = "SELECT message_id, thread_id, sender_id, body, created_at
               FROM chat_messages
               WHERE thread_id IN ({$in})
               ORDER BY created_at ASC, message_id ASC
               LIMIT 1000";
    $msgRes = $conn->query($msgSql);
    while ($msgRes && ($row = $msgRes->fetch_assoc())) {
        $tid = (int) ($row['thread_id'] ?? 0);
        if ($tid <= 0) continue;
        $dt = (string) ($row['created_at'] ?? '');
        $time = '';
        if ($dt !== '') {
            $time = date('H:i', strtotime($dt));
        }
        $messagesByThread[$tid][] = [
            'from' => ((int) ($row['sender_id'] ?? 0) === $viewerCombined) ? 'me' : 'them',
            'text' => (string) ($row['body'] ?? ''),
            'time' => $time,
            'created_at' => $dt
        ];
    }
}

// Lookup other participants (users + employers)
$otherCombined = array_values(array_unique(array_filter(array_map(static fn($r) => (int) ($r['other_combined'] ?? 0), $threadRows))));
$userIds = [];
$employerIds = [];
foreach ($otherCombined as $combined) {
    if ($combined < 0) {
        $employerIds[] = abs($combined);
    } elseif ($combined > 0) {
        $userIds[] = $combined;
    }
}

$profilesByCombined = [];
if (!empty($userIds)) {
    $safe = implode(',', array_map('intval', array_unique($userIds)));
    $nameColumn = users_name_column();
    $selectParts = [];
    $selectParts[] = users_has_column('user_id') ? "user_id" : "id AS user_id";
    $selectParts[] = users_has_column($nameColumn) ? "COALESCE(`{$nameColumn}`, 'KITA User') AS name" : "'KITA User' AS name";
    $selectParts[] = users_has_column('strand') ? "COALESCE(strand, '') AS strand" : "'' AS strand";
    $selectParts[] = users_has_column('location') ? "COALESCE(location, '') AS location" : "'' AS location";
    $selectParts[] = users_has_column('bio') ? "COALESCE(bio, '') AS bio" : "'' AS bio";
    $selectParts[] = users_has_column('profile_picture') ? "COALESCE(profile_picture, '') AS profile_picture" : "'' AS profile_picture";
    $sql = "SELECT " . implode(', ', $selectParts) . " FROM users WHERE " . (users_has_column('user_id') ? "user_id" : "id") . " IN ({$safe})";
    $res = $conn->query($sql);
    while ($res && ($row = $res->fetch_assoc())) {
        $uid = (int) ($row['user_id'] ?? 0);
        if ($uid <= 0) continue;
        $name = trim((string) ($row['name'] ?? 'KITA User'));
        $location = trim((string) ($row['location'] ?? ''));
        $strand = trim((string) ($row['strand'] ?? ''));
        $bio = trim((string) ($row['bio'] ?? ''));
        $profilesByCombined[$uid] = [
            'id' => $uid,
            'type' => 'user',
            'name' => $name,
            'handle' => social_user_handle($name),
            'status' => 'Connected',
            'bio' => $bio !== '' ? $bio : ($location !== '' ? 'From ' . $location : 'KITA member'),
            'tags' => array_values(array_filter([$strand, $location])),
            'media' => [],
            'avatar' => social_avatar_path((string) ($row['profile_picture'] ?? ''))
        ];
    }
}

if (!empty($employerIds) && db_table_exists('employers')) {
    $safe = implode(',', array_map('intval', array_unique($employerIds)));
    $sql = "SELECT id, COALESCE(company_name, contact_name, 'Employer') AS name,
                   COALESCE(industry, '') AS industry,
                   COALESCE(location, '') AS location,
                   COALESCE(bio, '') AS bio,
                   COALESCE(profile_picture, '') AS profile_picture
            FROM employers
            WHERE id IN ({$safe})";
    $res = $conn->query($sql);
    while ($res && ($row = $res->fetch_assoc())) {
        $eid = (int) ($row['id'] ?? 0);
        if ($eid <= 0) continue;
        $name = trim((string) ($row['name'] ?? 'Employer'));
        $industry = trim((string) ($row['industry'] ?? ''));
        $location = trim((string) ($row['location'] ?? ''));
        $bio = trim((string) ($row['bio'] ?? ''));
        $tags = array_values(array_filter([$industry, $location]));
        $profilesByCombined[-$eid] = [
            'id' => $eid,
            'type' => 'employer',
            'name' => $name,
            'handle' => social_user_handle($name),
            'status' => 'Company',
            'bio' => $bio !== '' ? $bio : ($location !== '' ? 'Based in ' . $location : 'Company'),
            'tags' => $tags,
            'media' => [],
            'avatar' => social_avatar_path((string) ($row['profile_picture'] ?? ''))
        ];
    }
}

$conversations = [];
foreach ($threadRows as $tr) {
    $threadId = (int) ($tr['thread_id'] ?? 0);
    $otherCombined = (int) ($tr['other_combined'] ?? 0);
    if ($threadId <= 0 || $otherCombined === 0) continue;
    $profile = $profilesByCombined[$otherCombined] ?? null;
    if (!$profile) {
        $fallbackName = $otherCombined < 0 ? 'Employer' : 'KITA User';
        $profile = [
            'id' => abs($otherCombined),
            'type' => $otherCombined < 0 ? 'employer' : 'user',
            'name' => $fallbackName,
            'handle' => social_user_handle($fallbackName),
            'status' => $otherCombined < 0 ? 'Company' : 'Connected',
            'bio' => $otherCombined < 0 ? 'Company' : 'KITA member',
            'tags' => [],
            'media' => [],
            'avatar' => ''
        ];
    }

    $conversations[] = [
        'id' => $threadId,
        'threadId' => $threadId,
        'targetUserId' => $profile['type'] === 'user' ? $profile['id'] : null,
        'targetEmployerId' => $profile['type'] === 'employer' ? $profile['id'] : null,
        'isCompany' => $profile['type'] === 'employer',
        'name' => $profile['name'],
        'handle' => $profile['handle'],
        'status' => $profile['status'],
        'bio' => $profile['bio'],
        'tags' => $profile['tags'],
        'media' => $profile['media'],
        'avatar' => $profile['avatar'],
        'messages' => $messagesByThread[$threadId] ?? []
    ];
}

// Add friends without threads (users only)
if ($viewerType === 'user' && !empty($friends)) {
    $existing = [];
    foreach ($conversations as $c) {
        $tid = (int) ($c['targetUserId'] ?? 0);
        if ($tid > 0) $existing[$tid] = true;
    }
    foreach ($friends as $fid => $friend) {
        if (isset($existing[$fid])) continue;
        $friend['targetUserId'] = $fid;
        $friend['isCompany'] = false;
        $friend['id'] = -1 * max(1, (int) $fid);
        $conversations[] = $friend;
    }
}

usort($conversations, static function (array $a, array $b): int {
    $aLast = $a['messages'][count($a['messages']) - 1]['created_at'] ?? '';
    $bLast = $b['messages'][count($b['messages']) - 1]['created_at'] ?? '';
    return strcmp((string) $bLast, (string) $aLast);
});

// Incoming/outgoing requests (users only)
$incoming = [];
$outgoing = [];
if ($viewerType === 'user') {
    $incomingSql = "SELECT fr.request_id, fr.from_user_id, COALESCE(u.username, u.full_name, 'KITA User') AS from_name,
                           COALESCE(u.profile_picture, '') AS profile_picture, fr.created_at
                    FROM friend_requests fr
                    INNER JOIN users u ON u.user_id = fr.from_user_id
                    WHERE fr.to_user_id = ? AND fr.status = 'pending'
                    ORDER BY fr.created_at DESC";
    $inStmt = $conn->prepare($incomingSql);
    if ($inStmt) {
        $inStmt->bind_param('i', $viewerId);
        $inStmt->execute();
        $res = $inStmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $name = trim((string) ($row['from_name'] ?? 'KITA User'));
            $incoming[] = [
                'request_id' => (int) ($row['request_id'] ?? 0),
                'from_user_id' => (int) ($row['from_user_id'] ?? 0),
                'from_name' => $name,
                'from_handle' => social_user_handle($name),
                'avatar' => social_avatar_path((string) ($row['profile_picture'] ?? '')),
                'created_at' => (string) ($row['created_at'] ?? '')
            ];
        }
        $inStmt->close();
    }

    $outSql = "SELECT fr.request_id, fr.to_user_id, fr.status, fr.created_at,
                      COALESCE(u.username, u.full_name, 'KITA User') AS to_name
               FROM friend_requests fr
               INNER JOIN users u ON u.user_id = fr.to_user_id
               WHERE fr.from_user_id = ?
               ORDER BY fr.created_at DESC";
    $outStmt = $conn->prepare($outSql);
    if ($outStmt) {
        $outStmt->bind_param('i', $viewerId);
        $outStmt->execute();
        $res = $outStmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $name = trim((string) ($row['to_name'] ?? 'KITA User'));
            $outgoing[] = [
                'request_id' => (int) ($row['request_id'] ?? 0),
                'to_user_id' => (int) ($row['to_user_id'] ?? 0),
                'to_name' => $name,
                'to_handle' => social_user_handle($name),
                'status' => (string) ($row['status'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? '')
            ];
        }
        $outStmt->close();
    }
}

$notifications = [];
if ($viewerType === 'user') {
    $notifStmt = $conn->prepare(
        "SELECT notification_id, type, title, body, data_json, is_read, created_at
         FROM notifications
         WHERE user_id = ?
         ORDER BY created_at DESC, notification_id DESC
         LIMIT 120"
    );
    if ($notifStmt) {
        $notifStmt->bind_param('i', $viewerId);
        $notifStmt->execute();
        $notifRes = $notifStmt->get_result();
        while ($notifRes && ($row = $notifRes->fetch_assoc())) {
            $payload = null;
            $rawData = (string) ($row['data_json'] ?? '');
            if ($rawData !== '') {
                $decoded = json_decode($rawData, true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }
            $notifications[] = [
                'id' => (int) ($row['notification_id'] ?? 0),
                'type' => (string) ($row['type'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'body' => (string) ($row['body'] ?? ''),
                'data' => $payload,
                'is_read' => (int) ($row['is_read'] ?? 0) === 1,
                'created_at' => (string) ($row['created_at'] ?? '')
            ];
        }
        $notifStmt->close();
    }
}

social_json([
    'ok' => true,
    'viewer' => [
        'id' => $viewerId,
        'name' => $viewerName,
        'handle' => social_user_handle($viewerName)
    ],
    'conversations' => $conversations,
    'incoming_requests' => $incoming,
    'outgoing_requests' => $outgoing,
    'notifications' => $notifications
]);
