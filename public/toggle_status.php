<?php
// 投稿の公開状態の切り替え（投稿一覧・投稿詳細のボタンから使用）
// POST: csrf_token, id, return_to('list'/'detail'), (list時) q, page
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/Post.php';

start_session();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
$newStatus = $id > 0 ? Post::toggleStatus($id) : null;

if ($newStatus !== null) {
    $_SESSION['flash'] = $newStatus === 'published'
        ? '投稿を公開しました。'
        : '投稿を下書きに戻しました。';
}

// リダイレクト先はクライアントの生URLを信用せず、既知の安全な形だけを組み立てる（オープンリダイレクト対策）
if (($_POST['return_to'] ?? '') === 'detail') {
    redirect('show.php?id=' . $id);
}

$query = http_build_query(array_filter([
    'q'    => $_POST['q'] ?? '',
    'page' => $_POST['page'] ?? '',
]));
redirect('index.php' . ($query !== '' ? '?' . $query : ''));
