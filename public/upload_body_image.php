<?php
// 本文用画像の即時アップロードAPI（管理画面のエディタ専用 / AJAX）
// POST: csrf_token, post_id, image(ファイル)
// 成功: {"id": 3, "placeholder": "[image:3]", "url": "uploads/posts/6/body_xxxx.png"}
// ※ 公開API（api/）とは別物。ログイン+CSRF必須の書き込みエンドポイント。

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/Post.php';
require_once __DIR__ . '/../src/PostImage.php';
require_once __DIR__ . '/../src/upload.php';

header('Content-Type: application/json; charset=UTF-8');

function json_out(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

start_session();

if (!is_logged_in()) {
    json_out(['error' => 'ログインが必要です。'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_out(['error' => 'POSTメソッドのみ利用できます。'], 405);
}

// verify_csrf() は失敗時にHTMLで exit するため、ここではJSONで返す
$sentToken = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sentToken)) {
    json_out(['error' => '不正なリクエストです（CSRFトークンが一致しません）。'], 403);
}

$postId = (int) ($_POST['post_id'] ?? 0);
if ($postId <= 0 || Post::find($postId) === null) {
    json_out(['error' => '対象の投稿が見つかりません。'], 404);
}

$file = $_FILES['image'] ?? null;
if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE) {
    json_out(['error' => '画像ファイルを指定してください。'], 422);
}

$errors = validate_upload_image($file);
if (count($errors) > 0) {
    json_out(['error' => implode(' ', $errors)], 422);
}

try {
    $path = store_upload_image($file, $postId, 'body');
    $imageId = PostImage::create($postId, $path);

    json_out([
        'id'          => $imageId,
        'placeholder' => '[image:' . $imageId . ']',
        'url'         => $path,
    ]);
} catch (RuntimeException | PDOException $e) {
    json_out(['error' => 'サーバーエラーが発生しました。'], 500);
}
