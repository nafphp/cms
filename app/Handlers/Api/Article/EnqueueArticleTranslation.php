<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Article;

use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;

final class EnqueueArticleTranslation
{
    public function __invoke(string $articleId): ResponseInterface
    {
        $id = (int) $articleId;

        return json([
            'success' => $id > 0,
            'queued' => $id > 0,
            'reason' => $id > 0 ? null : 'Invalid article id.',
        ]);
    }
}
