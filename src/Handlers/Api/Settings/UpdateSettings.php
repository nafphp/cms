<?php

declare(strict_types=1);

namespace NixPHP\CMS\Handlers\Api\Settings;

use NixPHP\CMS\Handlers\Support\ReadsJsonBody;
use NixPHP\CMS\Services\Settings\SettingsService;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;

final class UpdateSettings
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $result = (new SettingsService())->save($this->jsonBody());

        if (!$result['success']) {
            return json([
                'success' => false,
                'errors' => $result['errors'],
                'reason' => $result['errors'] === [] ? 'Could not save settings.' : 'Please check the highlighted settings.',
            ], 400);
        }

        return json(['success' => true]);
    }
}
