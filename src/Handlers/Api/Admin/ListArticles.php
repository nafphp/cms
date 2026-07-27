<?php

declare(strict_types=1);

namespace NixPHP\CMS\Handlers\Api\Admin;

use NixPHP\CMS\Models\Article;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\CMS\cms;

final class ListArticles
{
    public function __invoke(): ResponseInterface
    {
        return json(cms()->contentTypes()->requireByModel(Article::class)->catalog()->overview()['articles']);
    }
}
