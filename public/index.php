<?php
// 投稿一覧（Read）＋ 検索・ページネーション（Phase 4）
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/Post.php';
require_once __DIR__ . '/../src/Tag.php';

start_session();
require_login();

$search = trim($_GET['q'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));

$result = Post::paginate($search, $page);
$posts      = $result['posts'];
$total      = $result['total'];
$totalPages = $result['totalPages'];
$page       = $result['page'];

$tagsByPost = Tag::forPosts(array_column($posts, 'id'));

// 操作完了メッセージ（作成・更新・削除後に表示）
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = '投稿一覧';
require __DIR__ . '/../views/header.php';
?>

<div class="page-head">
  <h1>投稿一覧</h1>
  <div>
    <a class="btn btn-secondary" href="tags.php">タグ管理</a>
    <a class="btn" href="create.php">新規作成</a>
  </div>
</div>

<?php if ($flash !== null): ?>
  <div class="flash"><?= e($flash) ?></div>
<?php endif; ?>

<form method="get" class="search-form">
  <input type="text" name="q" value="<?= e($search) ?>" placeholder="タイトル・本文を検索">
  <button type="submit" class="btn">検索</button>
  <?php if ($search !== ''): ?>
    <a class="btn btn-secondary" href="index.php">クリア</a>
  <?php endif; ?>
</form>

<?php if ($search !== ''): ?>
  <p class="search-result-note">「<?= e($search) ?>」の検索結果: <?= e((string) $total) ?>件</p>
<?php endif; ?>

<?php if (count($posts) === 0): ?>
  <p><?= $search !== '' ? '該当する投稿はありません。' : '投稿はまだありません。' ?></p>
<?php else: ?>
  <table class="post-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>画像</th>
        <th>種別</th>
        <th>タイトル</th>
        <th>状態</th>
        <th>投稿者</th>
        <th>更新日時</th>
        <th>操作</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($posts as $post): ?>
        <tr>
          <td><?= e((string) $post['id']) ?></td>
          <td>
            <?php if ($post['hero_image'] !== null): ?>
              <img class="post-thumb" src="<?= e($post['hero_image']) ?>" alt="">
            <?php else: ?>
              <span class="post-thumb post-thumb-empty">なし</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge badge-<?= e($post['type']) ?>">
              <?= $post['type'] === 'notice' ? 'お知らせ' : 'ブログ' ?>
            </span>
          </td>
          <td>
            <a href="show.php?id=<?= e((string) $post['id']) ?>"><?= $post['title'] !== '' ? e($post['title']) : '（無題）' ?></a>
            <?php foreach ($tagsByPost[$post['id']] ?? [] as $tagName): ?>
              <span class="badge badge-tag"><?= e($tagName) ?></span>
            <?php endforeach; ?>
          </td>
          <td>
            <span class="badge badge-<?= e($post['status']) ?>">
              <?= $post['status'] === 'published' ? '公開' : '下書き' ?>
            </span>
          </td>
          <td><?= e($post['user_name']) ?></td>
          <td><?= e($post['updated_at']) ?></td>
          <td>
            <?php $isPublished = $post['status'] === 'published'; ?>
            <form method="post" action="toggle_status.php" class="inline-form"
                  onsubmit="return confirm('<?= $isPublished ? 'この投稿を下書きに戻しますか？' : 'この投稿を公開しますか？' ?>');">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= e((string) $post['id']) ?>">
              <input type="hidden" name="return_to" value="list">
              <input type="hidden" name="q" value="<?= e($search) ?>">
              <input type="hidden" name="page" value="<?= e((string) $page) ?>">
              <button type="submit" class="btn btn-small <?= $isPublished ? 'btn-secondary' : '' ?>">
                <?= $isPublished ? '下書きに戻す' : '公開する' ?>
              </button>
            </form>
            <a class="btn btn-small btn-secondary" href="edit.php?id=<?= e((string) $post['id']) ?>">編集</a>
            <a class="btn btn-small btn-danger" href="delete.php?id=<?= e((string) $post['id']) ?>">削除</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($totalPages > 1): ?>
    <nav class="pagination">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <?php $query = http_build_query(array_filter(['q' => $search, 'page' => $p])); ?>
        <?php if ($p === $page): ?>
          <span class="page-link page-current"><?= e((string) $p) ?></span>
        <?php else: ?>
          <a class="page-link" href="index.php?<?= e($query) ?>"><?= e((string) $p) ?></a>
        <?php endif; ?>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../views/footer.php'; ?>
