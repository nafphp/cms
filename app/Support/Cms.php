<?php

declare(strict_types=1);

namespace NixCMS\Support;

use NixCMS\Api\ArticleApi;
use NixCMS\Api\ApiRegistry;
use NixCMS\Content\ContentRegistry;
use NixCMS\Models\Article;
use NixCMS\Page\PageRegistry;

final class Cms
{
    private ?ApiRegistry $apiRegistry = null;
    private ?ContentRegistry $contentRegistry = null;
    private ?PageRegistry $pageRegistry = null;

    public function pages(): PageRegistry
    {
        return $this->pageRegistry ??= new PageRegistry();
    }

    public function contentTypes(): ContentRegistry
    {
        if ($this->contentRegistry === null) {
            $this->contentRegistry = new ContentRegistry();
            $contentFile = BASE_PATH . '/app/content.php';
            if (is_file($contentFile)) {
                $this->contentRegistry->addMany(require $contentFile);
            }
        }

        return $this->contentRegistry;
    }

    public function api(): ApiRegistry
    {
        return $this->apiRegistry ??= new ApiRegistry();
    }

    public function articles(): ArticleApi
    {
        /** @var ArticleApi $api */
        $api = $this->content(Article::class);
        return $api;
    }

    /**
     * @param class-string $class
     */
    public function content(string $class): object
    {
        $type = $this->contentTypes()->findByModel($class);
        if ($type !== null) {
            return $type->api();
        }

        throw new \InvalidArgumentException(sprintf(
            'No content type registered for "%s". Register one through cms()->contentTypes()->add(...).',
            $class
        ));
    }
}
