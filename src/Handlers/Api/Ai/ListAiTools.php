<?php

declare(strict_types=1);

namespace src\Handlers\Api\Ai;

use src\Services\Ai\LocalAiToolBridge;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use function NixPHP\json;

final class ListAiTools
{
    public function __invoke(): ResponseInterface
    {
        try {
            return json([
                'success' => true,
                'tools' => (new LocalAiToolBridge())->definitions(),
            ]);
        } catch (Throwable $exception) {
            return json([
                'success' => false,
                'reason' => $exception->getMessage(),
                'tools' => [],
            ], 500);
        }
    }
}
