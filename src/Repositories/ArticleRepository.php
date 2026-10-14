<?php

declare(strict_types=1);

namespace Naf\CMS\Repositories;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Naf\CMS\Enums\ArticleStatus;
use Naf\CMS\Models\Article;
use Naf\CMS\Models\ArticleVersion;
use Naf\CMS\Models\Category;
use Naf\CMS\Models\Tag;
use Naf\ORM\Repository\AbstractRepository;
use PDO;
use RuntimeException;
use Throwable;

use function Naf\CMS\cms;
use function Naf\log;
use function Naf\ORM\em;
use function Naf\ORM\repo;

class ArticleRepository extends AbstractRepository
{
    protected function getEntityClass(): string
    {
        return Article::class;
    }

    /**
     * @return array<int, Article>
     */
    public function findAllWithLatestVersion(): array
    {
        /** @var array<int, Article> $articles */
        $articles = $this->findAll();

        return $this->hydrateLatestVersions($articles);
    }

    /**
     * @return array<int, Article>
     */
    public function findAllWithLatestVersionByCategory(int $categoryId): array
    {
        /** @var array<int, Article> $articles */
        $articles = $this->findBy(['category_id' => $categoryId]);

        return $this->hydrateLatestVersions($articles);
    }

    /**
     * Returns published articles with latest version, optionally filtered by category,
     * limited, and with excluded article ids removed.
     *
     * @param int[] $excludeArticleIds
     * @return array<int, Article>
     */
    public function findPublishedWithLatestVersionByCategory(
        ?int $categoryId,
        ?int $limit = null,
        array $excludeArticleIds = [],
    ): array {
        return $this->findPublishedWithLatestVersionByCategoryAndLanguage(
            $categoryId,
            $limit,
            $this->resolveActiveLanguage(),
            $excludeArticleIds,
        );
    }

    /**
     * Returns published articles for a specific language.
     *
     * @param int[] $excludeArticleIds
     * @return array<int, Article>
     */
    public function findPublishedWithLatestVersionByCategoryAndLanguage(
        ?int $categoryId,
        ?int $limit,
        string $language,
        array $excludeArticleIds = [],
    ): array {
        /** @var array<int, Article> $articles */
        $articles    = $this->findAll();
        $articles    = $this->hydrateLatestVisiblePublishedVersions($articles, $language);
        $excludedIds = array_values(array_unique(array_map('intval', $excludeArticleIds)));

        $filteredArticles = array_values(array_filter(
            $articles,
            static function (Article $candidate) use ($categoryId, $excludedIds): bool {
                if (in_array($candidate->getId(), $excludedIds, true)) {
                    return false;
                }

                if ($candidate->getVersion() === null) {
                    return false;
                }

                if ($categoryId === null) {
                    return true;
                }

                $candidateCategoryId = $candidate->getCategory()?->getId();

                return $candidateCategoryId !== null && (int) $candidateCategoryId === $categoryId;
            },
        ));

        usort(
            $filteredArticles,
            static fn(Article $a, Article $b): int
                => ($b->getPublishedAt()?->getTimestamp() ?? 0) <=> ($a->getPublishedAt()?->getTimestamp() ?? 0),
        );

        if ($limit !== null) {
            return array_slice($filteredArticles, 0, max(0, $limit));
        }

        return $filteredArticles;
    }

