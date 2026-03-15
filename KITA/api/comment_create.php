<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$userId = (int) $_SESSION['user']['user_id'];
$postId = (int) ($_POST['post_id'] ?? 0);
$comment = trim((string) ($_POST['comment'] ?? ''));

if ($postId <= 0 || $comment === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'post_id and comment are required']);
    exit;
}

$conn = db();

$postCheck = $conn->prepare("SELECT post_id FROM posts WHERE post_id = ? LIMIT 1");
if (!$postCheck) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $conn->error]);
    exit;
}
$postCheck->bind_param('i', $postId);
$postCheck->execute();
$postResult = $postCheck->get_result();
$postExists = $postResult && $postResult->num_rows > 0;
$postCheck->close();

if (!$postExists) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Post not found']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $conn->error]);
    exit;
}
$stmt->bind_param('iis', $postId, $userId, $comment);
$ok = $stmt->execute();
$error = $stmt->error;
$commentId = (int) $stmt->insert_id;
$stmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
}

echo json_encode(['ok' => true, 'comment_id' => $commentId]);
