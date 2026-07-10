<?php
// 共通ヘルパー関数

// XSS対策：出力時のエスケープ用ショートカット
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// 指定URLへリダイレクトして終了
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}