    /**
     * @return array<int, array{article:Article}>
     */
    public function findReadingRecommendations(Article $currentArticle, int $limit = 2): array
    {
        $safeLimit = max(0, $limit);
        if ($safeLimit === 0) {
            return [];
        }

        $currentArticleId  = (int) $currentArticle->getId();
        $currentCategoryId = $currentArticle->getCategory()?->getId();
        $currentTagNames   = $this->normalizeTagNames($currentArticle->getTagNames());
        if ($currentTagNames === []) {
            return [];
        }

        $currentTagSet      = array_fill_keys($currentTagNames, true);
        $candidates         = $this->findPublishedWithLatestVersionByCategory(null, null, [$currentArticleId]);
        $sameCategoryAndTag = [];
        $sameTag            = [];

        foreach ($candidates as $candidate) {
            if ((int) $candidate->getId() === $currentArticleId) {
                continue;
            }

            $candidateCategoryId = $candidate->getCategory()?->getId();
            $sameCategory        = $currentCategoryId !== null
                && $candidateCategoryId !== null
                && (int) $candidateCategoryId === (int) $currentCategoryId;

            $sharedTagCount = 0;
            foreach ($this->normalizeTagNames($candidate->getTagNames()) as $candidateTagName) {
                if (isset($currentTagSet[$candidateTagName])) {
                    $sharedTagCount++;
                }
            }

            if ($sharedTagCount <= 0) {
                continue;
            }

            $entry = [
                'article'        => $candidate,
                'sharedTagCount' => $sharedTagCount,
            ];

            if ($sameCategory) {
                $sameCategoryAndTag[] = $entry;
                continue;
            }

            $sameTag[] = $entry;
        }

        $scored = $sameCategoryAndTag !== [] ? $sameCategoryAndTag : $sameTag;

        usort(
            $scored,
            static function (array $left, array $right): int {
                if ($left['sharedTagCount'] === $right['sharedTagCount']) {
                    return ((int) $right['article']->getId()) <=> ((int) $left['article']->getId());
                }

                return $right['sharedTagCount'] <=> $left['sharedTagCount'];
            },
        );

        $recommendations = [];

        foreach ($scored as $entry) {
            if (count($recommendations) >= $safeLimit) {
                break;
            }

            /** @var Article $candidate */
            $candidate         = $entry['article'];
            $recommendations[] = [
                'article' => $candidate,
            ];
        }

        return $recommendations;
    }

    /**
     * @param array<int, string> $tags
     * @return array<int, string>
     */
    private function normalizeTagNames(array $tags): array
    {
        $normalized = [];
        foreach ($tags as $tag) {
            $value = trim((string) $tag);
            if ($value === '') {
                continue;
            }

            $lookup = function_exists('mb_strtolower')
                ? mb_strtolower($value, 'UTF-8')
                : strtolower($value);
            $normalized[$lookup] = $lookup;
        }

        return array_values($normalized);
    }

    /**
     * @param array<int, Article> $articles
     * @return array<int, Article>
     */
    private function hydrateLatestVersions(array $articles): array
    {
        $language = $this->resolveActiveLanguage();

        usort($articles, static fn(Article $a, Article $b): int => $b->getId() <=> $a->getId());

        foreach ($articles as $article) {
            $languageVersions = array_filter(
                $article->getVersions(),
                static fn(ArticleVersion $version): bool => $version->getLanguageCode() === $language,
            );

            usort(
                $languageVersions,
                static fn(ArticleVersion $a, ArticleVersion $b): int => $b->getVersion() <=> $a->getVersion(),
            );

            if (count($languageVersions) === 0) {
                continue;
            }

            $article->setCurrentVersion(reset($languageVersions));
        }

        return $articles;
    }

    /**
     * @param array<int, Article> $articles
     * @return array<int, Article>
     */
    private function hydrateLatestVisiblePublishedVersions(array $articles, ?string $language = null): array
    {
        $language = $language !== null && trim($language) !== ''
            ? strtolower(trim($language))
            : $this->resolveActiveLanguage();

        usort($articles, static fn(Article $a, Article $b): int => $b->getId() <=> $a->getId());

        $fallbackLanguage = cms()->settings()->fallbackLanguage();

        foreach ($articles as $article) {
            $version = $this->preferredVisibleVersion($article, $language, $fallbackLanguage);
            if ($version instanceof ArticleVersion) {
                $article->setCurrentVersion($version);
            }
        }

        return $articles;
    }

