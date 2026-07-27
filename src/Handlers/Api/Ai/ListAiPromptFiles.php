<?php

declare(strict_types=1);

namespace src\Handlers\Api\Ai;

use src\Services\Ai\LocalAiPromptLibrary;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use function NixPHP\json;

final class ListAiPromptFiles
{
    public function __invoke(): ResponseInterface
    {
        try {
            return json([
                'success' => true,
                'files' => (new LocalAiPromptLibrary())->promptFiles(),
            ]);
        } catch (Throwable $exception) {
            return json([
                'success' => false,
                'reason' => $exception->getMessage(),
            ], 500);
        }
    }
}
