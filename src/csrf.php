<?php
// CSRF対策（トークン方式 / Phase 2）
// フォームに csrf_field() を埋め込み、POST受信時に verify_csrf() で検証する。

// セッションにトークンがなければ生成して返す
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// フォームに埋め込む hidden フィールドを返す
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

// POSTされたトークンを検証。不一致なら403で終了
function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        http_response_code(403);
        exit('不正なリクエストです（CSRFトークンが一致しません）');
    }
}
