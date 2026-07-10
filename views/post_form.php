<?php
/**
 * 投稿編集フォームのパーシャル（edit.php から include。新規作成も下書き経由で同画面）
 * 呼び出し側で用意する変数:
 *   @var int     $id             投稿ID
 *   @var array   $input          フォームの初期値 ['type', 'title', 'body', 'status']
 *   @var array   $errors         バリデーションエラーメッセージの配列
 *   @var string  $submitLabel    送信ボタンのラベル
 *   @var array   $allTags        選択肢となる全タグ（['id'=>int, 'name'=>string] の配列）
 *   @var array   $selectedTagIds この投稿に付いているタグIDの配列
 *   @var ?string $heroImage      現在のヒーロー画像パス（未設定は null）
 *   @var array   $existingImages 本文中画像の一覧（post_imagesの行の配列）
 */

// ライブプレビュー用: 既存の本文画像の ID => URL マップ
$imageMap = [];
foreach ($existingImages as $image) {
    $imageMap[(string) $image['id']] = $image['file_path'];
}
?>
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
  <form method="post" enctype="multipart/form-data" id="post-form"
        data-post-id="<?= e((string) $id) ?>"
        data-upload-url="upload_body_image.php"
        data-delete-hero-url="delete_hero_image.php"
        data-image-map="<?= e(json_encode($imageMap, JSON_UNESCAPED_SLASHES)) ?>">
    <?= csrf_field() ?>

    <div class="form-group">
      <label for="type">種別</label>
      <select id="type" name="type">
        <option value="blog" <?= ($input['type'] ?? 'blog') === 'blog' ? 'selected' : '' ?>>ブログ記事</option>
        <option value="notice" <?= ($input['type'] ?? '') === 'notice' ? 'selected' : '' ?>>お知らせ</option>
      </select>
    </div>

    <div class="form-group">
      <label for="title">タイトル</label>
      <input type="text" id="title" name="title" value="<?= e($input['title'] ?? '') ?>" maxlength="255">
    </div>

    <div class="form-group">
      <label for="hero_image">ヒーロー画像（一覧・詳細の先頭に表示、16:9推奨）</label>
      <div class="dropzone" id="hero-dropzone">
        <img id="hero-preview" src="<?= $heroImage !== null ? e($heroImage) : '' ?>"
             alt="ヒーロー画像プレビュー" <?= $heroImage === null ? 'hidden' : '' ?>>
        <p class="dropzone-text">ここに画像をドロップ（またはクリックして選択）</p>
      </div>
      <input type="file" id="hero_image" name="hero_image" accept="image/*" class="dropzone-input">
      <div class="hero-actions">
        <button type="button" id="hero-delete-btn" class="btn btn-small btn-danger"
                <?= $heroImage === null ? 'hidden' : '' ?>>画像を削除</button>
        <p class="upload-status" id="hero-status" hidden></p>
      </div>
      <noscript>
        <label class="checkbox-label">
          <input type="checkbox" name="remove_hero_image" value="1"> 現在のヒーロー画像を削除する
        </label>
      </noscript>
    </div>

    <div class="form-group">
      <label>タグ（複数選択可）</label>
      <?php if (count($allTags) === 0): ?>
        <p class="form-hint">タグがまだありません。<a href="tags.php">タグ管理</a>から追加してください。</p>
      <?php else: ?>
        <div class="tag-chips">
          <?php foreach ($allTags as $tag): ?>
            <label class="tag-chip">
              <input type="checkbox" name="tags[]" value="<?= e((string) $tag['id']) ?>"
                     <?= in_array($tag['id'], $selectedTagIds, true) ? 'checked' : '' ?>>
              <span class="tag-chip-label"><?= e($tag['name']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <p class="form-hint"><a href="tags.php">+ 新しいタグを追加</a></p>
    </div>

    <div class="form-group">
      <label for="body">本文</label>
      <div class="editor-split">
        <div class="editor-pane">
          <textarea id="body" name="body"><?= e($input['body'] ?? '') ?></textarea>
          <div class="dropzone dropzone-compact" id="body-dropzone">
            <p class="dropzone-text">本文に入れる画像をここにドロップ（またはクリックして選択）<br>
              アップロードと同時に <code>[image:ID]</code> がカーソル位置に挿入されます</p>
            <p class="upload-status" id="upload-status" hidden></p>
          </div>
        </div>
        <div class="editor-pane">
          <div class="preview-label">プレビュー</div>
          <div class="body-preview detail-body" id="body-preview"></div>
        </div>
      </div>
    </div>

    <?php if (count($existingImages) > 0): ?>
      <div class="form-group">
        <label>登録済みの本文用画像</label>
        <div class="image-gallery">
          <?php foreach ($existingImages as $image): ?>
            <div class="image-gallery-item">
              <img src="<?= e($image['file_path']) ?>" alt="">
              <code>[image:<?= e((string) $image['id']) ?>]</code>
              <label class="checkbox-label">
                <input type="checkbox" name="delete_images[]" value="<?= e((string) $image['id']) ?>"> 削除
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <noscript>
      <div class="form-group">
        <label for="body_images">本文用画像を追加（複数選択可・保存時にアップロード）</label>
        <input type="file" id="body_images" name="body_images[]" accept="image/*" multiple>
      </div>
    </noscript>

    <p class="form-hint">
      公開状態（公開する・下書きに戻す）は投稿一覧・投稿詳細のボタンから変更してください。
    </p>

    <div class="form-actions">
      <button type="submit" class="btn"><?= e($submitLabel) ?></button>
      <a class="btn btn-secondary" href="index.php" id="cancel-link">キャンセル</a>
    </div>
  </form>
</div>

<script src="js/editor.js"></script>