    /**
     * Pick the version of an article a visitor should see in a listing.
     *
     * Mirrors the detail page: the requested language wins, then the configured
     * fallback, then any published translation. An article that exists only in
     * another language stays listed instead of silently disappearing.
     */
    private function preferredVisibleVersion(
        Article $article,
        string $language,
        string $fallbackLanguage,
    ): ?ArticleVersion {
        $fallbackVersion = null;
        $anyVersion      = null;

        foreach ($article->getVersions() as $version) {
            if (!$this->isFrontendVisiblePublishedVersion($version)) {
                continue;
            }

            $versionLanguage = $version->getLanguageCode();
            if ($versionLanguage === $language) {
                return $version;
            }

            if ($versionLanguage === $fallbackLanguage && $fallbackVersion === null) {
                $fallbackVersion = $version;
            }

            $anyVersion ??= $version;
        }

        return $fallbackVersion ?? $anyVersion;
    }

    private function isFrontendVisiblePublishedVersion(ArticleVersion $version, ?DateTimeInterface $now = null): bool
    {
        if ($version->getStatus() !== ArticleStatus::PUBLISHED) {
            return false;
        }

        if (trim((string) ($version->getSlug() ?? '')) === '') {
            return false;
        }

        $publishedAt = $version->getPublishedAt();
        if ($publishedAt === null) {
            return false;
        }

        $now ??= new DateTimeImmutable();

        return $publishedAt->getTimestamp() <= $now->getTimestamp();
    }

    public function findWithVersion(int $id, ?string $language = null): ?Article
    {
        /** @var Article|null $article */
        $article = $this->findOneBy('id', $id);
        if (!$article) {
            return null;
        }

        $criteria = ['article_id' => $article->getId()];
        if ($language !== null && $language !== '') {
            $criteria['language'] = $language;
        }

        /** @var ArticleVersion|null $version */
        $version = repo(ArticleVersionRepository::class)
            ->findOneBy(
                $criteria,
                orderBy: ['version' => 'DESC'],
            );

        if ($version) {
            $article->setCurrentVersion($version);
        }

        return $article;
    }

    public function findPublishedVisibleWithVersion(int $id, ?string $language = null): ?Article
    {
        /** @var Article|null $article */
        $article = $this->findOneBy('id', $id);
        if (!$article) {
            return null;
        }

        $language = $language !== null && trim($language) !== ''
            ? strtolower(trim($language))
            : $this->resolveActiveLanguage();

        foreach ($article->getVersions() as $version) {
            if ($version->getLanguageCode() !== $language) {
                continue;
            }

            if (!$this->isFrontendVisiblePublishedVersion($version)) {
                continue;
            }

            $article->setCurrentVersion($version);

            return $article;
        }

        return null;
    }

    /**
     * Resolve an article URL to the version the visitor should read.
     *
     * A slug belongs to one language version, but the URL is the article's, not
     * the language's. So the slug only identifies the article; which version is
     * served is decided by the active language, falling back to the configured
     * fallback language. That keeps one canonical URL per article instead of a
     * language-suffixed variant per translation.
     */
    public function findPublishedVisibleBySlug(string $slug, ?string $language = null): ?Article
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $language = $language !== null && trim($language) !== ''
            ? strtolower(trim($language))
            : $this->resolveActiveLanguage();

        $version = $this->visibleVersionBySlugAndLanguage($slug, $language);

        if (!$version instanceof ArticleVersion) {
            // The slug belongs to a different translation. Keep the URL and
            // serve the active language, then the fallback language, and only
            // then the translation that owns the slug.
            $owner = $this->visibleVersionBySlug($slug);
            if ($owner instanceof ArticleVersion) {
                $articleId        = (int) $owner->getArticleId();
                $fallbackLanguage = cms()->settings()->fallbackLanguage();

                $version = $this->visibleVersionByArticleAndLanguage($articleId, $language)
                    ?? ($fallbackLanguage !== $language
                        ? $this->visibleVersionByArticleAndLanguage($articleId, $fallbackLanguage)
                        : null)
                    ?? $owner;
            }
        }

        if (!$version instanceof ArticleVersion) {
            return null;
        }

        /** @var Article|null $article */
        $article = $this->findOneBy('id', (int) $version->getArticleId());
        if (!$article instanceof Article) {
            return null;
        }

        $article->setCurrentVersion($version);

