<?php
// 画像アップロード処理（ヒーロー画像・本文中画像 共通、Phase 4）
// 保存先ルール：/uploads/posts/{post_id}/ 配下（仕様書5章 準備②）

const UPLOAD_MAX_BYTES = 5 * 1024 * 1024; // 5MB

const UPLOAD_ALLOWED_MIME = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

// アップロードファイルを検証。エラーメッセージの配列を返す（空なら合格）
// $file は $_FILES['xxx'] の1要素。未選択（error === UPLOAD_ERR_NO_FILE）は合格扱い
function validate_upload_image(array $file): array
{
    $errors = [];

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return $errors;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = '画像のアップロードに失敗しました。';
        return $errors;
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        $errors[] = '不正なアップロードです。';
        return $errors;
    }

    if ($file['size'] > UPLOAD_MAX_BYTES) {
        $errors[] = '画像は5MB以内にしてください。';
        return $errors;
    }

    // 拡張子・Content-Typeは信用せず、実バイナリからMIMEを判定する
    $mime = mime_content_type($file['tmp_name']);
    if (!array_key_exists($mime, UPLOAD_ALLOWED_MIME)) {
        $errors[] = '画像はJPEG・PNG・GIF・WebPのいずれかにしてください。';
    }

    return $errors;
}

// アップロードファイルを /uploads/posts/{post_id}/ に保存し、
// public/ からの相対パス（例: uploads/posts/3/hero_65f1.jpg）を返す。
// ファイルが未選択の場合は null を返す。
function store_upload_image(array $file, int $postId, string $prefix): ?string
{
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $mime = mime_content_type($file['tmp_name']);
    $ext = UPLOAD_ALLOWED_MIME[$mime];

    // 保存失敗を検知せずDBにパスだけ残ると画像リンク切れになるため、
    // mkdir / move_uploaded_file の失敗は例外として即座に知らせる
    $dir = __DIR__ . '/../public/uploads/posts/' . $postId;
    if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
        throw new RuntimeException('アップロード先ディレクトリを作成できません: ' . $dir);
    }

    $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        throw new RuntimeException('画像ファイルを保存できません: ' . $dir . '/' . $filename);
    }

    return 'uploads/posts/' . $postId . '/' . $filename;
}

// 相対パス指定で保存済み画像を削除する（uploads/posts/ 配下のみ許可）
function delete_upload_image(?string $relativePath): void
{
    if ($relativePath === null || !str_starts_with($relativePath, 'uploads/posts/')) {
        return;
    }

    $fullPath = __DIR__ . '/../public/' . $relativePath;
    if (is_file($fullPath)) {
        unlink($fullPath);
    }
}

// 投稿に紐づくアップロードディレクトリを丸ごと削除する（投稿削除時に使用）
function delete_post_upload_dir(int $postId): void
{
    $dir = __DIR__ . '/../public/uploads/posts/' . $postId;
    if (!is_dir($dir)) {
        return;
    }

    foreach (glob($dir . '/*') as $file) {
        unlink($file);
    }
    rmdir($dir);
}

// $_FILES['body_images'] のような複数ファイル入力を、
// 1ファイルずつの $_FILES 形式の配列のリストに正規化する（未選択分は除外）
function normalize_files_array(array $filesField): array
{
    $files = [];
    $count = count($filesField['name']);

    for ($i = 0; $i < $count; $i++) {
        if ($filesField['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $files[] = [
            'name'     => $filesField['name'][$i],
            'type'     => $filesField['type'][$i],
            'tmp_name' => $filesField['tmp_name'][$i],
            'error'    => $filesField['error'][$i],
            'size'     => $filesField['size'][$i],
        ];
    }

    return $files;
}
