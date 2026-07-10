<?php
// 公開JSON API（Phase 3 + Phase 4拡張）
// GET専用・published の投稿のみ返す。書き込み系は提供しない。
//
// 一覧:  GET /api/posts?type=notice&tag=旅行&limit=10
// 単一:  GET /api/posts/{id}  （または /api/posts.php?id={id}）

require_once __DIR__ . '/../../src/Post.php';
require_once __DIR__ . '/../../src/Tag.php';
require_once __DIR__ . '/../../src/PostImage.php';

header('Content-Type: application/json; charset=UTF-8');
// 別アプリ（React製 旅行プランナー）からの fetch を許可
header('Access-Control-Allow-Origin: *');

// JSONレスポンスを出力して終了
function json_response(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// public/ からの相対パスを絶対URLに変換（画像なしは null）
function public_url(?string $relativePath): ?string
{
    if ($relativePath === null) {
        return null;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    // このスクリプトのURL（.../public/api/posts.php）から public/ のベースURLを求める
    $publicBase = dirname(dirname($_SERVER['SCRIPT_NAME']));
    return $scheme . '://' . $_SERVER['HTTP_HOST']
        . rtrim($publicBase, '/') . '/' . $relativePath;
}

// 投稿レコードをAPIレスポンス用に整形（画像URL・タグ・本文中画像を付与）
function format_post(array $post): array
{
    $images = [];
    foreach (PostImage::forPost((int) $post['id']) as $image) {
        $images[] = [
            'id'  => (int) $image['id'],
            'url' => public_url($image['file_path']),
        ];
    }

    return [
        'id'             => (int) $post['id'],
        'type'           => $post['type'],
        'title'          => $post['title'],
        'body'           => $post['body'],
        'hero_image_url' => public_url($post['hero_image']),
        'tags'           => Tag::forPost((int) $post['id']),
        'images'         => $images,
        'published_at'   => $post['published_at'],
    ];
}

// 読み取り専用API：GET以外は拒否
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    json_response(['error' => 'GETメソッドのみ利用できます。'], 405);
}

try {
    // --- 単一投稿の取得 ---------------------------------------
    // /api/posts/{id}（.htaccessで ?id= に変換）または PATH_INFO
    $id = $_GET['id'] ?? null;
    if ($id === null && !empty($_SERVER['PATH_INFO'])) {
        $id = ltrim($_SERVER['PATH_INFO'], '/');
    }

    if ($id !== null) {
        if (!ctype_digit((string) $id)) {
            json_response(['error' => 'idは正の整数で指定してください。'], 400);
        }

        $post = Post::findPublished((int) $id);
        if ($post === null) {
            // 下書き・存在しないIDはどちらも404（下書きの存在を漏らさない）
            json_response(['error' => '投稿が見つかりません。'], 404);
        }

        json_response(['data' => format_post($post)]);
    }

    // --- 一覧の取得 -------------------------------------------
    $type = $_GET['type'] ?? null;
    if ($type !== null && !in_array($type, Post::TYPES, true)) {
        json_response(['error' => 'typeは blog または notice を指定してください。'], 400);
    }

    // status パラメータは受け付けるが、published 以外は許可しない
    // （下書きは絶対にAPIへ出さない）
    $status = $_GET['status'] ?? 'published';
    if ($status !== 'published') {
        json_response(['error' => 'statusは published のみ指定できます。'], 400);
    }

    // タグによる絞り込み（Phase 4）
    $tag = $_GET['tag'] ?? null;
    if ($tag !== null && ($tag === '' || mb_strlen($tag) > 50)) {
        json_response(['error' => 'tagは1〜50文字で指定してください。'], 400);
    }

    $limit = (int) ($_GET['limit'] ?? 20);
    $limit = max(1, min($limit, 100)); // 1〜100件に制限

    $data = array_map('format_post', Post::published($type, $tag, $limit));
    json_response(['data' => $data]);
} catch (PDOException $e) {
    // DB例外の詳細はクライアントに漏らさない
    json_response(['error' => 'サーバーエラーが発生しました。'], 500);
}
