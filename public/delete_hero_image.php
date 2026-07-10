<?php
// ヒーロー画像の即時削除API（管理画面のエディタ専用 / AJAX）
// POST: csrf_token, post_id
// 成功: {"success": true}
// フォーム送信を待たずに削除するため、保存前に離脱してもファイルが残らない。

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/Post.php';
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
$post = $postId > 0 ? Post::find($postId) : null;
if ($post === null) {
    json_out(['error' => '対象の投稿が見つかりません。'], 404);
}

delete_upload_image($post['hero_image']);
Post::setHeroImage($postId, null);

json_out(['success' => true]);
