<?php

declare(strict_types=1);

namespace NixPHP\CMS\Handlers\Api\Settings;

use NixPHP\CMS\Handlers\Support\ReadsJsonBody;
use NixPHP\CMS\Services\Settings\TranslationFileService;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;

final class UpdateTranslations
{
    use ReadsJsonBody;

    public function __invoke(string $language): ResponseInterface
    {
        $payload = $this->jsonBody();
        $entries = is_array($payload['entries'] ?? null) ? $payload['entries'] : [];
        $result = (new TranslationFileService())->save($language, $entries);

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
