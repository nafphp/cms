<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Ai;

use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Services\Ai\LocalAiPromptLibrary;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use function NixPHP\json;

final class AppendAiMemory
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $body = $this->jsonBody();
        $memory = trim((string) ($body['memory'] ?? ''));
        $resolvedMistakeIds = is_array($body['resolvedMistakeIds'] ?? null) ? $body['resolvedMistakeIds'] : [];

        if ($memory === '') {
            return json([
                'success' => false,
                'reason' => 'Memory is required.',
            ], 400);
        }

        try {
            $library = new LocalAiPromptLibrary();
            $content = $library->appendMemory($memory);
            $mistakes = $library->removeMistakeEntries($resolvedMistakeIds);
        } catch (Throwable $exception) {
            return json([
                'success' => false,
                'reason' => $exception->getMessage(),
            ], 500);
        }

        return json([
            'success' => true,
            'memory' => $content,
            'mistakes' => $mistakes,
        ]);
    }
}
