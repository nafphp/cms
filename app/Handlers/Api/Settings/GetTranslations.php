<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Settings;

use NixCMS\Services\Settings\TranslationFileService;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;

final class GetTranslations
{
    public function __invoke(string $language): ResponseInterface
    {
        $language = \cms()->languages()->normalize($language);
        if ($language === null) {
            return json(['success' => false, 'reason' => 'Invalid language.'], 400);
        }

        return json([
            'success' => true,
            'language' => $language,
            'entries' => (new TranslationFileService())->entries($language),
        ]);
    }
}
