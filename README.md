# ブログCMS

素のPHP + MySQL で構築した自作CMS。プログラミングスクール課題（PHPによるDBのCRUD実装）を目的とし、将来的にAI旅行プランナーアプリの「お知らせ機能」バックエンドとして転用できる設計になっています。

詳細な仕様は [blog-cms-spec.md](blog-cms-spec.md) を参照してください。

## 実装済みの機能

- **Phase 1 — コアCRUD**
  - 投稿の一覧・詳細表示（Read）、新規作成（Create）、編集（Update）、削除（Delete・確認画面つき）
  - 入力バリデーション
  - PDOプリペアドステートメントによるSQLインジェクション対策
- **Phase 2 — 認証とセキュリティ**
  - 管理者ログイン / ログアウト（セッションベース認証）
  - パスワードハッシュ化（`password_hash` / `password_verify`）
  - 未ログイン時の管理画面アクセス制限
  - CSRF対策（トークン方式）
  - XSS対策（出力時の `htmlspecialchars` エスケープ）
- **Phase 3 — JSON API**
  - 読み取り専用（GET限定）の公開APIエンドポイント
  - `type` / `tag` / `limit` によるフィルタ
  - `published` の投稿のみ返す（下書きは絶対に返さない）
- **Phase 4 — 発展機能**
  - ヒーロー画像（アイキャッチ）: 一覧サムネイル・詳細先頭・APIの `hero_image_url` に表示
  - 本文中の画像表示（方式B）: `post_images` テーブル + 本文の `[image:ID]` プレースホルダーを表示時に `<img>` へ置換
  - タグ（多対多）: カンマ区切りで入力、一覧・詳細・APIに表示、APIの `tag` パラメータで絞り込み
  - コメント: 投稿詳細画面での簡易コメントログ（管理画面内のみ・公開フォームなし）
  - 検索（タイトル・本文の部分一致）とページネーション（10件/ページ）
- **エディタ改善**
  - ヒーロー画像・本文用画像の**ドラッグ&ドロップ入稿**（クリックでのファイル選択も可）
  - 本文用画像はドロップと同時にAJAXアップロードされ、`[image:ID]` がカーソル位置に自動挿入
  - 本文の**ライブプレビュー**（`[image:ID]` を実画像で表示しながら編集できる）
  - 「新規作成」は空の下書きを即作成して編集画面へ遷移（空下書きは再利用され量産されない）

## 動作環境

| 項目 | 内容 |
|------|------|
| PHP | 8.2（XAMPP） |
| DB | MySQL / MariaDB 10.4（XAMPP） |
| Webサーバー | Apache（XAMPP、`.htaccess` 使用） |

## セットアップ

1. XAMPPの Apache と MySQL を起動する。

2. データベースを作成する（初期管理者とサンプル投稿も投入されます）:

   ```bash
   cd /Applications/XAMPP/xamppfiles/htdocs/0710_php_study
   /Applications/XAMPP/xamppfiles/bin/mysql -u root < sql/schema.sql
   ```

   > 旧バージョン（Phase 3まで）のDBを使っている場合は、追加分のマイグレーションを適用してください:
   > `/Applications/XAMPP/xamppfiles/bin/mysql -u root < sql/migrate_phase4.sql`

3. DB接続情報は [config.php](config.php) で設定する（XAMPP標準の `root` / パスワードなしを初期値としています）。

4. 画像アップロード先にWebサーバーからの書き込み権限を付与する（XAMPPのApacheは `daemon` ユーザーで動作するため）:

   ```bash
   chmod 777 public/uploads/posts
   ```

5. ブラウザで管理画面を開く:

   http://localhost/0710_php_study/public/login.php

## 初期ログインアカウント

| 項目 | 値 |
|------|-----|
| メールアドレス | `admin@example.com` |
| パスワード | `admin1234` |

> 管理者を追加する場合は、`password_hash()` で生成したハッシュを `users` テーブルに INSERT してください:
> ```bash
> /Applications/XAMPP/xamppfiles/bin/php -r "echo password_hash('新しいパスワード', PASSWORD_DEFAULT), PHP_EOL;"
> ```

## 管理画面

