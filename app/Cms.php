<?php

declare(strict_types=1);

namespace Cms;

use Cms\Api\ArticleApi;
use Cms\Api\ContentApi;
use Cms\Models\Article;
use Cms\Repositories\ArticleRepository;
use Cms\Repositories\ArticleVersionRepository;
use NixPHP\ORM\Core\EntityInterface;
use function NixPHP\ORM\repo;

final class Cms
{
    private ?ArticleApi $articleApi = null;

    /** @var array<class-string, ContentApi<EntityInterface>> */
    private array $contentApis = [];

    public function articles(): ArticleApi
    {
        if ($this->articleApi === null) {
            $this->articleApi = new ArticleApi(
                repo(ArticleRepository::class),
                repo(ArticleVersionRepository::class),
            );
        }

        return $this->articleApi;
    }

    /**
     * @template T of EntityInterface
     * @param class-string<T> $class
     * @return ContentApi<T>
     */
    public function content(string $class): ContentApi
    {
        if ($class !== Article::class) {
            throw new \InvalidArgumentException(
                'Only Cms\Models\Article is currently supported. Add a domain implementation before introducing new content types.'
            );
        }

        if (!isset($this->contentApis[$class])) {
            $this->contentApis[$class] = new ContentApi(repo(ArticleRepository::class));
        }

        /** @var ContentApi<T> $api */
        $api = $this->contentApis[$class];
        return $api;
    }
}
