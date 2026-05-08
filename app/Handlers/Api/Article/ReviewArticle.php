<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Article;

use NixCMS\Enums\ArticleStatus;
use NixCMS\Enums\Permission;
use NixCMS\Exceptions\AuthException;
use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Support\AuditEntry;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use function NixPHP\Database\database;
use function NixPHP\json;
use function NixPHP\Session\session;
use function NixCMS\user;

final class ReviewArticle
{
    use ReadsJsonBody;

    public function __invoke(string $articleId, string $language): ResponseInterface
    {
        $helper = user();
        if (
            $helper === null
            || (!$helper->can(Permission::ARTICLES_REVIEW) && !$helper->can(Permission::ARTICLES_PUBLISH))
        ) {
            throw new AuthException();
        }

        $payload = $this->jsonBody();
        $action = strtolower(trim((string) ($payload['action'] ?? '')));
        $comment = isset($payload['comment']) ? trim((string) $payload['comment']) : null;
        $publishArticle = filter_var($payload['publish_article'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!in_array($action, ['approve', 'decline'], true)) {
            return json(['success' => false, 'reason' => 'Invalid review action.'], 400);
        }

        if ($publishArticle && !$helper->can(Permission::ARTICLES_PUBLISH)) {
            return json(['success' => false, 'reason' => 'Not allowed to publish directly.'], 403);
        }

        return $this->withDatabase(function (PDO $connection) use ($articleId, $language, $action, $comment, $publishArticle): array {
            $articleIdValue = (int) $articleId;
            $languageValue = strtolower(trim($language));

            $select = $connection->prepare(
                'SELECT id, status FROM article_versions WHERE article_id = :article_id AND language = :language ORDER BY version DESC LIMIT 1'
            );
            $select->execute([
                'article_id' => $articleIdValue,
                'language' => $languageValue,
            ]);
            $version = $select->fetch(PDO::FETCH_ASSOC);
            if (!is_array($version)) {
                return ['success' => false, 'reason' => 'Article version not found.'];
            }

            $currentStatus = strtolower((string) ($version['status'] ?? ''));
            if ($action === 'decline' && $currentStatus === ArticleStatus::PUBLISHED->value) {
                return ['success' => false, 'reason' => 'Cannot decline a published article.'];
            }

            $newStatus = $currentStatus;
            if ($action === 'approve') {
                $newStatus = $publishArticle ? ArticleStatus::PUBLISHED->value : (
                    $currentStatus === ArticleStatus::DECLINED->value ? ArticleStatus::DRAFT->value : $currentStatus
                );
            }
            if ($action === 'decline') {
                $newStatus = ArticleStatus::DECLINED->value;
            }

            $connection->beginTransaction();
            try {
                if ($newStatus !== $currentStatus) {
                    $update = $connection->prepare(
                        'UPDATE article_versions SET status = :status, publishedAt = CASE WHEN :status_publish = 1 THEN COALESCE(publishedAt, CURRENT_TIMESTAMP) ELSE publishedAt END WHERE id = :id'
                    );
                    $update->execute([
                        'status' => $newStatus,
                        'status_publish' => $newStatus === ArticleStatus::PUBLISHED->value ? 1 : 0,
                        'id' => (int) $version['id'],
                    ]);
                }

                $insert = $connection->prepare(
                    'INSERT INTO article_review_actions (article_id, article_version_id, language, action, comment, reviewed_by) VALUES (:article_id, :article_version_id, :language, :action, :comment, :reviewed_by)'
                );
                $insert->execute([
                    'article_id' => $articleIdValue,
                    'article_version_id' => (int) $version['id'],
                    'language' => $languageValue,
                    'action' => $action,
                    'comment' => $comment !== '' ? $comment : null,
                    'reviewed_by' => $this->resolveReviewerId($connection),
                ]);

                $connection->commit();
            } catch (Throwable $throwable) {
                $connection->rollBack();
                throw $throwable;
            }

            AuditEntry::create(
                'article.reviewed',
                'article',
                (string) $articleIdValue,
                [
                    'article_version_id' => (int) $version['id'],
                    'language' => $languageValue,
                    'status' => $currentStatus,
                ],
                [
                    'article_version_id' => (int) $version['id'],
                    'language' => $languageValue,
                    'status' => $newStatus,
                    'review_action' => $action,
                    'published_directly' => $publishArticle,
                ],
                ['has_comment' => $comment !== null && $comment !== ''],
            )->record();

            return ['success' => true, 'action' => $action, 'newStatus' => $newStatus];
        });
    }

    private function resolveReviewerId(PDO $connection): int
    {
        $userId = (int) (session()->get('userId') ?? 0);
        if ($userId > 0) {
            return $userId;
        }

        return (int) $connection
            ->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')
            ->fetchColumn();
    }

    /**
     * @param callable(PDO): array<string, mixed> $callback
     */
    private function withDatabase(callable $callback): ResponseInterface
    {
        try {
            $connection = database();
            if (!$connection instanceof PDO) {
                return json(['success' => false, 'reason' => 'Database is not available.'], 503);
            }

            return json($callback($connection));
        } catch (Throwable $exception) {
            return json(['success' => false, 'reason' => $exception->getMessage()], 500);
        }
    }
}
