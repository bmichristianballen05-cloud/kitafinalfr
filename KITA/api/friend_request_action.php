<?php
declare(strict_types=1);

require_once __DIR__ . '/social_common.php';

ensure_social_tables();
$auth = social_require_user();
$viewerId = (int) $auth['user_id'];
$viewer = $auth['user'];
$viewerName = trim((string) (($viewer['username'] ?? '') ?: ($viewer['full_name'] ?? 'KITA User')));

$input = social_input();
$action = strtolower(trim((string) ($input['action'] ?? '')));
$targetUserId = (int) ($input['target_user_id'] ?? 0);
$requestId = (int) ($input['request_id'] ?? 0);
$conn = db();

if (!in_array($action, ['send', 'cancel', 'accept', 'decline'], true)) {
    social_json(['ok' => false, 'error' => 'Invalid action'], 400);
}

if (($action === 'send' || $action === 'cancel') && $targetUserId <= 0) {
    social_json(['ok' => false, 'error' => 'Missing target user'], 400);
}

if ($action === 'send') {
    if ($targetUserId === $viewerId) {
        social_json(['ok' => false, 'error' => 'Cannot send request to yourself'], 400);
    }
    $targetStmt = $conn->prepare("SELECT COALESCE(username, full_name, 'KITA User') AS username FROM users WHERE user_id = ? LIMIT 1");
    if (!$targetStmt) {
        social_json(['ok' => false, 'error' => 'Could not load target'], 500);
    }
    $targetStmt->bind_param('i', $targetUserId);
    $targetStmt->execute();
    $targetRes = $targetStmt->get_result();
    $target = $targetRes ? $targetRes->fetch_assoc() : null;
    $targetStmt->close();
    if (!is_array($target)) {
        social_json(['ok' => false, 'error' => 'User not found'], 404);
    }

    if (social_are_friends($viewerId, $targetUserId)) {
        social_json(['ok' => true, 'status' => 'already_friends']);
    }

    $sql = "INSERT INTO friend_requests (from_user_id, to_user_id, status)
            VALUES (?, ?, 'pending')
            ON DUPLICATE KEY UPDATE status = 'pending', updated_at = CURRENT_TIMESTAMP";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        social_json(['ok' => false, 'error' => 'Could not save request'], 500);
    }
    $stmt->bind_param('ii', $viewerId, $targetUserId);
    $stmt->execute();
    $stmt->close();

    $title = 'New connection request';
    $body = "{$viewerName} sent you a connection request.";
    $payload = json_encode(['from_user_id' => $viewerId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ext = "friend_req_{$viewerId}_{$targetUserId}_pending";
    $notif = $conn->prepare("INSERT INTO notifications (user_id, type, title, body, data_json, external_key, is_read) VALUES (?, 'connection_request', ?, ?, ?, ?, 0)
                             ON DUPLICATE KEY UPDATE is_read = 0, body = VALUES(body), data_json = VALUES(data_json), created_at = CURRENT_TIMESTAMP");
    if ($notif) {
        $notif->bind_param('issss', $targetUserId, $title, $body, $payload, $ext);
        $notif->execute();
        $notif->close();
    }

    social_json(['ok' => true, 'status' => 'sent']);
}

if ($action === 'cancel') {
    $stmt = $conn->prepare("UPDATE friend_requests SET status = 'cancelled' WHERE from_user_id = ? AND to_user_id = ? AND status = 'pending'");
    if (!$stmt) {
        social_json(['ok' => false, 'error' => 'Could not cancel request'], 500);
    }
    $stmt->bind_param('ii', $viewerId, $targetUserId);
    $stmt->execute();
    $stmt->close();
    social_json(['ok' => true, 'status' => 'cancelled']);
}

if ($requestId <= 0) {
    social_json(['ok' => false, 'error' => 'Missing request id'], 400);
}

$load = $conn->prepare("SELECT request_id, from_user_id, to_user_id, status FROM friend_requests WHERE request_id = ? LIMIT 1");
if (!$load) {
    social_json(['ok' => false, 'error' => 'Could not load request'], 500);
}
$load->bind_param('i', $requestId);
$load->execute();
$res = $load->get_result();
$req = $res ? $res->fetch_assoc() : null;
$load->close();

if (!is_array($req)) {
    social_json(['ok' => false, 'error' => 'Request not found'], 404);
}
if ((int) $req['to_user_id'] !== $viewerId) {
    social_json(['ok' => false, 'error' => 'Forbidden'], 403);
}
if ((string) ($req['status'] ?? '') !== 'pending') {
    social_json(['ok' => true, 'status' => (string) ($req['status'] ?? '')]);
}

if ($action === 'decline') {
    $stmt = $conn->prepare("UPDATE friend_requests SET status = 'declined' WHERE request_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $requestId);
        $stmt->execute();
        $stmt->close();
    }
    social_json(['ok' => true, 'status' => 'declined']);
}

// accept
$stmt = $conn->prepare("UPDATE friend_requests SET status = 'accepted' WHERE request_id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $stmt->close();
}

$fromId = (int) $req['from_user_id'];
$a = min($fromId, $viewerId);
$b = max($fromId, $viewerId);
$friendStmt = $conn->prepare("INSERT IGNORE INTO friendships (user_one_id, user_two_id) VALUES (?, ?)");
if ($friendStmt) {
    $friendStmt->bind_param('ii', $a, $b);
    $friendStmt->execute();
    $friendStmt->close();
}

social_create_or_get_thread($fromId, $viewerId);

$title = 'Connection accepted';
$body = "{$viewerName} accepted your connection request.";
$payload = json_encode(['user_id' => $viewerId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$ext = "friend_req_{$fromId}_{$viewerId}_accepted";
$notif = $conn->prepare("INSERT INTO notifications (user_id, type, title, body, data_json, external_key, is_read) VALUES (?, 'connection_accepted', ?, ?, ?, ?, 0)
                         ON DUPLICATE KEY UPDATE is_read = 0, body = VALUES(body), data_json = VALUES(data_json), created_at = CURRENT_TIMESTAMP");
if ($notif) {
    $notif->bind_param('issss', $fromId, $title, $body, $payload, $ext);
    $notif->execute();
    $notif->close();
}

social_json(['ok' => true, 'status' => 'accepted']);

