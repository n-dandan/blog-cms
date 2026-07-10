<?php /** @var string $pageTitle */ ?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'ブログCMS') ?> | ブログCMS</title>
  <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/../public/css/style.css') ?>">
</head>
<body>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="index.php">ブログCMS 管理画面</a>
    <?php if (is_logged_in()): ?>
      <nav class="header-nav">
        <span class="user-name"><?= e(current_user_name()) ?> さん</span>
        <a class="btn btn-small btn-outline" href="logout.php">ログアウト</a>
      </nav>
    <?php endif; ?>
  </div>
</header>
<main class="container">
