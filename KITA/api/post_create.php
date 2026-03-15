<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$userId = 0;
if (isset($_SESSION['user']['user_id'])) {
    $userId = (int) $_SESSION['user']['user_id'];
} elseif (isset($_SESSION['employer']['id'])) {
    // Employers can also create posts; store employer id as user_id
    $userId = (int) $_SESSION['employer']['id'];
}

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

ensure_post_meta_schema();

$content = trim((string) ($_POST['content'] ?? ''));
$strand = trim((string) ($_POST['strand'] ?? ''));
$location = trim((string) ($_POST['location'] ?? ''));
$imagePath = null;

if ($content === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Content is required']);
    exit;
}

// Handle image upload (optional - posts can be text-only)
$imagePath = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['image'];
    $size = (int)($file['size'] ?? 0);
    
    // Check file size first
    if ($size > 5 * 1024 * 1024) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Image is too large. Maximum size is 5MB.']);
        exit;
    }
    
    // Try to get mime type, fallback to extension-based detection
    $mime = '';
    $ext = '';
    
    if (function_exists('mime_content_type') && !empty($file['tmp_name'])) {
        $mime = (string) mime_content_type((string)$file['tmp_name']);
    }
    
    // Map mime types to extensions
    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/jpg' => 'jpg'
    ];
    
    // If mime type not detected, try extension from filename
    if (empty($mime) || !isset($mimeToExt[$mime])) {
        $filename = $file['name'] ?? '';
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $extToMime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
        if (isset($extToMime[$ext])) {
            $mime = $extToMime[$ext];
        }
    }
    
    // Validate mime type
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowedMimes, true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Invalid image format. Use JPG, PNG, WEBP, or GIF.']);
        exit;
    }
    
    $ext = $mimeToExt[$mime] ?? 'jpg';
    
    $dir = __DIR__ . '/../uploads/post_images';
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0777, true)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Could not create upload directory.']);
            exit;
        }
    }
    $fname = 'p' . $userId . '_' . time() . '_' . uniqid('', true) . '.' . $ext;
    $target = $dir . '/' . $fname;

    if (!move_uploaded_file((string)($file['tmp_name'] ?? ''), $target)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not save uploaded image.']);
        exit;
    }
    $imagePath = 'uploads/post_images/' . $fname;
}

$conn = db();

$stmt = $conn->prepare("INSERT INTO posts (user_id, content, image, strand, location, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $conn->error]);
    exit;
}

$stmt->bind_param('issss', $userId, $content, $imagePath, $strand, $location);
$ok = $stmt->execute();
$error = $stmt->error;
$postId = (int) $stmt->insert_id;
$stmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
}

echo json_encode(['ok' => true, 'post_id' => $postId]);