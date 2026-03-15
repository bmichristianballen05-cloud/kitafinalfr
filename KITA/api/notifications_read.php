<?php
declare(strict_types=1);

require_once __DIR__ . '/social_common.php';

ensure_social_tables();
$auth = social_require_user();
$viewerId = (int) $auth['user_id'];
$input = social_input();

$action = strtolower(trim((string) ($input['action'] ?? 'all')));
$notificationId = (int) ($input['notification_id'] ?? 0);

$conn = db();

if ($action === 'one' && $notificationId > 0) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    if (!$stmt) {
        social_json(['ok' => false, 'error' => 'Could not update notification'], 500);
    }
    $stmt->bind_param('ii', $notificationId, $viewerId);
    $stmt->execute();
    $stmt->close();
    social_json(['ok' => true]);
}

$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
if (!$stmt) {
    social_json(['ok' => false, 'error' => 'Could not update notifications'], 500);
}
$stmt->bind_param('i', $viewerId);
$stmt->execute();
$stmt->close();

social_json(['ok' => true]);

