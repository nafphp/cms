<?php

declare(strict_types=1);

namespace NixCMS\Api;

use NixCMS\Enums\Language;
use NixCMS\Models\Article;
use NixCMS\Models\ArticleVersion;
use NixCMS\Repositories\ArticleRepository;

/**
 * Domain API for articles. Exposes article-specific behaviour and delegates persistence/queries to ArticleRepository.
 */
final class ArticleApi
{
    public function __construct(
        private ArticleRepository $articleRepository,
    ) {}

    /**
     * @param array<string, mixed> $data
     * @return array{articleId:int,articleVersionId:int}
     */
    public function create(array $data, ?int $authorId = null): array
    {
        $data['article_id'] = 0;
        return $this->articleRepository->saveDraftFromPayload($data, $authorId);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{articleId:int,articleVersionId:int}
     */
    public function draft(int $id, array $data, ?int $authorId = null): array
    {
        $data['article_id'] = $id;
        return $this->articleRepository->saveDraftFromPayload($data, $authorId);
    }

    public function publish(int $id, string $locale = 'de'): bool
    {
        return $this->articleRepository->publishLatestLanguageVersion($id, $this->resolveLocale($locale));
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
            throw new \RuntimeException(
                "Version not found for article {$articleId}, locale {$locale}, version {$versionNumber}"
            );
        }

        $content = $version->getContent();
        $decodedContent = $content !== null
            ? json_decode($content, true, 512, JSON_THROW_ON_ERROR)
            : [];

        return $this->articleRepository->saveDraftFromPayload([
            'article_id' => $articleId,
            'title' => $version->getTitle() ?? '',
            'slug' => $version->getSlug() ?? '',
            'teaser' => $version->getTeaser() ?? '',
            'content' => $decodedContent,
            'language' => $locale,
            'show_teaser' => $version->shouldShowTeaser(),
            'hide_lead_image_in_content' => $article->shouldHideLeadImageInContent(),
            'tags' => $article->getTagNames(),
            'category' => $article->getCategory()?->getName() ?? '',
            'publish_date' => $version->getPublishedAt()?->format('Y-m-d H:i:s') ?? '',
        ]);
    }

    public function deleteLocale(int $articleId, string $locale): bool
    {
        return $this->articleRepository->deleteLanguageVersions($articleId, $this->resolveLocale($locale));
    }

    public function deleteVersion(int $articleId, string $locale, int $versionNumber): bool
    {
        return $this->articleRepository->deleteSpecificVersion(
            $articleId,
            $this->resolveLocale($locale),
            $versionNumber,
        );
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

    private function resolveLocale(string $locale): Language
    {
        $language = Language::tryFrom(strtolower($locale));
        if (!$language instanceof Language) {
            throw new \InvalidArgumentException("Unknown locale: {$locale}");
        }

        return $language;
    }
}
