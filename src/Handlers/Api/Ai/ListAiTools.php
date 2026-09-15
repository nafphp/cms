<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Ai;

use Naf\CMS\Services\Ai\LocalAiToolBridge;
use Psr\Http\Message\ResponseInterface;
use Throwable;

use function Naf\json;

final class ListAiTools
{
    public function __invoke(): ResponseInterface
    {
        try {
            return json([
                'success' => true,
                'tools'   => (new LocalAiToolBridge())->definitions(),
            ]);
        } catch (Throwable $exception) {
            return json([
                'success' => false,
                'reason'  => $exception->getMessage(),
                'tools'   => [],
            ], 500);
        }
    }
}
