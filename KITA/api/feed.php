<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$conn = db();

$postSql = "
    SELECT
        p.post_id,
        p.user_id,
        p.content,
        p.image,
        p.created_at,
        u.username
    FROM posts p
    INNER JOIN users u ON u.user_id = p.user_id
    ORDER BY p.created_at DESC
    LIMIT 100
";

$postRes = $conn->query($postSql);
if (!$postRes) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $conn->error]);
    exit;
}

$posts = [];
$postIds = [];
while ($row = $postRes->fetch_assoc()) {
    $postId = (int) $row['post_id'];
    $postIds[] = $postId;
    $posts[$postId] = [
        'post_id' => $postId,
        'user_id' => (int) $row['user_id'],
        'username' => (string) $row['username'],
        'content' => (string) ($row['content'] ?? ''),
        'image' => (string) ($row['image'] ?? ''),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'comments' => [],
    ];
}

if (!empty($postIds)) {
    $in = implode(',', array_map('intval', $postIds));
    $commentSql = "
        SELECT
            c.comment_id,
            c.post_id,
            c.user_id,
            c.comment,
            c.created_at,
            u.username
        FROM comments c
        INNER JOIN users u ON u.user_id = c.user_id
        WHERE c.post_id IN ($in)
        ORDER BY c.created_at ASC
    ";
    $commentRes = $conn->query($commentSql);
    if ($commentRes) {
        while ($c = $commentRes->fetch_assoc()) {
            $pid = (int) $c['post_id'];
            if (!isset($posts[$pid])) {
                continue;
            }
            $posts[$pid]['comments'][] = [
                'comment_id' => (int) $c['comment_id'],
                'user_id' => (int) $c['user_id'],
                'username' => (string) $c['username'],
                'comment' => (string) ($c['comment'] ?? ''),
                'created_at' => (string) ($c['created_at'] ?? ''),
            ];
        }
    }
}

echo json_encode([
    'ok' => true,
    'posts' => array_values($posts),
]);
