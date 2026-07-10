<?php
// セッションベース認証（Phase 2）

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// セッション開始（未開始のときだけ）
function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,   // JSからのクッキー読み取りを禁止
            'samesite' => 'Lax',  // CSRF緩和
        ]);
        session_start();
    }
}

// メール + パスワードでログイン試行。成功時 true
function login(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user === false || !password_verify($password, $user['password'])) {
        return false;
    }

    // セッション固定化攻撃対策：ログイン成功時にIDを再生成
    session_regenerate_id(true);
    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['user_name'] = $user['name'];

    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function current_user_name(): string
{
    return $_SESSION['user_name'] ?? '';
}

// 未ログインならログイン画面へリダイレクト（管理画面の各ページ先頭で呼ぶ）
function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}
