<?php
// タグのロジック（Phase 4／投稿と多対多）
// タグ自体はタグ管理画面（tags.php）で事前に作成し、投稿編集画面ではその中から選択する。

require_once __DIR__ . '/db.php';

class Tag
{
    // 全タグを名前順で取得（[{id, name}, ...]）
    public static function all(): array
    {
        return db()->query('SELECT id, name FROM tags ORDER BY name')->fetchAll();
    }

    // 投稿数つきで全タグを取得（タグ管理画面用）
    public static function withPostCounts(): array
    {
        return db()->query(
            'SELECT t.id, t.name, COUNT(pt.post_id) AS post_count
             FROM tags t
             LEFT JOIN post_tags pt ON pt.tag_id = t.id
             GROUP BY t.id, t.name
             ORDER BY t.name'
        )->fetchAll();
    }

    // 投稿に紐づくタグ名を取得（一覧・詳細・APIの表示用）
    public static function forPost(int $postId): array
    {
        $stmt = db()->prepare(
            'SELECT t.name FROM tags t
             JOIN post_tags pt ON pt.tag_id = t.id
             WHERE pt.post_id = :post_id
             ORDER BY t.name'
        );
        $stmt->execute([':post_id' => $postId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // 投稿に紐づくタグIDを取得（編集画面のチェックボックス初期値用）
    public static function idsForPost(int $postId): array
    {
        $stmt = db()->prepare('SELECT tag_id FROM post_tags WHERE post_id = :post_id');
        $stmt->execute([':post_id' => $postId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    // 複数投稿分のタグをまとめて取得（一覧画面用）。戻り値: [post_id => [タグ名, ...]]
    public static function forPosts(array $postIds): array
    {
        if (count($postIds) === 0) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = db()->prepare(
            "SELECT pt.post_id, t.name FROM post_tags pt
             JOIN tags t ON t.id = pt.tag_id
             WHERE pt.post_id IN ($placeholders)
             ORDER BY t.name"
        );
        $stmt->execute(array_values($postIds));

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['post_id']][] = $row['name'];
        }
        return $result;
    }

    // 新しいタグを作成する。同名のタグが既にあれば作成せず null を返す
    public static function create(string $name): ?int
    {
        $name = mb_substr(trim($name), 0, 50);

        $stmt = db()->prepare('SELECT id FROM tags WHERE name = :name');
        $stmt->execute([':name' => $name]);
        if ($stmt->fetchColumn() !== false) {
            return null;
        }

        $stmt = db()->prepare('INSERT INTO tags (name) VALUES (:name)');
        $stmt->execute([':name' => $name]);

        return (int) db()->lastInsertId();
    }

    // タグを削除する（post_tagsへの紐付けはON DELETE CASCADEで自動的に消える）
    public static function delete(int $id): void
    {
        db()->prepare('DELETE FROM tags WHERE id = :id')->execute([':id' => $id]);
    }

    // 投稿のタグを指定したタグID集合に同期する（既存タグのみを対象とし、新規作成はしない）
    public static function syncForPostByIds(int $postId, array $tagIds): void
    {
        $pdo = db();

        $pdo->prepare('DELETE FROM post_tags WHERE post_id = :post_id')
            ->execute([':post_id' => $postId]);

        $insert = $pdo->prepare('INSERT INTO post_tags (post_id, tag_id) VALUES (:post_id, :tag_id)');
        foreach (array_unique($tagIds) as $tagId) {
            $insert->execute([':post_id' => $postId, ':tag_id' => $tagId]);
        }
    }
}
