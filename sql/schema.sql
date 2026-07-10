-- ブログCMS スキーマ定義
-- 実行方法: /Applications/XAMPP/xamppfiles/bin/mysql -u root < sql/schema.sql

CREATE DATABASE IF NOT EXISTS blog_cms
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE blog_cms;

-- 管理者テーブル
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 投稿テーブル（ブログ記事・お知らせ共通）
CREATE TABLE IF NOT EXISTS posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(20) NOT NULL DEFAULT 'blog',
  title VARCHAR(255) NOT NULL,
  body LONGTEXT NOT NULL,
  hero_image VARCHAR(255) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  user_id INT UNSIGNED NOT NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_posts_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- 本文中に埋め込む画像（Phase 4 / 方式B：本文には [image:ID] のプレースホルダーを書く）
CREATE TABLE IF NOT EXISTS post_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_post_images_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- タグ（Phase 4）
CREATE TABLE IF NOT EXISTS tags (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 投稿とタグの中間テーブル（多対多、Phase 4）
CREATE TABLE IF NOT EXISTS post_tags (
  post_id INT UNSIGNED NOT NULL,
  tag_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (post_id, tag_id),
  CONSTRAINT fk_post_tags_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_post_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- コメント（Phase 4／管理画面内の簡易ログ。公開の投稿フォームはない）
CREATE TABLE IF NOT EXISTS comments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id INT UNSIGNED NOT NULL,
  author_name VARCHAR(100) NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_comments_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 初期管理者（email: admin@example.com / password: admin1234）
INSERT INTO users (name, email, password)
SELECT '管理者', 'admin@example.com', '$2y$10$INAWN85XcPx3Ubksf3R5beqjjCqgHMSOQMlx2uJiC/ZRsQ0igszq.'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@example.com');

-- 動作確認用サンプル投稿
INSERT INTO posts (type, title, body, status, user_id, published_at)
SELECT 'blog', 'はじめての記事', 'これはサンプルのブログ記事です。', 'published', u.id, NOW()
FROM users u
WHERE u.email = 'admin@example.com'
  AND NOT EXISTS (SELECT 1 FROM posts);

INSERT INTO posts (type, title, body, status, user_id, published_at)
SELECT 'notice', 'メンテナンスのお知らせ', '7月10日にメンテナンスを実施します。', 'published', u.id, NOW()
FROM users u
WHERE u.email = 'admin@example.com'
  AND NOT EXISTS (SELECT 1 FROM posts WHERE type = 'notice');
