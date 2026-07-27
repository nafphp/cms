<?php

declare(strict_types=1);

namespace NixPHP\CMS\Services\Content;

use PDO;
use Throwable;
use function NixPHP\Database\database;

final class ArticleFormService
{
    public function __construct(private ?PDO $connection = null)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findArticleForForm(int $articleId, ?string $language = null, ?int $version = null): ?array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO || $articleId <= 0) {
            return null;
        }

        $sql = <<<SQL
            SELECT
                a.id,
                a.category_id,
                a.author,
                a.hide_lead_image_in_content,
                c.name AS category_name,
                av.id AS article_version_id,
                av.language,
                av.version,
                av.status,
                av.slug,
                av.title,
                av.teaser,
                av.content,
                av.publishedAt,
                av.show_teaser
            FROM articles a
            LEFT JOIN categories c ON c.id = a.category_id
            LEFT JOIN article_versions av ON av.article_id = a.id
            WHERE a.id = :article_id
        SQL;

        $params = ['article_id' => $articleId];
        if ($language !== null && trim($language) !== '') {
            $sql .= ' AND av.language = :language';
            $params['language'] = strtolower(trim($language));
        }
        if ($version !== null && $version > 0) {
            $sql .= ' AND av.version = :version';
            $params['version'] = $version;
        }
        $sql .= ' ORDER BY av.version DESC LIMIT 1';

        $statement = $connection->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $row['tags'] = $this->tagNamesForArticle($articleId);
        return $row;
    }

    /**
     * @return array<int, string>
     */
    public function categoryNames(): array
    {
        return $this->nameColumn('categories');
    }

    /**
     * @return array<int, string>
     */
    public function tagNames(): array
    {
        return $this->nameColumn('tags');
    }

    public function nextVersion(int $articleId, string $language): int
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO || $articleId <= 0) {
            return 1;
        }

        $statement = $connection->prepare(
            'SELECT COALESCE(MAX(version), 0) + 1 FROM article_versions WHERE article_id = :article_id AND language = :language'
        );
        $statement->execute([
            'article_id' => $articleId,
            'language' => strtolower(trim($language)),
        ]);

        return max(1, (int) $statement->fetchColumn());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function reviewActions(int $articleVersionId): array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO || $articleVersionId <= 0) {
            return [];
        }

        $statement = $connection->prepare(<<<SQL
            SELECT
                ara.action,
                ara.comment,
                ara.reviewed_at,
                u.username,
                u.firstname,
                u.lastname
            FROM article_review_actions ara
            LEFT JOIN users u ON u.id = ara.reviewed_by
            WHERE ara.article_version_id = :article_version_id
            ORDER BY ara.reviewed_at DESC, ara.id DESC
        SQL);
        $statement->execute(['article_version_id' => $articleVersionId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, string>
     */
    private function tagNamesForArticle(int $articleId): array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO) {
            return [];
        }

        $statement = $connection->prepare(
            'SELECT t.name FROM tags t INNER JOIN article_tags at ON at.tag_id = t.id WHERE at.article_id = :article_id ORDER BY t.name ASC'
        );
        $statement->execute(['article_id' => $articleId]);

        return array_values(array_filter(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN))));
    }

    /**
     * @return array<int, string>
     */
    private function nameColumn(string $table): array
    {
        $connection = $this->connection();
        if (!$connection instanceof PDO || !in_array($table, ['categories', 'tags'], true)) {
            return [];
        }

        try {
            return array_values(array_filter(array_map(
                static fn(mixed $value): string => trim((string) $value),
                $connection->query(sprintf('SELECT name FROM %s ORDER BY name ASC', $table))->fetchAll(PDO::FETCH_COLUMN)
            )));
        } catch (Throwable) {
            return [];
        }
    }

    private function connection(): ?PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        try {
            $connection = database();
        } catch (Throwable) {
            return null;
        }

        return $connection instanceof PDO ? $connection : null;
    }
}
