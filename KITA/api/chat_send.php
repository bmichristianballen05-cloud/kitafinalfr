<?php
declare(strict_types=1);

require_once __DIR__ . '/social_common.php';

ensure_social_tables();
$auth = social_require_user();
$viewerId = (int) $auth['user_id'];
$viewer = $auth['user'];
$viewerType = $auth['type'];
$viewerName = trim((string) (($viewer['username'] ?? '') ?: ($viewer['full_name'] ?? '') ?: ($viewer['company_name'] ?? '') ?: 'KITA User'));

$input = social_input();
$threadId = (int) ($input['thread_id'] ?? 0);
$targetUserId = (int) ($input['target_user_id'] ?? 0);
$targetEmployerId = (int) ($input['target_employer_id'] ?? 0);
$body = trim((string) ($input['message'] ?? ''));

if ($body === '') {
    social_json(['ok' => false, 'error' => 'Message cannot be empty'], 400);
}
if (mb_strlen($body) > 1000) {
    social_json(['ok' => false, 'error' => 'Message too long'], 400);
}

$conn = db();

if ($threadId <= 0) {
    if ($targetEmployerId > 0) {
        $targetId = $targetEmployerId;
        $targetType = 'employer';
    } elseif ($targetUserId > 0) {
        $targetId = $targetUserId;
        $targetType = 'user';
    } else {
        social_json(['ok' => false, 'error' => 'Missing thread/target'], 400);
    }
    if (($viewerType === 'employer' && $viewerId === $targetId && $targetType === 'employer') ||
        ($viewerType === 'user' && $viewerId === $targetId && $targetType === 'user')) {
        social_json(['ok' => false, 'error' => 'Invalid target'], 400);
    }
    // Allow messaging any valid registered user or employer.
    if ($targetType === 'user') {
        $userStmt = $conn->prepare("SELECT 1 FROM users WHERE user_id = ? LIMIT 1");
        $userStmt->bind_param('i', $targetId);
    } else {
        $userStmt = $conn->prepare("SELECT 1 FROM employers WHERE id = ? LIMIT 1");
        $userStmt->bind_param('i', $targetId);
    }
    if (!$userStmt) {
        social_json(['ok' => false, 'error' => 'Could not verify target'], 500);
    }
    $userStmt->execute();
    $userRes = $userStmt->get_result();
    $userExists = $userRes && $userRes->fetch_assoc();
    $userStmt->close();
    if (!$userExists) {
        social_json(['ok' => false, 'error' => 'Target not found'], 404);
    }

    $threadId = social_create_or_get_thread($viewerType, $viewerId, $targetType, $targetId);
    if ($threadId <= 0) {
        social_json(['ok' => false, 'error' => 'Could not open chat thread'], 500);
    }
} else {
    $check = $conn->prepare("SELECT user_id FROM chat_thread_members WHERE thread_id = ? ORDER BY user_id ASC");
    if (!$check) {
        social_json(['ok' => false, 'error' => 'Could not verify thread'], 500);
    }
    $check->bind_param('i', $threadId);
    $check->execute();
    $res = $check->get_result();
    $members = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $members[] = (int) ($row['user_id'] ?? 0);
    }
    $check->close();
    $viewerCombined = $viewerType === 'user' ? $viewerId : -$viewerId;
    if (!in_array($viewerCombined, $members, true)) {
        social_json(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    if ($targetEmployerId <= 0 && $targetUserId <= 0) {
        foreach ($members as $memberId) {
            if ($memberId !== $viewerCombined) {
                $targetCombined = $memberId;
                $targetId = abs($memberId);
                $targetType = $memberId < 0 ? 'employer' : 'user';
                break;
            }
        }
    }
}

$msg = $conn->prepare("INSERT INTO chat_messages (thread_id, sender_id, body) VALUES (?, ?, ?)");
if (!$msg) {
    social_json(['ok' => false, 'error' => 'Could not send message'], 500);
}
$viewerCombined = $viewerType === 'user' ? $viewerId : -$viewerId;
$msg->bind_param('iis', $threadId, $viewerCombined, $body);
$ok = $msg->execute();
$messageId = (int) $msg->insert_id;
$msg->close();

if (!$ok) {
    social_json(['ok' => false, 'error' => 'Could not send message'], 500);
}

$touch = $conn->prepare("UPDATE chat_threads SET updated_at = CURRENT_TIMESTAMP WHERE thread_id = ?");
if ($touch) {
    $touch->bind_param('i', $threadId);
    $touch->execute();
    $touch->close();
}

if ($targetId > 0 && $targetType === 'user') {
    $title = 'New message';
    $short = mb_strlen($body) > 90 ? (mb_substr($body, 0, 87) . '...') : $body;
    $notifBody = "{$viewerName}: {$short}";
    $payload = json_encode(['thread_id' => $threadId, 'sender_id' => $viewerCombined], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ext = null;
    $notif = $conn->prepare("INSERT INTO notifications (user_id, type, title, body, data_json, external_key, is_read) VALUES (?, 'new_message', ?, ?, ?, ?, 0)");
    if ($notif) {
        $notif->bind_param('issss', $targetId, $title, $notifBody, $payload, $ext);
        $notif->execute();
        $notif->close();
    }
}

social_json([
    'ok' => true,
    'thread_id' => $threadId,
    'message_id' => $messageId,
    'message' => $body
]);

