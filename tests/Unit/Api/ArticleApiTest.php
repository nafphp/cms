<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use NixCMS\Api\ArticleApi;
use NixCMS\Models\Article;
use NixCMS\Repositories\ArticleRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Support\NixPHPTestCase;

final class ArticleApiTest extends NixPHPTestCase
{
    private ArticleRepository&MockObject $articleRepository;
    private ArticleApi $articleApi;

    protected function setUp(): void
    {
        $this->articleRepository = $this->createMock(ArticleRepository::class);

        $this->articleApi = new ArticleApi($this->articleRepository);
    }

    public function testCreateSetsArticleIdToZeroAndCallsSaveDraft(): void
    {
        $data = ['title' => 'New', 'slug' => 'new'];
        $expected = ['articleId' => 1, 'articleVersionId' => 10];

        $this->articleRepository
            ->expects($this->once())
            ->method('saveDraftFromPayload')
            ->with(['article_id' => 0, 'title' => 'New', 'slug' => 'new'], 5)
            ->willReturn($expected);

        self::assertSame($expected, $this->articleApi->create($data, 5));
    }

    public function testDraftPreservesArticleIdAndCallsSaveDraft(): void
    {
        $data = ['title' => 'Updated', 'slug' => 'updated'];
        $expected = ['articleId' => 1, 'articleVersionId' => 11];

        $this->articleRepository
            ->expects($this->once())
            ->method('saveDraftFromPayload')
            ->with(['article_id' => 1, 'title' => 'Updated', 'slug' => 'updated'], 5)
            ->willReturn($expected);

        self::assertSame($expected, $this->articleApi->draft(1, $data, 5));
    }

    public function testPublishNormalizesLocaleString(): void
    {
        $this->articleRepository
            ->expects($this->once())
            ->method('publishLatestLanguageVersion')
            ->with(1, 'de')
            ->willReturn(true);

        self::assertTrue($this->articleApi->publish(1, 'de'));
    }

    public function testPublishWithDifferentLocale(): void
    {
        $this->articleRepository
            ->expects($this->once())
            ->method('publishLatestLanguageVersion')
            ->with(42, 'en')
            ->willReturn(true);

        self::assertTrue($this->articleApi->publish(42, 'en'));
    }

    public function testPublishThrowsForUnknownLocale(): void
    {
        $this->articleRepository
            ->expects($this->never())
            ->method('publishLatestLanguageVersion');

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Unknown locale: xx');

        $this->articleApi->publish(1, 'xx');
    }

    public function testDeleteLocaleNormalizesLanguage(): void
    {
        $this->articleRepository
            ->expects($this->once())
            ->method('deleteLanguageVersions')
            ->with(1, 'en')
            ->willReturn(true);

        self::assertTrue($this->articleApi->deleteLocale(1, 'en'));
    }

    public function testDeleteLocaleThrowsOnUnknownLocale(): void
    {
        $this->articleRepository
            ->expects($this->never())
            ->method('deleteLanguageVersions');

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Unknown locale: xx');

        $this->articleApi->deleteLocale(1, 'xx');
    }

    public function testDeleteVersionNormalizesLanguage(): void
    {
        $this->articleRepository
            ->expects($this->once())
            ->method('deleteSpecificVersion')
            ->with(1, 'fr', 2)
            ->willReturn(true);

        self::assertTrue($this->articleApi->deleteVersion(1, 'fr', 2));
    }

    public function testRestoreVersionThrowsWhenVersionNotFound(): void
    {
        $this->articleRepository
            ->expects($this->once())
            ->method('findWithSpecificVersion')
            ->with(1, 'de', 1)
            ->willReturn(null);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('Version not found');

        $this->articleApi->restoreVersion(1, 'de', 1);
    }

    public function testRestoreVersionThrowsWhenArticleHasNoVersion(): void
    {
        $article = $this->mockArticleWithoutVersion(['id' => 1]);

        $this->articleRepository
            ->expects($this->once())
            ->method('findWithSpecificVersion')
            ->with(1, 'de', 1)
            ->willReturn($article);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('Version not found');

        $this->articleApi->restoreVersion(1, 'de', 1);
    }

    public function testVersionsReturnsEmptyArrayIfArticleNotFound(): void
    {
        $this->articleRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with('id', 999)
            ->willReturn(null);

        self::assertSame([], $this->articleApi->versions(999));
    }

    public function testGetDraftReviewQueueForwardsLimit(): void
    {
        $expectedQueue = [
            [
                'article_id' => 1,
                'language' => 'de',
                'article_version_id' => 10,
                'version' => 1,
                'title' => 'Test Article',
                'slug' => 'test-article',
                'teaser' => 'A test article',
                'author_name' => 'John Doe',
                'published_at' => null,
                'created_at' => '2026-05-01 12:00:00',
                'last_review_action' => null,
                'last_review_comment' => null,
                'last_reviewed_at' => null,
                'last_reviewer_name' => null,
            ],
        ];

        $this->articleRepository
            ->expects($this->once())
            ->method('getDraftReviewQueue')
            ->with(5)
            ->willReturn($expectedQueue);

        self::assertSame($expectedQueue, $this->articleApi->getDraftReviewQueue(5));
    }

    public function testFindReadingRecommendationsReturnsRepositoryPayload(): void
    {
        $article = $this->mockArticle(['id' => 1]);
        $recommendations = [
            [
                'article' => $this->mockArticle(['id' => 2]),
                'sharedTagCount' => 2,
            ],
        ];

        $this->articleRepository
            ->expects($this->once())
            ->method('findReadingRecommendations')
            ->with($article, 2)
            ->willReturn($recommendations);

        self::assertSame($recommendations, $this->articleApi->findReadingRecommendations($article, 2));
    }

    public function testGetStatusOverviewForwardsAuthorAndLanguage(): void
    {
        $expectedOverview = [
            'draft' => 5,
            'review' => 2,
            'published' => 10,
        ];

        $this->articleRepository
            ->expects($this->once())
            ->method('getArticleStatusOverview')
            ->with(7, 'de')
            ->willReturn($expectedOverview);

        self::assertSame($expectedOverview, $this->articleApi->getStatusOverview(7, 'de'));
    }
}
