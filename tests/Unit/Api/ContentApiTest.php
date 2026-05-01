<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use Cms\Api\ContentApi;
use Cms\Models\Article;
use Cms\Repositories\ArticleRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ContentApiTest extends TestCase
{
    private ArticleRepository&MockObject $repository;

    /** @var ContentApi<Article> */
    private ContentApi $contentApi;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ArticleRepository::class);
        $this->contentApi = new ContentApi($this->repository);
    }

    public function testFindDelegatesToRepositoryFindOneByOnId(): void
    {
        $article = new Article(['id' => 7]);

        $this->repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with('id', 7)
            ->willReturn($article);

        self::assertSame($article, $this->contentApi->find(7));
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with('id', 99)
            ->willReturn(null);

        self::assertNull($this->contentApi->find(99));
    }

    public function testFindByForwardsAllArguments(): void
    {
        $articles = [new Article(['id' => 1]), new Article(['id' => 2])];

        $this->repository
            ->expects($this->once())
            ->method('findBy')
            ->with(['author' => 5], null, ['id' => 'DESC'], 10, 20)
            ->willReturn($articles);

        $result = $this->contentApi->findBy(['author' => 5], null, ['id' => 'DESC'], 10, 20);

        self::assertSame($articles, $result);
    }

    public function testFindOneByForwardsCriteriaAndOrderBy(): void
    {
        $article = new Article(['id' => 3]);

        $this->repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['author' => 1], null, ['id' => 'DESC'])
            ->willReturn($article);

        $result = $this->contentApi->findOneBy(['author' => 1], null, ['id' => 'DESC']);

        self::assertSame($article, $result);
    }

    public function testFindAllDelegatesToRepository(): void
    {
        $articles = [new Article(['id' => 1])];

        $this->repository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($articles);

        self::assertSame($articles, $this->contentApi->findAll());
    }
}