| URL | 画面 |
|-----|------|
| `public/login.php` | ログイン |
| `public/index.php` | 投稿一覧（検索・ページネーション） |
| `public/show.php?id={id}` | 投稿詳細（コメント管理つき） |
| `public/create.php` | 新規作成（空の下書きを作成して編集画面へ） |
| `public/edit.php?id={id}` | 編集フォーム（D&D入稿・ライブプレビュー） |
| `public/delete.php?id={id}` | 削除確認 |
| `public/logout.php` | ログアウト |
| `public/upload_body_image.php` | 本文用画像のAJAXアップロード（エディタ内部用・要ログイン+CSRF） |

## JSON API（読み取り専用）

ベースURL: `http://localhost/0710_php_study/public`

### 一覧の取得

```
GET /api/posts
GET /api/posts?type=notice&limit=10
GET /api/posts?tag=旅行
```

| パラメータ | 必須 | 説明 |
|-----------|------|------|
| `type` | 任意 | `blog` / `notice`。省略時は全種別 |
| `tag` | 任意 | タグ名で絞り込み |
| `status` | 任意 | `published` のみ指定可（既定値）。下書きは返さない |
| `limit` | 任意 | 取得件数（1〜100、既定 20） |

レスポンス例:

```json
{
    "data": [
        {
            "id": 2,
            "type": "notice",
            "title": "メンテナンスのお知らせ",
            "body": "7月10日にメンテナンスを実施します。\n[image:1]\n詳細は追ってお知らせします。",
            "hero_image_url": "http://localhost/0710_php_study/public/uploads/posts/2/hero_xxxx.jpg",
            "tags": ["お知らせ", "重要"],
            "images": [
                { "id": 1, "url": "http://localhost/0710_php_study/public/uploads/posts/2/body_xxxx.png" }
            ],
            "published_at": "2026-07-05 22:51:13"
        }
    ]
}
```

> `body` 内の `[image:1]` は本文中画像のプレースホルダーです。クライアント側では `images` 配列の `id` と突き合わせて `<img>` に置換して表示してください（画像がない投稿では `hero_image_url` は `null`、`images` は空配列）。

### 単一投稿の取得

```
GET /api/posts/{id}
```

公開済み（`published`）の投稿のみ返します。下書き・存在しないIDはいずれも `404` です。GET以外のメソッドは `405` を返します。

Reactアプリからの利用例:

```js
const res = await fetch('http://localhost/0710_php_study/public/api/posts?type=notice');
const { data } = await res.json();
```

## ディレクトリ構成

```
0710_php_study/
├── config.php                 # DB接続情報（.htaccessで直接アクセス禁止）
├── public/                    # 公開ディレクトリ
│   ├── index.php              # 投稿一覧（検索・ページネーション）
│   ├── show.php               # 投稿詳細（コメント管理）
│   ├── create.php             # 新規作成（空下書きを作成して編集画面へ）
│   ├── edit.php               # 編集（D&D入稿・ライブプレビュー）
│   ├── delete.php             # 削除確認・実行
│   ├── login.php              # ログイン
│   ├── logout.php             # ログアウト
│   ├── upload_body_image.php  # 本文用画像のAJAXアップロード（エディタ用）
│   ├── css/style.css
│   ├── js/editor.js           # ドロップ入稿 + 本文ライブプレビュー
│   ├── api/
│   │   └── posts.php          # 公開JSON API（読み取り専用）
│   └── uploads/posts/{id}/    # 画像保存先（.htaccessでPHP実行禁止）
├── src/
│   ├── db.php                 # PDO接続
│   ├── auth.php               # 認証・セッション
│   ├── csrf.php               # CSRFトークン
│   ├── helpers.php            # 共通ヘルパー（e() エスケープ等）
│   ├── upload.php             # 画像アップロード（検証・保存・削除）
│   ├── Post.php               # 投稿のCRUDロジック
│   ├── Tag.php                # タグ（多対多）
│   ├── PostImage.php          # 本文中画像（[image:ID] 置換）
│   └── Comment.php            # コメント
├── views/                     # 画面テンプレート
│   ├── header.php
│   ├── footer.php
│   └── post_form.php          # 投稿編集フォーム
├── sql/
│   ├── schema.sql             # テーブル定義 + 初期データ
│   └── migrate_phase4.sql     # 旧DB向け追加マイグレーション
├── docs/plans/                # 実装プランのログ
└── blog-cms-spec.md           # 仕様書
```

