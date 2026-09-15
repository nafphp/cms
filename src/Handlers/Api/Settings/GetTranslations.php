<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Settings;

use Naf\CMS\Contracts\TranslationServiceInterface;
use Psr\Http\Message\ResponseInterface;

use function Naf\CMS\cms;
use function Naf\json;

final class GetTranslations
{
    public function __construct(private readonly TranslationServiceInterface $translations)
    {
    }

    public function __invoke(string $language): ResponseInterface
    {
        $language = cms()->languages()->normalize($language);
        if ($language === null) {
            return json(['success' => false, 'reason' => 'Invalid language.'], 400);
        }

        return json([
            'success'  => true,
            'language' => $language,
            'entries'  => $this->translations->entries($language),
        ]);
    }
}
