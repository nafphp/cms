<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Settings;

use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Services\Settings\TranslationFileService;
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
