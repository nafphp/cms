<?php

declare(strict_types=1);

namespace NixPHP\CMS\Handlers\Api\Settings;

use NixPHP\CMS\Contracts\TranslationServiceInterface;
use NixPHP\CMS\Handlers\Support\ReadsJsonBody;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;

final class UpdateTranslations
{
    use ReadsJsonBody;

    public function __construct(private readonly TranslationServiceInterface $translations) {}

    public function __invoke(string $language): ResponseInterface
    {
        $payload = $this->jsonBody();
        $entries = is_array($payload['entries'] ?? null) ? $payload['entries'] : [];
        // "replace" stays the default so the translation editor, which posts the
        // complete set and needs to be able to drop keys, keeps working as is.
        $result = strtolower(trim((string) ($payload['mode'] ?? 'replace'))) === 'merge'
            ? $this->translations->merge(
                $language,
                $entries,
                is_array($payload['remove'] ?? null) ? $payload['remove'] : [],
            )
            : $this->translations->save($language, $entries);

        if (!$result['success']) {
            return json([
                'success' => false,
                'errors' => $result['errors'],
                'reason' => 'Could not save translations.',
            ], 400);
        }

        return json([
            'success' => true,
            'changed' => $result['changed'],
        ]);
    }
}
