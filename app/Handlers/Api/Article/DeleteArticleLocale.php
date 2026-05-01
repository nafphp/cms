<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Article;

use NixCMS\Enums\Language;
use NixCMS\Models\Article;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;

final class DeleteArticleLocale
{
    public function __invoke(string $articleId, string $language): ResponseInterface
    {
        $lang = Language::tryFrom(strtolower(trim($language)));
        if (!$lang instanceof Language || (int) $articleId <= 0) {
            return json(['success' => false, 'reason' => 'Invalid article or language.'], 400);
        }

        return json([
            'success' => \cms()->content(Article::class)->deleteLocale((int) $articleId, $lang->value),
        ]);
    }
}
