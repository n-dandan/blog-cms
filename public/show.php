<?php
// 投稿詳細（Read）＋ コメント管理（Phase 4／管理画面内の簡易ログ）
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/Post.php';
require_once __DIR__ . '/../src/Tag.php';
require_once __DIR__ . '/../src/PostImage.php';
require_once __DIR__ . '/../src/Comment.php';

start_session();
require_login();

$id = (int) ($_GET['id'] ?? 0);
$post = $id > 0 ? Post::find($id) : null;

if ($post === null) {
    http_response_code(404);
    $pageTitle = '投稿が見つかりません';
    require __DIR__ . '/../views/header.php';
    echo '<p>指定された投稿は見つかりませんでした。</p>';
    echo '<p><a href="index.php">一覧へ戻る</a></p>';
    require __DIR__ . '/../views/footer.php';
    exit;
}

$commentErrors = [];
$commentInput = ['author_name' => current_user_name(), 'body' => ''];

// コメントの追加・削除（POST）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'add_comment') {
        $commentInput = [
            'author_name' => $_POST['author_name'] ?? '',
            'body'        => $_POST['body'] ?? '',
        ];
        $commentErrors = Comment::validate($commentInput);

        if (count($commentErrors) === 0) {
            Comment::create($id, $commentInput['author_name'], $commentInput['body']);
            $_SESSION['flash'] = 'コメントを追加しました。';
            redirect('show.php?id=' . $id);
        }
    } elseif ($action === 'delete_comment') {
        Comment::delete((int) ($_POST['comment_id'] ?? 0));
        $_SESSION['flash'] = 'コメントを削除しました。';
        redirect('show.php?id=' . $id);
    }
}

$tags     = Tag::forPost($id);
$images   = PostImage::forPost($id);
$comments = Comment::forPost($id);
$bodyHtml = PostImage::renderBody($post['body'], $images);

// 操作完了メッセージ（作成・更新・コメント操作後に表示）
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = $post['title'];
require __DIR__ . '/../views/header.php';
?>

<?php if ($flash !== null): ?>
  <div class="flash"><?= e($flash) ?></div>
<?php endif; ?>

<?php $isPublished = $post['status'] === 'published'; ?>
<div class="page-head">
  <h1>投稿詳細</h1>
  <div>
    <form method="post" action="toggle_status.php" class="inline-form"
          onsubmit="return confirm('<?= $isPublished ? 'この投稿を下書きに戻しますか？' : 'この投稿を公開しますか？' ?>');">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= e((string) $post['id']) ?>">
      <input type="hidden" name="return_to" value="detail">
      <button type="submit" class="btn btn-small <?= $isPublished ? 'btn-secondary' : '' ?>">
        <?= $isPublished ? '下書きに戻す' : '公開する' ?>
      </button>
    </form>
    <a class="btn btn-small btn-secondary" href="edit.php?id=<?= e((string) $post['id']) ?>">編集</a>
    <a class="btn btn-small btn-danger" href="delete.php?id=<?= e((string) $post['id']) ?>">削除</a>
  </div>
</div>

<div class="detail-card">
  <?php if ($post['hero_image'] !== null): ?>
    <img class="hero-image" src="<?= e($post['hero_image']) ?>" alt="<?= e($post['title']) ?>">
  <?php endif; ?>

  <h2><?= e($post['title']) ?></h2>
  <div class="detail-meta">
    <span class="badge badge-<?= e($post['type']) ?>">
      <?= $post['type'] === 'notice' ? 'お知らせ' : 'ブログ' ?>
    </span>
    <span class="badge badge-<?= e($post['status']) ?>">
      <?= $post['status'] === 'published' ? '公開' : '下書き' ?>
    </span>
    <?php foreach ($tags as $tagName): ?>
      <span class="badge badge-tag"><?= e($tagName) ?></span>
    <?php endforeach; ?>
    ｜ 投稿者: <?= e($post['user_name']) ?>
    ｜ 公開日時: <?= e($post['published_at'] ?? '未公開') ?>
    ｜ 更新日時: <?= e($post['updated_at']) ?>
  </div>
  <div class="detail-body"><?= $bodyHtml /* renderBody内でエスケープ済み */ ?></div>
</div>

<section class="comment-section">
  <h2>コメント（<?= e((string) count($comments)) ?>件）</h2>

  <?php if (count($commentErrors) > 0): ?>
    <div class="errors">
      <ul>
        <?php foreach ($commentErrors as $error): ?>
          <li><?= e($error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="form-card comment-form">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_comment">
      <div class="form-group">
        <label for="author_name">投稿者名</label>
        <input type="text" id="author_name" name="author_name" value="<?= e($commentInput['author_name']) ?>" maxlength="100">
      </div>
      <div class="form-group">
        <label for="comment_body">コメント</label>
        <textarea id="comment_body" name="body" class="comment-textarea"><?= e($commentInput['body']) ?></textarea>
      </div>
      <button type="submit" class="btn">コメントを追加</button>
    </form>
  </div>

  <?php if (count($comments) === 0): ?>
    <p>コメントはまだありません。</p>
  <?php else: ?>
    <ul class="comment-list">
      <?php foreach ($comments as $comment): ?>
        <li class="comment-item">
          <div class="comment-meta">
            <strong><?= e($comment['author_name']) ?></strong>
            <span><?= e($comment['created_at']) ?></span>
            <form method="post" class="comment-delete-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_comment">
              <input type="hidden" name="comment_id" value="<?= e((string) $comment['id']) ?>">
              <button type="submit" class="btn btn-small btn-danger">削除</button>
            </form>
          </div>
          <div class="comment-body"><?= nl2br(e($comment['body'])) ?></div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<p style="margin-top: 16px;"><a href="index.php">← 一覧へ戻る</a></p>

<?php require __DIR__ . '/../views/footer.php'; ?>
