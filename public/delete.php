<?php
// 投稿の削除（Delete、確認画面つき）
// GET: 削除確認画面を表示 / POST: 削除を実行
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/Post.php';
require_once __DIR__ . '/../src/upload.php';

start_session();
require_login();

// 削除実行（POSTのみ受け付ける）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0 && Post::find($id) !== null) {
        // 画像・タグ・コメントのレコードは外部キーの ON DELETE CASCADE で消える。
        // 画像ファイル本体はDBでは消えないため、ディレクトリごと削除する。
        Post::delete($id);
        delete_post_upload_dir($id);
        $_SESSION['flash'] = '投稿を削除しました。';
    }
    redirect('index.php');
}

// 削除確認画面
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

$pageTitle = '削除の確認';
require __DIR__ . '/../views/header.php';
?>

<div class="page-head">
  <h1>削除の確認</h1>
</div>

<div class="detail-card">
  <p>次の投稿を削除します。よろしいですか？　<strong>この操作は取り消せません。</strong></p>
  <h2><?= e($post['title']) ?></h2>
  <div class="detail-meta">
    <span class="badge badge-<?= e($post['type']) ?>">
      <?= $post['type'] === 'notice' ? 'お知らせ' : 'ブログ' ?>
    </span>
    ｜ 作成日時: <?= e($post['created_at']) ?>
  </div>

  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e((string) $post['id']) ?>">
    <div class="form-actions">
      <button type="submit" class="btn btn-danger">削除する</button>
      <a class="btn btn-secondary" href="index.php">キャンセル</a>
    </div>
  </form>
</div>

<?php require __DIR__ . '/../views/footer.php'; ?>
