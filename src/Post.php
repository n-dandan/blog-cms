<?php
// 投稿のCRUDロジック（Phase 1）
// 全SQLはPDOのプリペアドステートメントで実行する。

require_once __DIR__ . '/db.php';

class Post
{
    public const TYPES    = ['blog', 'notice'];
    public const STATUSES = ['draft', 'published'];

    public const PER_PAGE = 10;

    // 管理画面一覧用：検索・絞り込み・ページネーション付き取得（下書きも含む、新しい順）
    // $filters: ['types' => [...], 'statuses' => [...], 'tagIds' => [...]]（各グループ内はOR、グループ間はAND）
    // 戻り値: ['posts' => [...], 'total' => int, 'totalPages' => int, 'page' => int]
    public static function paginate(string $search, int $page, array $filters = []): array
    {
        $perPage = self::PER_PAGE;

        // エミュレーション無効時は同名プレースホルダを再利用できないため個別名にする
        $conditions = [];
        $params = [];

        if ($search !== '') {
            $conditions[] = '(p.title LIKE :search_title OR p.body LIKE :search_body)';
            $params[':search_title'] = '%' . $search . '%';
            $params[':search_body']  = '%' . $search . '%';
        }

        // 種別で絞り込み（不正値は許可リストで除外）
        $types = array_values(array_intersect($filters['types'] ?? [], self::TYPES));
        if (count($types) > 0) {
            $ph = [];
            foreach ($types as $i => $t) {
                $ph[] = ":type{$i}";
                $params[":type{$i}"] = $t;
            }
            $conditions[] = 'p.type IN (' . implode(',', $ph) . ')';
        }

        // 公開状態で絞り込み
        $statuses = array_values(array_intersect($filters['statuses'] ?? [], self::STATUSES));
        if (count($statuses) > 0) {
            $ph = [];
            foreach ($statuses as $i => $s) {
                $ph[] = ":status{$i}";
                $params[":status{$i}"] = $s;
            }
            $conditions[] = 'p.status IN (' . implode(',', $ph) . ')';
        }

        // タグで絞り込み（選択したタグのいずれかを持つ投稿）
        $tagIds = array_values(array_filter(
            array_map('intval', $filters['tagIds'] ?? []),
            fn ($id) => $id > 0
        ));
        if (count($tagIds) > 0) {
            $ph = [];
            foreach (array_unique($tagIds) as $i => $tid) {
                $ph[] = ":tag{$i}";
                $params[":tag{$i}"] = $tid;
            }
            $conditions[] = 'p.id IN (SELECT pt.post_id FROM post_tags pt WHERE pt.tag_id IN (' . implode(',', $ph) . '))';
        }

        $where = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = db()->prepare("SELECT COUNT(*) FROM posts p {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $stmt = db()->prepare(
            "SELECT p.*, u.name AS user_name
             FROM posts p
             JOIN users u ON u.id = p.user_id
             {$where}
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'posts'      => $stmt->fetchAll(),
            'total'      => $total,
            'totalPages' => $totalPages,
            'page'       => $page,
        ];
    }