> 本来は `public/` をドキュメントルートにする想定ですが、XAMPPの `htdocs` 配下で動かすため、ルートの [.htaccess](.htaccess) で `config.php`・`src/`・`views/`・`sql/` への直接アクセスを禁止しています。

### 公開／非公開ディレクトリの方針

このプロジェクトはファイルを「ブラウザに配信するもの（公開）」と「サーバー内部だけで使うもの（非公開）」に分けています。

- **`public/`（公開）** … ブラウザが直接URLでアクセスするファイル。各PHPページ（`index.php`・`edit.php` など）に加え、`css/style.css`・`js/editor.js`・`uploads/`（画像）を置く。CSSやJSは `<link>` / `<script>` からブラウザが独立したリクエストで取得するため、Webサーバーから配信可能な場所に置く必要がある。
- **`src/`・`views/`・`config.php`・`sql/`（非公開）** … サーバー側のPHPが `require` で読み込むだけで、ブラウザが直接触れる必要のないもの。

理想は「`public/` をドキュメントルートにし、非公開ファイルはその外側に置く」構成で、そうすれば非公開ファイルはURL上そもそも存在せずアクセス不能になります（[blog-cms-spec.md](blog-cms-spec.md) 9章の想定）。今回はプロジェクト全体を `htdocs/0710_php_study/` 配下に置いている都合上、非公開ファイルも理屈上はURLで届いてしまうため、ルートの [.htaccess](.htaccess) でまとめてブロックし、擬似的に「`public/` の中だけが公開されている」状態を作っています。

**なぜ `config.php` だけでなく `src/`・`views/`・`sql/` も禁止するのか**

- **`config.php` / `sql/`** … DB接続情報やスキーマそのもので、外部に見えると危険な**秘密情報**。これは第一の理由。
- **`src/`** … DBアクセスやCRUDのロジック。単体で直接実行されると予期しない挙動やエラーを招く。
- **`views/`** … 実は**秘密情報は書かれていない**（HTMLテンプレートのみで、DB接続情報やSQLには触れない。値は呼び出し元のPHPから変数で渡される）。それでも禁止するのは次の予防的な理由による:
  1. `views/post_form.php` などを単体で直接開くと、`$allTags` 等の変数が未定義のまま実行され、**PHPの警告（ファイルパスや変数名）が漏れて**攻撃の手がかりになる。
  2. フォーム項目やCSRFトークンの埋め込み方など、**内部構造を外部にさらす利点がない**。
  3. ファイルごとに安全/危険を判断するより、**「`public/` 以外はまとめて非公開」というホワイトリスト方式**の方が安全で管理も単純。

つまり `views/` のブロックは「秘密を守る」というより、**未完成状態での直接実行の防止と内部構造の隠蔽**が目的です。本当に守るべき秘密情報（DB接続情報）は [config.php](config.php) にあり、これは `.htaccess` で個別に禁止したうえ、[src/db.php](src/db.php) 経由でしか読み込まれません。

## セキュリティ対策の実装箇所

| 対策 | 実装箇所 |
|------|----------|
| SQLインジェクション | 全SQLをPDOプリペアドステートメントで実行（[src/Post.php](src/Post.php) ほか） |
| パスワード | `password_hash()` / `password_verify()`（[src/auth.php](src/auth.php)） |
| CSRF | セッショントークンをフォームに埋め込み `hash_equals()` で検証（[src/csrf.php](src/csrf.php)） |
| XSS | 出力時に `e()`（`htmlspecialchars`）でエスケープ（[src/helpers.php](src/helpers.php)） |
| セッション固定化 | ログイン成功時に `session_regenerate_id(true)` |
| アクセス制御 | 管理画面は `require_login()` で未ログイン時リダイレクト。APIは公開済み投稿のみ返す |
| 画像アップロード | 実バイナリのMIME判定（拡張子・Content-Typeは信用しない）、5MB上限、保存名はランダム生成、`uploads/` でのPHP実行禁止（[public/uploads/.htaccess](public/uploads/.htaccess)） |
