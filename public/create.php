<?php
// 投稿の新規作成（Create）
// 空の下書きを即作成して編集画面へ遷移する。
// エディタが常に post_id を持ち、画像のドロップ即時アップロードができるようにするため。
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Post.php';

start_session();
require_login();

$id = Post::createOrReuseDraft(current_user_id());
redirect('edit.php?id=' . $id);
