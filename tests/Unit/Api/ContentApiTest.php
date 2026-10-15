<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use Naf\CMS\Api\ContentApi;
use Naf\CMS\Repositories\ArticleRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Support\NafTestCase;

final class ContentApiTest extends NafTestCase
{
    private ArticleRepository&MockObject $repository;

    /** @var ContentApi */
    private ContentApi $contentApi;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ArticleRepository::class);
        $this->contentApi = new ContentApi($this->repository);
    }

    public function testFindDelegatesToRepositoryFindOneByOnId(): void
    {
        $article = $this->mockArticle(['id' => 7]);

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
        $articles = [
            $this->mockArticle(['id' => 1]),
            $this->mockArticle(['id' => 2]),
        ];

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
        $article = $this->mockArticle(['id' => 3]);

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
        $articles = [$this->mockArticle(['id' => 1])];

        $this->repository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($articles);

        self::assertSame($articles, $this->contentApi->findAll());
    }
}
