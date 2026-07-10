<?php
// 本文中に埋め込む画像のロジック（Phase 4／方式B：本文には [image:ID] のプレースホルダーを書く）

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

class PostImage
{
    // 投稿に紐づく画像を並び順で取得
    public static function forPost(int $postId): array
    {
        $stmt = db()->prepare(
            'SELECT * FROM post_images WHERE post_id = :post_id ORDER BY sort_order, id'
        );
        $stmt->execute([':post_id' => $postId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM post_images WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $image = $stmt->fetch();
        return $image === false ? null : $image;
    }

    // 画像を登録し、新しいIDを返す（本文には [image:ID] と書いてもらう）
    public static function create(int $postId, string $filePath): int
    {
        $stmt = db()->prepare(
            'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM post_images WHERE post_id = :post_id'
        );
        $stmt->execute([':post_id' => $postId]);
        $nextOrder = (int) $stmt->fetchColumn();

        $stmt = db()->prepare(
            'INSERT INTO post_images (post_id, file_path, sort_order)
             VALUES (:post_id, :file_path, :sort_order)'
        );
        $stmt->execute([
            ':post_id'    => $postId,
            ':file_path'  => $filePath,
            ':sort_order' => $nextOrder,
        ]);

        return (int) db()->lastInsertId();
    }

    // 削除して、削除したファイルの相対パスを返す（呼び出し側でファイル本体を削除する）
    public static function delete(int $id): ?string
    {
        $image = self::find($id);
        if ($image === null) {
            return null;
        }

        db()->prepare('DELETE FROM post_images WHERE id = :id')->execute([':id' => $id]);

        return $image['file_path'];
    }

    // 本文中の [image:ID] プレースホルダーを <img> タグに置換した表示用HTMLを組み立てる
    // （本文自体はプレーンテキストとして扱うため、まずエスケープしてから置換する）
    public static function renderBody(string $body, array $images): string
    {
        $pathById = [];
        foreach ($images as $image) {
            $pathById[(int) $image['id']] = $image['file_path'];
        }

        $html = nl2br(e($body));

        return preg_replace_callback(
            '/\[image:(\d+)\]/',
            function (array $matches) use ($pathById): string {
                $id = (int) $matches[1];
                if (!isset($pathById[$id])) {
                    return '';
                }
                return '<img src="' . e($pathById[$id]) . '" alt="" class="body-image">';
            },
            $html
        );
    }
}
