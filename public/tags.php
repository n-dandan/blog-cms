<?php
// タグ管理（作成・削除）。投稿編集画面ではここで作ったタグを選択する。
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/Tag.php';

start_session();
require_login();

$errors = [];
$nameInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nameInput = trim($_POST['name'] ?? '');

        if ($nameInput === '') {
            $errors[] = 'タグ名は必須です。';
        } elseif (mb_strlen($nameInput) > 50) {
            $errors[] = 'タグ名は50文字以内で入力してください。';
        }

        if (count($errors) === 0) {
            $newId = Tag::create($nameInput);
            if ($newId === null) {
                $errors[] = 'そのタグ名はすでに存在します。';
            } else {
                $_SESSION['flash'] = 'タグを追加しました。';
                redirect('tags.php');
            }
        }
    } elseif ($action === 'delete') {
        Tag::delete((int) ($_POST['tag_id'] ?? 0));
        $_SESSION['flash'] = 'タグを削除しました。';
        redirect('tags.php');
    }
}

$tags = Tag::withPostCounts();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = 'タグ管理';
require __DIR__ . '/../views/header.php';
?>

<div class="page-head">
  <h1>タグ管理</h1>
  <a class="btn btn-secondary" href="index.php">投稿一覧へ戻る</a>
</div>

<?php if ($flash !== null): ?>
  <div class="flash"><?= e($flash) ?></div>
<?php endif; ?>

<?php if (count($errors) > 0): ?>
  <div class="errors">
    <ul>
      <?php foreach ($errors as $error): ?>
        <li><?= e($error) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="form-card">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="form-group">
      <label for="name">新しいタグ名</label>
      <input type="text" id="name" name="name" value="<?= e($nameInput) ?>" maxlength="50" placeholder="例: 旅行">
    </div>
    <button type="submit" class="btn">タグを追加</button>
  </form>
</div>

<?php if (count($tags) === 0): ?>
  <p>タグはまだありません。</p>
<?php else: ?>
  <table class="post-table">
    <thead>
      <tr>
        <th>タグ名</th>
        <th>使用中の投稿数</th>
        <th>操作</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($tags as $tag): ?>
        <tr>
          <td><span class="badge badge-tag"><?= e($tag['name']) ?></span></td>
          <td><?= e((string) $tag['post_count']) ?></td>
          <td>
            <form method="post" class="inline-form"
                  onsubmit="return confirm('このタグを削除しますか？（投稿からの関連付けも解除されます）');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="tag_id" value="<?= e((string) $tag['id']) ?>">
              <button type="submit" class="btn btn-small btn-danger">削除</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php require __DIR__ . '/../views/footer.php'; ?>