    // 1件取得。見つからなければ null
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT p.*, u.name AS user_name
             FROM posts p
             JOIN users u ON u.id = p.user_id
             WHERE p.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $post = $stmt->fetch();
        return $post === false ? null : $post;
    }

    // 入力バリデーション。エラーメッセージの配列を返す（空なら合格）
    public static function validate(array $input): array
    {
        $errors = [];

        $title = trim($input['title'] ?? '');
        if ($title === '') {
            $errors[] = 'タイトルは必須です。';
        } elseif (mb_strlen($title) > 255) {
            $errors[] = 'タイトルは255文字以内で入力してください。';
        }

        if (trim($input['body'] ?? '') === '') {
            $errors[] = '本文は必須です。';
        }

        if (!in_array($input['type'] ?? '', self::TYPES, true)) {
            $errors[] = '種別が不正です。';
        }

        if (!in_array($input['status'] ?? '', self::STATUSES, true)) {
            $errors[] = '公開状態が不正です。';
        }

        return $errors;
    }

    // 新規作成（Create）。作成した投稿のIDを返す
    public static function create(array $input, int $userId): int
    {
        // 公開日時は created_at と同じ時計（DBサーバーの NOW()）で揃える
        $publishedAtExpr = $input['status'] === 'published' ? 'NOW()' : 'NULL';

        $stmt = db()->prepare(
            "INSERT INTO posts (type, title, body, status, user_id, published_at)
             VALUES (:type, :title, :body, :status, :user_id, {$publishedAtExpr})"
        );
        $stmt->execute([
            ':type'    => $input['type'],
            ':title'   => trim($input['title']),
            ':body'    => $input['body'],
            ':status'  => $input['status'],
            ':user_id' => $userId,
        ]);

        return (int) db()->lastInsertId();
    }

    // 空の下書きを作成して、そのIDを返す（「新規作成」→ 即編集画面フロー用）
    // 同ユーザーの空下書きが既にあれば再利用し、連打による下書きの量産を防ぐ
    public static function createOrReuseDraft(int $userId): int
    {
        $stmt = db()->prepare(
            "SELECT id FROM posts
             WHERE user_id = :user_id AND status = 'draft' AND title = '' AND body = ''
             ORDER BY id LIMIT 1"
        );
        $stmt->execute([':user_id' => $userId]);
        $existingId = $stmt->fetchColumn();

        if ($existingId !== false) {
            return (int) $existingId;
        }

        $stmt = db()->prepare(
            "INSERT INTO posts (type, title, body, status, user_id, published_at)
             VALUES ('blog', '', '', 'draft', :user_id, NULL)"
        );
        $stmt->execute([':user_id' => $userId]);

        return (int) db()->lastInsertId();
    }

    // 更新（Update）
    public static function update(int $id, array $input): void
    {
        $current = self::find($id);
        if ($current === null) {
            return;
        }

        // はじめて公開になったタイミングで公開日時をセット
        // （時計は created_at と同じ DBサーバーの NOW() で揃える）
        $params = [
            ':type'   => $input['type'],
            ':title'  => trim($input['title']),
            ':body'   => $input['body'],
            ':status' => $input['status'],
            ':id'     => $id,
        ];

        if ($input['status'] === 'published' && $current['published_at'] === null) {
            $publishedAtExpr = 'NOW()';
        } else {
            $publishedAtExpr = ':published_at';
            $params[':published_at'] = $current['published_at'];
        }

        $stmt = db()->prepare(
            "UPDATE posts
             SET type = :type, title = :title, body = :body,
                 status = :status, published_at = {$publishedAtExpr}
             WHERE id = :id"
        );
        $stmt->execute($params);
    }

    // 削除（Delete）
    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM posts WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    // 公開状態だけを切り替える（一覧・詳細画面のボタンから使用）。
    // 公開日時ははじめて公開になったときだけ設定し、以後は下書きに戻しても保持する。
    // 戻り値: 切り替え後のstatus。投稿が存在しなければ null
    public static function toggleStatus(int $id): ?string
    {
        $current = self::find($id);
        if ($current === null) {
            return null;
        }

        $newStatus = $current['status'] === 'published' ? 'draft' : 'published';

        if ($newStatus === 'published' && $current['published_at'] === null) {
            $stmt = db()->prepare(
                "UPDATE posts SET status = :status, published_at = NOW() WHERE id = :id"
            );
        } else {
            $stmt = db()->prepare('UPDATE posts SET status = :status WHERE id = :id');
        }
        $stmt->execute([':status' => $newStatus, ':id' => $id]);

        return $newStatus;
    }

    // ヒーロー画像（アイキャッチ）のパスを更新（null で解除）
    public static function setHeroImage(int $id, ?string $path): void
    {
        $stmt = db()->prepare('UPDATE posts SET hero_image = :hero_image WHERE id = :id');
        $stmt->execute([':hero_image' => $path, ':id' => $id]);
    }

    // 公開API用：published のみ取得（Phase 3）。tag指定時はタグでも絞り込む（Phase 4）
    public static function published(?string $type, ?string $tag, int $limit): array
    {
        $sql = 'SELECT p.id, p.type, p.title, p.body, p.hero_image, p.published_at
                FROM posts p';
        $params = [':status' => 'published'];

        if ($tag !== null) {
            $sql .= ' JOIN post_tags pt ON pt.post_id = p.id
                      JOIN tags t ON t.id = pt.tag_id AND t.name = :tag';
            $params[':tag'] = $tag;
        }

        $sql .= ' WHERE p.status = :status';

        if ($type !== null) {
            $sql .= ' AND p.type = :type';
            $params[':type'] = $type;
        }

        $sql .= ' ORDER BY p.published_at DESC, p.id DESC LIMIT :limit';

        $stmt = db()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // 公開API用：published の投稿を1件取得
    public static function findPublished(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT id, type, title, body, hero_image, published_at
             FROM posts
             WHERE id = :id AND status = :status'
        );
        $stmt->execute([':id' => $id, ':status' => 'published']);
        $post = $stmt->fetch();
        return $post === false ? null : $post;
    }
}
