<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

function social_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function social_require_user(): array
{
    $user = $_SESSION['user'] ?? null;
    $employer = $_SESSION['employer'] ?? null;
    if (!is_array($user) && !is_array($employer)) {
        social_json(['ok' => false, 'error' => 'Unauthorized'], 401);
    }
    if (is_array($user)) {
        $userId = (int) ($user['user_id'] ?? 0);
        $type = 'user';
    } elseif (is_array($employer)) {
        $userId = (int) ($employer['id'] ?? 0);
        $type = 'employer';
    } else {
        social_json(['ok' => false, 'error' => 'Invalid session'], 401);
    }
    if ($userId <= 0) {
        social_json(['ok' => false, 'error' => 'Invalid session'], 401);
    }
    return ['user' => $user ?: $employer, 'user_id' => $userId, 'type' => $type];
}

function social_input(): array
{
    $raw = file_get_contents('php://input');
    $json = [];
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $json = $decoded;
        }
    }
    return array_merge($_POST ?: [], $json);
}

function social_user_handle(string $name): string
{
    $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $name));
    $slug = trim($slug, '_');
    if ($slug === '') {
        $slug = 'kita_user';
    }
    return '@' . $slug;
}

function social_avatar_path(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') return '';
    if (preg_match('/^https?:\/\//i', $raw)) return $raw;
    if (str_starts_with($raw, 'uploads/')) return $raw;
    if (str_starts_with($raw, '/uploads/')) return ltrim($raw, '/');
    return 'uploads/profile_pics/' . ltrim($raw, '/');
}

function social_create_or_get_thread(string $typeA, int $idA, string $typeB, int $idB): int
{
    $conn = db();

    $combinedA = $typeA === 'user' ? $idA : -$idA;
    $combinedB = $typeB === 'user' ? $idB : -$idB;

    $sql = "SELECT t.thread_id
            FROM chat_threads t
            INNER JOIN chat_thread_members m1 ON m1.thread_id = t.thread_id AND m1.user_id = ?
            INNER JOIN chat_thread_members m2 ON m2.thread_id = t.thread_id AND m2.user_id = ?
            ORDER BY t.updated_at DESC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ii', $combinedA, $combinedB);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (is_array($row) && (int) ($row['thread_id'] ?? 0) > 0) {
            return (int) $row['thread_id'];
        }
    }

    $conn->query("INSERT INTO chat_threads () VALUES ()");
    $threadId = (int) $conn->insert_id;
    if ($threadId <= 0) {
        return 0;
    }

    $ins = $conn->prepare("INSERT IGNORE INTO chat_thread_members (thread_id, user_id) VALUES (?, ?)");
    if ($ins) {
        $ins->bind_param('ii', $threadId, $combinedA);
        $ins->execute();
        $ins->bind_param('ii', $threadId, $combinedB);
        $ins->execute();
        $ins->close();
    }

    return $threadId;
}

function social_are_friends(int $userA, int $userB): bool
{
    $a = min($userA, $userB);
    $b = max($userA, $userB);
    $conn = db();
    $stmt = $conn->prepare("SELECT friendship_id FROM friendships WHERE user_one_id = ? AND user_two_id = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('ii', $a, $b);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return is_array($row);
}

