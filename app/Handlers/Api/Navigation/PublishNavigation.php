<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Navigation;

use Psr\Http\Message\ResponseInterface;
use Throwable;
use function NixPHP\json;
use function NixCMS\cms;

final class PublishNavigation
{
    public function __invoke(string $navigationId): ResponseInterface
    {
        if ((int) $navigationId <= 0) {
            return json(['success' => false, 'reason' => 'Invalid navigation.'], 400);
        }

        try {
            return json([
                'success' => cms()->navigation()->publish((int) $navigationId),
            ]);
        } catch (Throwable $exception) {
            return json(['success' => false, 'reason' => $exception->getMessage()], 500);
        }
    }
}
