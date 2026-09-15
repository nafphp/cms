<?php

declare(strict_types=1);

namespace Naf\CMS\Handlers\Api\Ai;

use Naf\CMS\Services\Ai\LocalAiPromptLibrary;
use Psr\Http\Message\ResponseInterface;
use Throwable;

use function Naf\json;

final class ListAiPromptFiles
{
    public function __invoke(): ResponseInterface
    {
        try {
            return json([
                'success' => true,
                'files'   => (new LocalAiPromptLibrary())->promptFiles(),
            ]);
        } catch (Throwable $exception) {
            return json([
                'success' => false,
                'reason'  => $exception->getMessage(),
            ], 500);
        }
    }
}
