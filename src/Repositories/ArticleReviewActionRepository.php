<?php

declare(strict_types=1);

namespace Naf\CMS\Repositories;

use Naf\CMS\Models\ArticleReviewAction;
use Naf\ORM\Repository\AbstractRepository;
use PDO;

class ArticleReviewActionRepository extends AbstractRepository
{
    protected function getEntityClass(): string
    {
        return ArticleReviewAction::class;
    }

    /**
     * @return array<int, array{
     *     action:string,
     *     comment:?string,
     *     reviewed_at:string,
     *     reviewer_name:string
     * }>
     */
    public function findByArticleVersion(int $articleVersionId, int $limit = 20): array
    {
        $safeLimit = max(1, min(100, $limit));
        $sql       = <<<SQL
            SELECT
                ra.action,
                ra.comment,
                ra.reviewed_at,
                CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, '')) AS reviewer_name
            FROM article_review_actions ra
            LEFT JOIN users u ON u.id = ra.reviewed_by
            WHERE ra.article_version_id = :articleVersionId
            ORDER BY ra.reviewed_at DESC, ra.id DESC
            LIMIT {$safeLimit}
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['articleVersionId' => $articleVersionId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            static fn(array $row): array => [
                'action'        => (string) ($row['action'] ?? ''),
                'comment'       => $row['comment'] !== null ? (string) $row['comment'] : null,
                'reviewed_at'   => (string) ($row['reviewed_at'] ?? ''),
                'reviewer_name' => trim((string) ($row['reviewer_name'] ?? '')),
            ],
            $rows,
        );
    }

    public function addAction(
        int $articleId,
        int $articleVersionId,
        string $language,
        string $action,
        ?string $comment,
        int $reviewedBy,
    ): bool {
        $stmt = $this->pdo->prepare(
            'INSERT INTO article_review_actions
                (article_id, article_version_id, language, action, comment, reviewed_by, reviewed_at)
             VALUES
                (:article_id, :article_version_id, :language, :action, :comment, :reviewed_by, CURRENT_TIMESTAMP)',
        );

        return $stmt->execute([
            'article_id'         => $articleId,
            'article_version_id' => $articleVersionId,
            'language'           => $language,
            'action'             => $action,
            'comment'            => $this->nullableTrimmedComment($comment),
            'reviewed_by'        => $reviewedBy,
        ]);
    }

    private function nullableTrimmedComment(?string $comment): ?string
    {
        if ($comment === null) {
            return null;
        }

        $trimmed = trim($comment);
        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, 1000);
    }
}
