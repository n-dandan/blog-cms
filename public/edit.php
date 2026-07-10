<?php
// 投稿の編集・更新（Update）
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/Post.php';
require_once __DIR__ . '/../src/Tag.php';
require_once __DIR__ . '/../src/PostImage.php';
require_once __DIR__ . '/../src/upload.php';

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

$errors = [];
$input = [
    'type'   => $post['type'],
    'title'  => $post['title'],
    'body'   => $post['body'],
    'status' => $post['status'],
];
$allTags = Tag::all();
$selectedTagIds = Tag::idsForPost($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $input = [
        'type'   => $_POST['type'] ?? '',
        'title'  => $_POST['title'] ?? '',
        'body'   => $_POST['body'] ?? '',
        // 公開状態はこのフォームでは変更しない（一覧・詳細の専用ボタンでのみ切り替える）
        'status' => $post['status'],
    ];

    // 送信されたタグIDは、実在するタグのIDのみを許可する（未知のIDによるFK違反を防ぐ）
    $validTagIds = array_column($allTags, 'id');
    $submittedTagIds = array_map('intval', (array) ($_POST['tags'] ?? []));
    $selectedTagIds = array_values(array_intersect($submittedTagIds, $validTagIds));

    $errors = Post::validate($input);

    $heroFile = $_FILES['hero_image'] ?? ['error' => UPLOAD_ERR_NO_FILE];
    $errors = array_merge($errors, validate_upload_image($heroFile));

    $bodyFiles = isset($_FILES['body_images'])
        ? normalize_files_array($_FILES['body_images'])
        : [];
    foreach ($bodyFiles as $bodyFile) {
        $errors = array_merge($errors, validate_upload_image($bodyFile));
    }

    if (count($errors) === 0) {
        Post::update($id, $input);

        // ヒーロー画像：新規アップロードは差し替え、削除チェックは解除
        $newHeroPath = store_upload_image($heroFile, $id, 'hero');
        if ($newHeroPath !== null) {
            delete_upload_image($post['hero_image']);
            Post::setHeroImage($id, $newHeroPath);
        } elseif (!empty($_POST['remove_hero_image'])) {
            delete_upload_image($post['hero_image']);
            Post::setHeroImage($id, null);
        }

        // 本文用画像：チェックされたものを削除
        foreach ((array) ($_POST['delete_images'] ?? []) as $imageId) {
            $image = PostImage::find((int) $imageId);
            if ($image !== null && (int) $image['post_id'] === $id) {
                delete_upload_image(PostImage::delete((int) $imageId));
            }
        }

        // 本文用画像：新規追加
        foreach ($bodyFiles as $bodyFile) {
            $path = store_upload_image($bodyFile, $id, 'body');
            if ($path !== null) {
                PostImage::create($id, $path);
            }
        }

        Tag::syncForPostByIds($id, $selectedTagIds);

        $_SESSION['flash'] = '投稿を更新しました。';
        redirect('show.php?id=' . $id);
    }
}

$heroImage = $post['hero_image'];
$existingImages = PostImage::forPost($id);

$pageTitle = '投稿の編集';
$submitLabel = '変更を保存';
require __DIR__ . '/../views/header.php';
?>

<div class="page-head">
  <h1>投稿の編集（ID: <?= e((string) $id) ?>）</h1>
</div>

<?php require __DIR__ . '/../views/post_form.php'; ?>

<?php require __DIR__ . '/../views/footer.php'; ?>
