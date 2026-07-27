<?php

declare(strict_types=1);

namespace NixPHP\CMS\Handlers\Api\Ai;

use NixPHP\CMS\Services\Ai\LocalAiPromptLibrary;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use function NixPHP\json;

final class ReviewAiMistakes
{
    public function __invoke(): ResponseInterface
    {
        try {
            $library = new LocalAiPromptLibrary();
            $mistakes = $library->mistakesLog();

            return json([
                'success' => true,
                'mistakes' => $mistakes,
                'mistakeEntries' => $library->mistakeEntries(),
                'memory' => $library->memory(),
                'hasMistakes' => trim($mistakes) !== '',
            ]);
        } catch (Throwable $exception) {
            return json([
                'success' => false,
                'reason' => $exception->getMessage(),
            ], 500);
        }
    }
}
