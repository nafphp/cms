<?php

declare(strict_types=1);

namespace NixPHP\CMS\Handlers\Api\Settings;

use NixPHP\CMS\Contracts\TranslationServiceInterface;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\CMS\cms;

final class GetTranslations
{
    public function __construct(private readonly TranslationServiceInterface $translations) {}

    public function __invoke(string $language): ResponseInterface
    {
        $language = cms()->languages()->normalize($language);
        if ($language === null) {
            return json(['success' => false, 'reason' => 'Invalid language.'], 400);
        }

        return json([
            'success' => true,
            'language' => $language,
            'entries' => $this->translations->entries($language),
        ]);
    }
}
