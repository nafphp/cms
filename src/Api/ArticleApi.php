<?php

declare(strict_types=1);

namespace Naf\CMS\Api;

use InvalidArgumentException;
use Naf\CMS\Localization\LanguageRegistry;
use Naf\CMS\Models\Article;
use Naf\CMS\Models\ArticleVersion;
use Naf\CMS\Repositories\ArticleRepository;
use Naf\CMS\Storage\EntityStorage;
use Naf\CMS\Support\ContentChange;
use RuntimeException;
use Throwable;

/**
 * Domain API for articles. Exposes article-specific behaviour and delegates persistence/queries to ArticleRepository.
 *
 * Data access path: ContentApi → Storage<Article> → ArticleRepository → db()
 * Use ->storage() for generic Criteria-based reads, ->repository() for joins, aggregates, and version-aware queries.
 */
final class ArticleApi
{
    /**
     * @var EntityStorage<Article>|null
     */
    private ?EntityStorage $storage = null;

    public function __construct(
        private ArticleRepository $articleRepository,
    ) {
    }

    /**
     * @return EntityStorage<Article>
     */
    public function storage(): EntityStorage
    {
        return $this->storage ??= new EntityStorage(Article::class);
    }

    public function repository(): ArticleRepository
    {
        return $this->articleRepository;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{articleId:int,articleVersionId:int}
     */
    public function create(array $data, ?int $authorId = null): array
    {
        $data['article_id'] = 0;
        $result             = $this->articleRepository->saveDraftFromPayload($data, $authorId);

        ContentChange::record(
            'article.created',
            'article',
            (string) $result['articleId'],
            null,
            $this->articleSnapshot($result['articleId'], (string) ($data['language'] ?? '')),
            ['article_version_id' => $result['articleVersionId']],
        );

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{articleId:int,articleVersionId:int}
     */
    public function draft(int $id, array $data, ?int $authorId = null): array
    {
        $old                = $this->articleSnapshot($id, (string) ($data['language'] ?? ''));
        $data['article_id'] = $id;
        $result             = $this->articleRepository->saveDraftFromPayload($data, $authorId);

        ContentChange::record(
            'article.draft_saved',
            'article',
            (string) $result['articleId'],
            $old,
            $this->articleSnapshot($result['articleId'], (string) ($data['language'] ?? '')),
            ['article_version_id' => $result['articleVersionId']],
        );

        return $result;
    }

    public function publish(int $id, string $locale = 'de'): bool
    {
        $language  = $this->resolveLocale($locale);
        $old       = $this->articleSnapshot($id, $language);
        $published = $this->articleRepository->publishLatestLanguageVersion($id, $language);
        if ($published) {
            ContentChange::record(
                'article.published',
                'article',
                (string) $id,
                $old,
                $this->articleSnapshot($id, $language),
                ['language' => $language],
            );
        }

        return $published;
    }

    /**
     * Snapshot of one article language in the shape audit entries and content
     * events carry
     *
     * @internal For CMS handlers that publish outside publish()
     *
     * @return array<string, mixed>|null
     */
    public function snapshot(int $id, string $locale): ?array
    {
        return $this->articleSnapshot($id, $this->snapshotLocale($locale));
    }

    /**
     * Report a publication that did not go through publish()
     *
     * A review approval publishes the reviewed version inside its own
     * transaction; this records the same `article.published` change that
     * publish() records, so listeners see every publication.
     *
     * @internal For CMS handlers that publish outside publish()
     *
     * @param array<string, mixed>|null $before Snapshot taken before the change
     */
    public function recordPublished(int $id, string $locale, ?array $before, string $source): void
    {
        $language = $this->snapshotLocale($locale);

        ContentChange::record(
            'article.published',
            'article',
            (string) $id,
            $before,
            $this->articleSnapshot($id, $language),
            ['language' => $language, 'source' => $source],
        );
    }

    public function find(int $id, ?string $locale = null): ?Article
    {
        return $this->articleRepository->findWithVersion($id, $locale);
    }

    public function findPublished(int $id, ?string $locale = null): ?Article
    {
        return $this->articleRepository->findPublishedVisibleWithVersion($id, $locale);
    }

    public function findVersion(int $id, string $locale, int $versionNumber): ?Article
    {
        return $this->articleRepository->findWithSpecificVersion($id, $locale, $versionNumber);
    }

    /**
     * @return array<int, ArticleVersion>
     */
    public function versions(int $id): array
    {
        $article = $this->articleRepository->findOneBy('id', $id);
        if (!$article instanceof Article) {
            return [];
        }

        return $article->getVersions();
    }

    /**
     * Restore a specific version as a new draft.
     *
     * @return array{articleId:int,articleVersionId:int}
     */
    public function restoreVersion(int $articleId, string $locale, int $versionNumber): array
    {
        $article = $this->articleRepository->findWithSpecificVersion($articleId, $locale, $versionNumber);
        $version = $article?->getVersion();
        if (!$article instanceof Article || !$version instanceof ArticleVersion) {
            throw new RuntimeException(
                "Version not found for article {$articleId}, locale {$locale}, version {$versionNumber}",
            );
        }

        $content        = $version->getContent();
        $decodedContent = $content !== null
            ? json_decode($content, true, 512, JSON_THROW_ON_ERROR)
            : [];

        $result = $this->articleRepository->saveDraftFromPayload([
            'article_id'                 => $articleId,
            'title'                      => $version->getTitle() ?? '',
            'slug'                       => $version->getSlug() ?? '',
            'teaser'                     => $version->getTeaser() ?? '',
            'content'                    => $decodedContent,
            'language'                   => $locale,
            'show_teaser'                => $version->shouldShowTeaser(),
            'hide_lead_image_in_content' => $article->shouldHideLeadImageInContent(),
            'tags'                       => $article->getTagNames(),
            'category'                   => $article->getCategory()?->getName() ?? '',
            'publish_date'               => $version->getPublishedAt()?->format('Y-m-d H:i:s') ?? '',
        ]);

        ContentChange::record(
            'article.version_restored',
            'article',
            (string) $articleId,
            $this->articleVersionSnapshot($version),
            $this->articleSnapshot($articleId, $locale),
            [
                'language'           => $locale,
                'restored_version'   => $versionNumber,
                'article_version_id' => $result['articleVersionId'],
            ],
        );

        return $result;
    }

    public function deleteLocale(int $articleId, string $locale): bool
    {
        $language = $this->resolveLocale($locale);
        $old      = $this->articleSnapshot($articleId, $language);
        $deleted  = $this->articleRepository->deleteLanguageVersions($articleId, $language);
        if ($deleted) {
            ContentChange::record(
                'article.locale_deleted',
                'article',
                (string) $articleId,
                $old,
                null,
                ['language' => $language],
            );
        }

        return $deleted;
    }

    public function deleteVersion(int $articleId, string $locale, int $versionNumber): bool
    {
        $language = $this->resolveLocale($locale);
        $old      = $this->articleSnapshot($articleId, $language, $versionNumber);
        $deleted  = $this->articleRepository->deleteSpecificVersion(
            $articleId,
            $language,
            $versionNumber,
        );
        if ($deleted) {
            ContentChange::record(
                'article.version_deleted',
                'article',
                (string) $articleId,
                $old,
                null,
                ['language' => $language, 'version' => $versionNumber],
            );
        }

        return $deleted;
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
        return $this->articleRepository->getDraftReviewQueue($limit);
    }

    /**
     * @return array<string, int>
     */
    public function getStatusOverview(?int $authorId = null, ?string $locale = null): array
    {
        return $this->articleRepository->getArticleStatusOverview($authorId, $locale);
    }

    /**
     * @return array<string, int>
     */
    public function getVersionStatusOverview(): array
    {
        return $this->articleRepository->getVersionStatusOverview();
    }

    /**
     * @return array<int, array{article:Article}>
     */
    public function findReadingRecommendations(Article $article, int $limit = 2): array
    {
        return $this->articleRepository->findReadingRecommendations($article, $limit);
    }

    /**
     * @return array<int, Article>
     */
    public function findAllWithLatestVersion(): array
    {
        return $this->articleRepository->findAllWithLatestVersion();
    }

    /**
     * @param int[] $excludeIds
     * @return array<int, Article>
     */
    public function findPublishedByCategory(?int $categoryId, ?int $limit = null, array $excludeIds = []): array
    {
        return $this->articleRepository->findPublishedWithLatestVersionByCategory($categoryId, $limit, $excludeIds);
    }

    private function snapshotLocale(string $locale): string
    {
        try {
            return $this->resolveLocale($locale);
        } catch (InvalidArgumentException) {
            return strtolower(trim($locale));
        }
    }

    private function resolveLocale(string $locale): string
    {
        $languages = new LanguageRegistry();
        $language  = $languages->normalize($locale);
        if ($language !== null && $languages->isKnown($language)) {
            return $language;
        }

        throw new InvalidArgumentException("Unknown locale: {$locale}");
    }

    /**
     * @return array<string, mixed>|null
     */
    private function articleSnapshot(int $id, ?string $locale = null, ?int $versionNumber = null): ?array
    {
        try {
            $article = $versionNumber !== null
                ? $this->findVersion($id, (string) $locale, $versionNumber)
                : $this->find($id, $locale);
        } catch (Throwable) {
            return null;
        }

        if (!$article instanceof Article) {
            return null;
        }

        $version = $article->getVersion();

        return [
            'id'                         => $article->getId(),
            'category'                   => $article->getCategory()?->getName(),
            'tags'                       => $article->getTagNames(),
            'hide_lead_image_in_content' => $article->shouldHideLeadImageInContent(),
            'version'                    => $version instanceof ArticleVersion ? $this->articleVersionSnapshot($version) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function articleVersionSnapshot(ArticleVersion $version): array
    {
        return [
            'id'           => $version->getId(),
            'language'     => $version->getLanguageCode(),
            'version'      => $version->getVersion(),
            'status'       => $version->getStatus()?->value,
            'title'        => $version->getTitle(),
            'slug'         => $version->getSlug(),
            'published_at' => $version->getPublishedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
