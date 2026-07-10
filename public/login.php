<?php
// 管理者ログイン（Phase 2）
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';

start_session();

// ログイン済みなら一覧へ
if (is_logged_in()) {
    redirect('index.php');
}

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'メールアドレスとパスワードを入力してください。';
    } elseif (login($email, $password)) {
        redirect('index.php');
    } else {
        // どちらが間違っているかは伏せる（攻撃者へのヒントを減らす）
        $error = 'メールアドレスまたはパスワードが正しくありません。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ログイン | ブログCMS</title>
  <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
</head>
<body>
<div class="login-card">
  <h1>ブログCMS ログイン</h1>

  <?php if ($error !== null): ?>
    <div class="errors"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <?= csrf_field() ?>
    <div class="form-group">
      <label for="email">メールアドレス</label>
      <input type="email" id="email" name="email" value="<?= e($email) ?>" autofocus>
    </div>
    <div class="form-group">
      <label for="password">パスワード</label>
      <input type="password" id="password" name="password">
    </div>
    <button type="submit" class="btn" style="width: 100%;">ログイン</button>
  </form>
</div>
</body>
</html>
