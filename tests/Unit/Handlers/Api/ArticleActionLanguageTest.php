<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers\Api;

use Naf\CMS\Api\ArticleApi;
use Naf\CMS\Content\ContentTypeInterface;
use Naf\CMS\Enums\ArticleStatus;
use Naf\CMS\Handlers\Api\Article\DeleteArticleLocale;
use Naf\CMS\Handlers\Api\Article\DeleteArticleVersion;
use Naf\CMS\Handlers\Api\Article\PublishArticle;
use Naf\CMS\Models\Article;
use Naf\CMS\Repositories\ArticleRepository;
use Naf\CMS\Support\Cms;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\NafTestCase;

use function Naf\CMS\cms;

final class ArticleActionLanguageTest extends NafTestCase
{
    private Cms $previousCms;
    private ArticleRepository&MockObject $repository;

    protected function setUp(): void
    {
        $this->previousCms = cms();
        $this->repository  = $this->createMock(ArticleRepository::class);
        $api               = new ArticleApi($this->repository);
        $type              = new class ($api) implements ContentTypeInterface {
            public function __construct(private ArticleApi $articleApi)
            {
            }

            public function id(): string
            {
                return 'article';
            }

            public function label(): string
            {
                return 'Article';
            }

            /** @return class-string<Article> */
            public function modelClass(): string
            {
                return Article::class;
            }

            public function api(): ArticleApi
            {
                return $this->articleApi;
            }
        };
        cms(override: new Cms())->contentTypes()->add($type);
    }

    protected function tearDown(): void
    {
        cms(override: $this->previousCms);
    }

    public function testPublishPassesTheNormalizedLanguageCodeToTheContentApi(): void
    {
        $this->repository->expects(self::once())->method('publishLatestLanguageVersion')->with(1, 'de')->willReturn(true);

        $this->assertSuccessful((new PublishArticle())('1', 'DE'));
    }

    public function testDeleteLocalePassesTheNormalizedLanguageCodeToTheContentApi(): void
    {
        $this->repository->expects(self::once())->method('deleteLanguageVersions')->with(1, 'en')->willReturn(true);

        $this->assertSuccessful((new DeleteArticleLocale())('1', 'EN'));
    }

    public function testDeleteDraftVersionPassesTheNormalizedLanguageCodeToTheContentApi(): void
    {
        $article = $this->mockArticle(['tags' => ['fixture']]);
        $this->repository->method('findWithSpecificVersion')->with(1, 'de', 1)->willReturn($article);
        $this->repository->expects(self::once())->method('deleteSpecificVersion')->with(1, 'de', 1)->willReturn(true);

        $this->assertSuccessful((new DeleteArticleVersion())('1', 'DE', '1'));
    }

    public function testPublishedVersionRemainsProtectedFromDeletion(): void
    {
        $article = $this->mockArticle([], $this->mockArticleVersion(['status' => ArticleStatus::PUBLISHED->value]));
        $this->repository->method('findWithSpecificVersion')->with(1, 'de', 1)->willReturn($article);
        $this->repository->expects(self::never())->method('deleteSpecificVersion');

        $response = (new DeleteArticleVersion())('1', 'de', '1');

        self::assertSame(400, $response->getStatusCode());
        self::assertFalse(json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['success']);
    }

    private function assertSuccessful(ResponseInterface $response): void
    {
        self::assertSame(200, $response->getStatusCode());
        self::assertTrue(json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['success']);
    }
}