        return $article;
    }

    private function visibleVersionBySlugAndLanguage(string $slug, string $language): ?ArticleVersion
    {
        return $this->visibleVersion(['slug' => $slug, 'language' => $language]);
    }

    private function visibleVersionBySlug(string $slug): ?ArticleVersion
    {
        return $this->visibleVersion(['slug' => $slug]);
    }

    private function visibleVersionByArticleAndLanguage(int $articleId, string $language): ?ArticleVersion
    {
        if ($articleId <= 0 || trim($language) === '') {
            return null;
        }

        return $this->visibleVersion(['article_id' => $articleId, 'language' => $language]);
    }

    /**
     * @param array<string, mixed> $criteria
     */
    private function visibleVersion(array $criteria): ?ArticleVersion
    {
        /** @var ArticleVersion|null $version */
        $version = repo(ArticleVersionRepository::class)->findOneBy(
            $criteria,
            orderBy: ['version' => 'DESC'],
        );

        return $version instanceof ArticleVersion && $this->isFrontendVisiblePublishedVersion($version)
            ? $version
            : null;
    }

    public function findWithSpecificVersion(int $id, string $language, int $versionNumber): ?Article
    {
        /** @var Article|null $article */
        $article = $this->findOneBy('id', $id);
        if (!$article) {
            return null;
        }

        /** @var ArticleVersion|null $version */
        $version = repo(ArticleVersionRepository::class)->findOneBy([
            'article_id' => $article->getId(),
            'language'   => $language,
            'version'    => $versionNumber,
        ]);

        if ($version) {
            $article->setCurrentVersion($version);
        }

        return $article;
    }

    /**
     * Persist an article draft using the same versioning semantics as WeOnlyWalk:
     * an existing draft/declined version is overwritten and moved back to draft;
     * published, scheduled, or review versions are preserved and a new version is created.
     *
     * @param array<string, mixed> $data
     * @return array{articleId:int,articleVersionId:int}
     */
    public function saveDraftFromPayload(array $data, ?int $authorId = null): array
    {
        em()->begin();

        try {
            $articleId              = (int) ($data['article_id'] ?? 0);
            $hideLeadImageInContent = filter_var($data['hide_lead_image_in_content'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $showTeaser             = filter_var($data['show_teaser'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $language               = cms()->languages()->normalize((string) ($data['language'] ?? ''));
            if ($language === null || !cms()->languages()->isKnown($language)) {
                throw new InvalidArgumentException('Invalid article language.');
            }

            $content = json_encode($data['content'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($content === false) {
                throw new RuntimeException('Could not encode article content.');
            }

            $category = repo(CategoryRepository::class)->findOrCreateByNormalizedName((string) ($data['category'] ?? ''));

            if ($articleId > 0) {
                /** @var Article|null $article */
                $article = $this->findOneBy('id', $articleId);
                if (!$article instanceof Article) {
                    throw new RuntimeException('Article not found.');
                }

                $article->setHideLeadImageInContent($hideLeadImageInContent);
                $article->setCategory($category instanceof Category ? $category : null);
            } else {
                $article = new Article([
                    'author'                     => $authorId,
                    'hide_lead_image_in_content' => $hideLeadImageInContent ? 1 : 0,
                ]);
                $article->setCategory($category instanceof Category ? $category : null);
            }

            em()->save($article);
            $articleId = (int) $article->getId();

            $this->replaceArticleTags($articleId, $this->normalizeTagNamesFromPayload($data['tags'] ?? []));

            $articleVersionId = (int) ($data['article_version_id'] ?? 0);
            $articleVersion   = null;
            if ($articleVersionId > 0) {
                /** @var ArticleVersion|null $candidateVersion */
                $candidateVersion = repo(ArticleVersionRepository::class)->findOneBy('id', $articleVersionId);
                if (
                    $candidateVersion instanceof ArticleVersion
                    && in_array($candidateVersion->getStatus(), [ArticleStatus::DRAFT, ArticleStatus::DECLINED], true)
                ) {
                    $articleVersion = $candidateVersion
                        ->withTitle(trim((string) $data['title']))
                        ->withTeaser((string) ($data['teaser'] ?? ''))
                        ->withShowTeaser($showTeaser)
                        ->withSlug(trim((string) $data['slug']))
                        ->withStatus(ArticleStatus::DRAFT)
                        ->withLanguage($language)
                        ->withContent($content)
                        ->withPublishedAt($this->normalizeDate((string) ($data['publish_date'] ?? '')));
                }
            }

            if (!$articleVersion instanceof ArticleVersion) {
                $articleVersion = repo(ArticleVersionRepository::class)->createNewVersion(
                    $articleId,
                    $language,
                    trim((string) $data['slug']),
                    trim((string) $data['title']),
                    (string) ($data['teaser'] ?? ''),
                    $content,
                    $this->normalizeDate((string) ($data['publish_date'] ?? '')),
                    $showTeaser,
                );
            } else {
                em()->save($articleVersion);
            }

            em()->commit();

            return [
                'articleId'        => $articleId,
                'articleVersionId' => (int) $articleVersion->getId(),
            ];
        } catch (Throwable $throwable) {
            em()->rollback();

            throw $throwable;
        }
    }

    public function publishLatestLanguageVersion(int $articleId, string $language): bool
    {
        $article = $this->findWithVersion($articleId, $language);
        $version = $article?->getVersion();
        if (!$version instanceof ArticleVersion || $version->getStatus() === ArticleStatus::PUBLISHED) {
            return false;
        }

        $publishedAt      = $version->getPublishedAt() ?? new DateTime();
        $publishedVersion = $version
            ->withStatus(ArticleStatus::PUBLISHED)
            ->withPublishedAt($publishedAt);

        em()->save($publishedVersion);

        return true;
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM articles')->fetchColumn();
    }

    public function countVersionsByStatus(ArticleStatus $status): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM article_versions WHERE status = :status');
        $stmt->execute(['status' => $status->value]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string, int>
     */
    public function getVersionStatusOverview(): array
    {
        $overview = [];
        foreach (ArticleStatus::cases() as $status) {
            $overview[$status->value] = 0;
        }

        $stmt = $this->pdo->query('SELECT status, COUNT(*) AS total FROM article_versions GROUP BY status');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (!array_key_exists($status, $overview)) {
                continue;
            }

            $overview[$status] = (int) ($row['total'] ?? 0);
        }

        return $overview;
    }

    /**
     * @return array<string, int>
     */
    public function getArticleStatusOverview(?int $authorId = null, ?string $language = null): array
    {
        $overview = [];
        foreach (ArticleStatus::cases() as $status) {
            $overview[$status->value] = 0;
        }

        $language = $language !== null && trim($language) !== ''
            ? strtolower(trim($language))
            : $this->resolveActiveLanguage();

        $sql = <<<'SQL'
            SELECT av.status, COUNT(*) AS total
            FROM article_versions av
            INNER JOIN articles a ON a.id = av.article_id
            INNER JOIN (
                SELECT article_id, language, MAX(version) AS latest_version
                FROM article_versions
                WHERE language = :language_latest
                GROUP BY article_id, language
            ) latest ON latest.article_id = av.article_id
                AND latest.language = av.language
                AND latest.latest_version = av.version
            WHERE av.language = :language_filter
        SQL;

        $params = [
            'language_latest' => $language,
            'language_filter' => $language,
        ];
        if ($authorId !== null) {
            $sql .= ' AND a.author = :author_id';
            $params['author_id'] = $authorId;
        }

        $sql .= ' GROUP BY av.status';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (!array_key_exists($status, $overview)) {
                continue;
            }

            $overview[$status] = (int) ($row['total'] ?? 0);
        }

        return $overview;
    }

    public function countDraftReviewQueue(): int
    {
        $sql = <<<'SQL'
            SELECT COUNT(*)
            FROM article_versions av
            WHERE av.status IN (:draftStatusOuter, :declinedStatusOuter)
                AND av.id = (
                    SELECT av2.id
                    FROM article_versions av2
                    WHERE av2.article_id = av.article_id
                        AND av2.language = av.language
                    ORDER BY av2.version DESC
                    LIMIT 1
                )
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'draftStatusOuter'    => ArticleStatus::DRAFT->value,
            'declinedStatusOuter' => ArticleStatus::DECLINED->value,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<int, array{
     *     article_id:int,
     *     language:string,
     *     article_version_id:int,
     *     version:int,
     *     title:string,
     *     slug:string,
     *     teaser:string,
     *     author_name:string,
     *     published_at:?string,
     *     created_at:?string,
     *     last_review_action:?string,
     *     last_review_comment:?string,
     *     last_reviewed_at:?string,
     *     last_reviewer_name:?string
     * }>
     */
    public function getDraftReviewQueue(int $limit = 8): array
    {
        $safeLimit = max(1, min(50, $limit));

        $sql = <<<SQL
            SELECT
                av.article_id,
                av.language,
                av.id AS article_version_id,
                av.version,
                av.title,
                av.slug,
                av.teaser,
                CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, '')) AS author_name,
                av.publishedAt AS published_at,
                av.createdAt AS created_at,
                ra.action AS last_review_action,
                ra.comment AS last_review_comment,
                ra.reviewed_at AS last_reviewed_at,
                CONCAT(COALESCE(reviewer.firstname, ''), ' ', COALESCE(reviewer.lastname, '')) AS last_reviewer_name
            FROM article_versions av
            INNER JOIN articles a ON a.id = av.article_id
            LEFT JOIN users u ON u.id = a.author
            LEFT JOIN article_review_actions ra ON ra.id = (
                SELECT ra2.id
                FROM article_review_actions ra2
                WHERE ra2.article_version_id = av.id
                ORDER BY ra2.reviewed_at DESC, ra2.id DESC
                LIMIT 1
            )
            LEFT JOIN users reviewer ON reviewer.id = ra.reviewed_by
            WHERE av.status IN (:draftStatus, :declinedStatus)
                AND av.id = (
                    SELECT av2.id
                    FROM article_versions av2
                    WHERE av2.article_id = av.article_id
                        AND av2.language = av.language
                    ORDER BY av2.version DESC
                    LIMIT 1
                )
            ORDER BY av.createdAt DESC
            LIMIT {$safeLimit}
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'draftStatus'    => ArticleStatus::DRAFT->value,
            'declinedStatus' => ArticleStatus::DECLINED->value,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            static fn(array $row): array => [
                'article_id'          => (int) ($row['article_id'] ?? 0),
                'language'            => (string) ($row['language'] ?? ''),
                'article_version_id'  => (int) ($row['article_version_id'] ?? 0),
                'version'             => (int) ($row['version'] ?? 0),
                'title'               => (string) ($row['title'] ?? ''),
                'slug'                => (string) ($row['slug'] ?? ''),
                'teaser'              => (string) ($row['teaser'] ?? ''),
                'author_name'         => trim((string) ($row['author_name'] ?? '')),
                'published_at'        => $row['published_at'] !== null ? (string) $row['published_at'] : null,
                'created_at'          => $row['created_at'] !== null ? (string) $row['created_at'] : null,
                'last_review_action'  => $row['last_review_action'] !== null ? (string) $row['last_review_action'] : null,
                'last_review_comment' => $row['last_review_comment'] !== null ? (string) $row['last_review_comment'] : null,
                'last_reviewed_at'    => $row['last_reviewed_at'] !== null ? (string) $row['last_reviewed_at'] : null,
                'last_reviewer_name'  => $row['last_reviewer_name'] !== null ? trim((string) $row['last_reviewer_name']) : null,
            ],
            $rows,
        );
    }

    public function deleteLanguageVersions(int $articleId, string $language): bool
    {
        if ($articleId <= 0) {
            return false;
        }

        $this->pdo->beginTransaction();

        try {
            $deleteStmt = $this->pdo->prepare(
                'DELETE FROM article_versions WHERE article_id = :article_id AND language = :language',
            );
            $deleteStmt->execute([
                'article_id' => $articleId,
                'language'   => $language,
            ]);

            $deleted = $deleteStmt->rowCount() > 0;
            if (!$deleted) {
                $this->pdo->rollBack();

                return false;
            }

            $remainingStmt = $this->pdo->prepare('SELECT COUNT(*) FROM article_versions WHERE article_id = :article_id');
            $remainingStmt->execute(['article_id' => $articleId]);
            $remaining = (int) $remainingStmt->fetchColumn();

            if ($remaining === 0) {
                $articleStmt = $this->pdo->prepare('DELETE FROM articles WHERE id = :article_id');
                $articleStmt->execute(['article_id' => $articleId]);
            }

            $this->pdo->commit();

            return true;
        } catch (Throwable $e) {
            log()->error(sprintf('ArticleRepository.deleteLanguageVersions failed: %s', $e->getMessage()), ['exception' => $e]);
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return false;
        }
    }

    /**
     * @param array<int, string> $tagNames
     */
    private function replaceArticleTags(int $articleId, array $tagNames): void
    {
        $delete = $this->pdo->prepare('DELETE FROM article_tags WHERE article_id = :article_id');
        $delete->execute(['article_id' => $articleId]);

        if ($tagNames === []) {
            return;
        }

        /** @var array<int, Tag> $tags */
        $tags   = repo(TagRepository::class)->findOrCreateManyByNormalizedName($tagNames);
        $insert = $this->pdo->prepare(
            'INSERT IGNORE INTO article_tags (article_id, tag_id) VALUES (:article_id, :tag_id)',
        );
        foreach ($tags as $tag) {
            $tagId = (int) $tag->getId();
            if ($tagId <= 0) {
                continue;
            }

            $insert->execute([
                'article_id' => $articleId,
                'tag_id'     => $tagId,
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function normalizeTagNamesFromPayload(mixed $rawTags): array
    {
        $values = is_array($rawTags) ? $rawTags : (preg_split('/[,;\n\r]+/u', (string) $rawTags) ?: []);
        $tags   = [];
        $seen   = [];

        foreach ($values as $value) {
            $name = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';
            if ($name === '') {
                continue;
            }

            $key = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $tags[]     = $name;
        }

        return $tags;
    }

    private function normalizeDate(string $value): ?DateTime
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        try {
            return new DateTime($trimmed);
        } catch (Throwable $e) {
            log()->warning(sprintf('ArticleRepository.normalizeDate failed: %s', $e->getMessage()), ['exception' => $e]);

            return null;
        }
    }

    public function deleteSpecificVersion(int $articleId, string $language, int $versionNumber): bool
    {
        if ($articleId <= 0 || $versionNumber <= 0) {
            return false;
        }

        $this->pdo->beginTransaction();

        try {
            $deleteStmt = $this->pdo->prepare(
                'DELETE FROM article_versions
                 WHERE article_id = :article_id AND language = :language AND version = :version',
            );
            $deleteStmt->execute([
                'article_id' => $articleId,
                'language'   => $language,
                'version'    => $versionNumber,
            ]);

            $deleted = $deleteStmt->rowCount() > 0;
            if (!$deleted) {
                $this->pdo->rollBack();

                return false;
            }

            $remainingStmt = $this->pdo->prepare('SELECT COUNT(*) FROM article_versions WHERE article_id = :article_id');
            $remainingStmt->execute(['article_id' => $articleId]);
            $remaining = (int) $remainingStmt->fetchColumn();

            if ($remaining === 0) {
                $articleStmt = $this->pdo->prepare('DELETE FROM articles WHERE id = :article_id');
                $articleStmt->execute(['article_id' => $articleId]);
            }

            $this->pdo->commit();

            return true;
        } catch (Throwable $e) {
            log()->error(sprintf('ArticleRepository.deleteSpecificVersion failed: %s', $e->getMessage()), ['exception' => $e]);
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return false;
        }
    }

    private function resolveActiveLanguage(): string
    {
        return cms()->settings()->currentLanguage();
    }
}
