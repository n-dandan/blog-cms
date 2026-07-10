<?php
// コメントのロジック（Phase 4／管理画面内の簡易ログ。公開の投稿フォームは持たない）

require_once __DIR__ . '/db.php';

class Comment
{
    public static function forPost(int $postId): array
    {
        $stmt = db()->prepare(
            'SELECT * FROM comments WHERE post_id = :post_id ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([':post_id' => $postId]);
        return $stmt->fetchAll();
    }

    // 入力バリデーション。エラーメッセージの配列を返す（空なら合格）
    public static function validate(array $input): array
    {
        $errors = [];

        $authorName = trim($input['author_name'] ?? '');
        if ($authorName === '') {
            $errors[] = '投稿者名は必須です。';
        } elseif (mb_strlen($authorName) > 100) {
            $errors[] = '投稿者名は100文字以内で入力してください。';
        }

        if (trim($input['body'] ?? '') === '') {
            $errors[] = 'コメント本文は必須です。';
        }

        return $errors;
    }

    public static function create(int $postId, string $authorName, string $body): int
    {
        $stmt = db()->prepare(
            'INSERT INTO comments (post_id, author_name, body) VALUES (:post_id, :author_name, :body)'
        );
        $stmt->execute([
            ':post_id'     => $postId,
            ':author_name' => trim($authorName),
            ':body'        => trim($body),
        ]);
        return (int) db()->lastInsertId();
    }

    public static function delete(int $id): void
    {
        db()->prepare('DELETE FROM comments WHERE id = :id')->execute([':id' => $id]);
    }
}
