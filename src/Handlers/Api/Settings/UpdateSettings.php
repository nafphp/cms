<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Settings;

use Naf\CMS\Contracts\SettingsServiceInterface;
use Naf\CMS\Handlers\Support\ReadsJsonBody;
use Psr\Http\Message\ResponseInterface;
use function Naf\json;

final class UpdateSettings
{
    use ReadsJsonBody;

    public function __construct(private readonly SettingsServiceInterface $settings) {}

    public function __invoke(): ResponseInterface
    {
        $result = $this->settings->save($this->jsonBody());

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
