<?php

declare(strict_types=1);

namespace src\Handlers\Api\Article;

use src\Models\Article;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use function NixPHP\json;
use function NixCMS\cms;

final class PublishArticle
{
    public function __invoke(string $articleId, string $language): ResponseInterface
    {
        $lang = cms()->languages()->normalize($language);
        if ($lang === null || !cms()->languages()->isKnown($lang) || (int) $articleId <= 0) {
            return json(['success' => false, 'reason' => 'Invalid article or language.'], 400);
        }

        try {
            return json([
                'success' => cms()->content(Article::class)->publish((int) $articleId, $lang->value),
            ]);
        } catch (Throwable $exception) {
            return json(['success' => false, 'reason' => $exception->getMessage()], 500);
        }
    }
}
