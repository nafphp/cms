<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Admin;

use NixCMS\Models\Article;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixCMS\cms;

final class ListArticles
{
    public function __invoke(): ResponseInterface
    {
        return json(cms()->contentTypes()->requireByModel(Article::class)->catalog()->overview()['articles']);
    }
}
