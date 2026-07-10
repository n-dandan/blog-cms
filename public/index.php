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

// 絞り込み条件（不正値は許可リストで除外）
$filterTypes    = array_values(array_intersect((array) ($_GET['type'] ?? []), Post::TYPES));
$filterStatuses = array_values(array_intersect((array) ($_GET['status'] ?? []), Post::STATUSES));
$filterTagIds   = array_values(array_filter(array_map('intval', (array) ($_GET['tag'] ?? [])), fn ($id) => $id > 0));
$hasFilter      = count($filterTypes) > 0 || count($filterStatuses) > 0 || count($filterTagIds) > 0;

$result = Post::paginate($search, $page, [
    'types'    => $filterTypes,
    'statuses' => $filterStatuses,
    'tagIds'   => $filterTagIds,
]);
$posts      = $result['posts'];
$total      = $result['total'];
$totalPages = $result['totalPages'];
$page       = $result['page'];

$allTags    = Tag::all();
$tagsByPost = Tag::forPosts(array_column($posts, 'id'));

// ページネーション・状態切替後の遷移で現在の検索/絞り込みを保つためのベースクエリ
$baseQuery = array_filter([
    'q'      => $search,
    'type'   => $filterTypes,
    'status' => $filterStatuses,
    'tag'    => $filterTagIds,
], fn ($v) => $v !== '' && $v !== []);

// 状態切替フォーム（各行）に埋め込む、現在の絞り込みを保持する hidden 群
ob_start();
foreach ($filterTypes as $t):    ?><input type="hidden" name="type[]" value="<?= e($t) ?>"><?php endforeach;
foreach ($filterStatuses as $s): ?><input type="hidden" name="status[]" value="<?= e($s) ?>"><?php endforeach;
foreach ($filterTagIds as $tid): ?><input type="hidden" name="tag[]" value="<?= e((string) $tid) ?>"><?php endforeach;
$filterHidden = ob_get_clean();

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

<form method="get" class="search-form-wrap">
  <div class="search-form">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="タイトル・本文を検索">
    <button type="submit" class="btn">検索</button>
    <?php if ($search !== '' || $hasFilter): ?>
      <a class="btn btn-secondary" href="index.php">クリア</a>
    <?php endif; ?>
  </div>

  <details class="filter-details" <?= $hasFilter ? 'open' : '' ?>>
    <summary class="btn btn-secondary filter-toggle">
      絞り込み<?php if ($hasFilter): ?><span class="filter-count"><?= count($filterTypes) + count($filterStatuses) + count($filterTagIds) ?></span><?php endif; ?>
    </summary>

    <div class="filter-panel">
      <div class="filter-group">
        <span class="filter-label">種別</span>
        <div class="tag-chips">
          <label class="tag-chip">
            <input type="checkbox" name="type[]" value="blog" <?= in_array('blog', $filterTypes, true) ? 'checked' : '' ?>>
            <span class="tag-chip-label">ブログ</span>
          </label>
          <label class="tag-chip">
            <input type="checkbox" name="type[]" value="notice" <?= in_array('notice', $filterTypes, true) ? 'checked' : '' ?>>
            <span class="tag-chip-label">お知らせ</span>
          </label>
        </div>
      </div>

      <div class="filter-group">
        <span class="filter-label">公開状態</span>
        <div class="tag-chips">
          <label class="tag-chip">
            <input type="checkbox" name="status[]" value="published" <?= in_array('published', $filterStatuses, true) ? 'checked' : '' ?>>
            <span class="tag-chip-label">公開</span>
          </label>
          <label class="tag-chip">
            <input type="checkbox" name="status[]" value="draft" <?= in_array('draft', $filterStatuses, true) ? 'checked' : '' ?>>
            <span class="tag-chip-label">下書き</span>
          </label>
        </div>
      </div>

      <?php if (count($allTags) > 0): ?>
        <div class="filter-group">
          <span class="filter-label">タグ</span>
          <div class="tag-chips">
            <?php foreach ($allTags as $tag): ?>
              <label class="tag-chip">
                <input type="checkbox" name="tag[]" value="<?= e((string) $tag['id']) ?>" <?= in_array((int) $tag['id'], $filterTagIds, true) ? 'checked' : '' ?>>
                <span class="tag-chip-label"><?= e($tag['name']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="filter-actions">
        <button type="submit" class="btn">絞り込み検索</button>
        <?php if ($search !== '' || $hasFilter): ?>
          <a class="btn btn-secondary" href="index.php">クリア</a>
        <?php endif; ?>
      </div>
    </div>
  </details>
</form>

<?php if ($search !== '' || $hasFilter): ?>
  <p class="search-result-note">
    <?php if ($search !== ''): ?>「<?= e($search) ?>」の<?php endif; ?>絞り込み結果: <?= e((string) $total) ?>件
  </p>
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
          <td class="post-title-cell">
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
          <td class="post-date-cell"><?= str_replace(' ', '<br>', e($post['updated_at'])) ?></td>
          <td>
            <?php $isPublished = $post['status'] === 'published'; ?>
            <div class="post-actions">
              <form method="post" action="toggle_status.php" class="inline-form"
                    onsubmit="return confirm('<?= $isPublished ? 'この投稿を下書きに戻しますか？' : 'この投稿を公開しますか？' ?>');">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string) $post['id']) ?>">
                <input type="hidden" name="return_to" value="list">
                <input type="hidden" name="q" value="<?= e($search) ?>">
                <input type="hidden" name="page" value="<?= e((string) $page) ?>">
                <?= $filterHidden ?>
                <button type="submit" class="btn btn-small <?= $isPublished ? 'btn-secondary' : '' ?>">
                  <?= $isPublished ? '下書きに戻す' : '公開する' ?>
                </button>
              </form>
              <a class="btn btn-small btn-secondary" href="edit.php?id=<?= e((string) $post['id']) ?>">編集</a>
              <a class="btn btn-small btn-danger" href="delete.php?id=<?= e((string) $post['id']) ?>">削除</a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($totalPages > 1): ?>
    <nav class="pagination">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <?php $query = http_build_query(array_merge($baseQuery, ['page' => $p])); ?>
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
