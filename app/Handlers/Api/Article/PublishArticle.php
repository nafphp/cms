<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Article;

use NixCMS\Enums\Language;
use NixCMS\Models\Article;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use function NixPHP\json;

final class PublishArticle
{
    public function __invoke(string $articleId, string $language): ResponseInterface
    {
        $lang = Language::tryFrom(strtolower(trim($language)));
        if (!$lang instanceof Language || (int) $articleId <= 0) {
            return json(['success' => false, 'reason' => 'Invalid article or language.'], 400);
        }

        try {
            return json([
                'success' => \cms()->content(Article::class)->publish((int) $articleId, $lang->value),
            ]);
        } catch (Throwable $exception) {
            return json(['success' => false, 'reason' => $exception->getMessage()], 500);
        }
    }
}
