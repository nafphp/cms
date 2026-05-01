<?php

declare(strict_types=1);

namespace NixCMS\Repositories;

use NixCMS\Enums\ArticleStatus;
use NixCMS\Enums\Language;
use NixCMS\Models\ArticleVersion;
use NixPHP\ORM\Repository\AbstractRepository;
use function NixPHP\ORM\em;

class ArticleVersionRepository extends AbstractRepository
{
    protected function getEntityClass(): string
    {
        return ArticleVersion::class;
    }

    public function getTable(bool $singular = false): string
    {
        return 'article_versions';
    }

    public function getNextVersionById(int $articleVersionId): int
    {
        $sql = "SELECT COALESCE(MAX(version), 0) + 1 AS next_version
                FROM {$this->getTable()}
                WHERE id = :article_version_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['article_version_id' => $articleVersionId]);
        return (int) $stmt->fetchColumn();
    }

    public function getNextVersion(int $articleId, Language $language): int
    {
        $sql = "SELECT COALESCE(MAX(version), 0) + 1 AS next_version
                FROM {$this->getTable()}
                WHERE article_id = :article_id AND language = :language";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'article_id' => $articleId,
            'language' => $language->value,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function createNewVersion(
        int $articleId,
        Language $language,
        string $slug,
        string $title,
        string $teaser,
        string $content,
        ?\DateTime $publishedAt = null,
        bool $showTeaser = false,
    ): ArticleVersion {
        $nextVersion = $this->getNextVersion($articleId, $language);

        $entityClass = $this->getEntityClass();
        $version = new $entityClass([
            'article_id' => $articleId,
            'language' => $language->value,
            'slug' => $slug,
            'version' => $nextVersion,
            'status' => ArticleStatus::DRAFT->value,
            'title' => $title,
            'teaser' => $teaser,
            'show_teaser' => $showTeaser ? 1 : 0,
            'content' => $content,
            'publishedAt' => $publishedAt?->format('Y-m-d H:i:s'),
        ]);

        em()->save($version);

        return $version;
    }
}
