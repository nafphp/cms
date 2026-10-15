<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers\Api;

use Naf\CMS\Content\ContentTypeInterface;
use Naf\CMS\Handlers\Api\Admin\ListArticles;
use Naf\CMS\Handlers\Api\Article\NextArticleVersion;
use Naf\CMS\Models\Article;
use Naf\CMS\Services\Content\ArticleCatalogService;
use Naf\CMS\Services\Content\ArticleFormService;
use Naf\CMS\Support\Cms;
use PDO;
use Tests\Support\DatabaseTestCase;

use function Naf\CMS\cms;

final class ArticleContentReplacementTest extends DatabaseTestCase
{
    private Cms $previousCms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema(
            'CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, firstname TEXT, lastname TEXT)',
            'CREATE TABLE articles (id INTEGER PRIMARY KEY, author INTEGER, createdAt TEXT)',
            'CREATE TABLE article_versions (id INTEGER PRIMARY KEY, article_id INTEGER, language TEXT, version INTEGER, status TEXT, slug TEXT, title TEXT, publishedAt TEXT, scheduledAt TEXT, createdAt TEXT)',
        );
        $this->insertRow('articles', ['id' => 42]);
        $this->insertRow('article_versions', [
            'id'     => 7, 'article_id' => 42, 'language' => 'en', 'version' => 4,
            'status' => 'draft', 'slug' => 'replacement', 'title' => 'Replacement article',
        ]);
        $this->previousCms = cms();
        $original          = $this->previousCms->contentTypes()->requireByModel(Article::class);
        $replacement       = new class ($original, $this->pdo) implements ContentTypeInterface {
            public function __construct(private ContentTypeInterface $original, private PDO $connection)
            {
            }

            public function id(): string
            {
                return $this->original->id();
            }

            public function label(): string
            {
                return 'Replacement article';
            }

            /** @return class-string<Article> */
            public function modelClass(): string
            {
                return Article::class;
            }

            public function api(): object
            {
                return $this->original->api();
            }

            public function catalog(): ArticleCatalogService
            {
                return new ArticleCatalogService($this->connection);
            }

            public function form(): ArticleFormService
            {
                return new ArticleFormService($this->connection);
            }
        };
        $cms = new Cms();
        $cms->contentTypes()->add($original)->add($replacement, replace: true);
        cms(override: $cms);
    }

    protected function tearDown(): void
    {
        cms(override: $this->previousCms);
        parent::tearDown();
    }

    public function testArticleListUsesTheRegisteredReplacementCatalog(): void
    {
        $response = (new ListArticles())();

        self::assertSame(200, $response->getStatusCode());
        $rows = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(42, $rows[0]['id']);
        self::assertSame('Replacement article', $rows[0]['title']);
    }

    public function testNextVersionUsesTheRegisteredReplacementFormService(): void
    {
        $response = (new NextArticleVersion())('42', 'en');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['success' => true, 'version' => 5], json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }
}
