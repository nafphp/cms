<?php

declare(strict_types=1);

namespace src\Handlers\Api\Ai;

use src\Handlers\Support\ReadsJsonBody;
use src\Services\Ai\LocalAiPromptLibrary;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use function NixPHP\json;

final class RecordAiMistake
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $body = $this->jsonBody();
        $event = trim((string) ($body['event'] ?? ''));
        $context = trim((string) ($body['context'] ?? ''));
        $correction = trim((string) ($body['correction'] ?? ''));

        if ($event === '') {
            return json([
                'success' => false,
                'reason' => 'Event is required.',
            ], 400);
        }

        try {
            (new LocalAiPromptLibrary())->appendMistake($event, $context, $correction);
        } catch (Throwable $exception) {
            return json([
                'success' => false,
                'reason' => $exception->getMessage(),
            ], 500);
        }

        return json(['success' => true]);
    }
}
