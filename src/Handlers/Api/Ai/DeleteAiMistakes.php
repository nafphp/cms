<?php

declare(strict_types=1);

namespace NixPHP\CMS\Handlers\Api\Ai;

use NixPHP\CMS\Handlers\Support\ReadsJsonBody;
use NixPHP\CMS\Services\Ai\LocalAiPromptLibrary;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use function NixPHP\json;

final class DeleteAiMistakes
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $body = $this->jsonBody();
        $ids = $body['mistakeIds'] ?? $body['ids'] ?? [];
        $ids = is_array($ids) ? array_values(array_filter(array_map('strval', $ids))) : [];

        if ($ids === []) {
            return json([
                'success' => false,
                'reason' => 'At least one mistake id is required.',
            ], 400);
        }

        try {
            $library = new LocalAiPromptLibrary();
            $mistakes = $library->removeMistakeEntries($ids);

            return json([
                'success' => true,
                'mistakes' => $mistakes,
                'mistakeEntries' => $library->mistakeEntries(),
            ]);
        } catch (Throwable $exception) {
            return json([
                'success' => false,
                'reason' => $exception->getMessage(),
            ], 500);
        }
    }
}
