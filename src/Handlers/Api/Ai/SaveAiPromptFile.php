<?php

declare(strict_types=1);

namespace src\Handlers\Api\Ai;

use src\Handlers\Support\ReadsJsonBody;
use src\Services\Ai\LocalAiPromptLibrary;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use function NixPHP\json;

final class SaveAiPromptFile
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $body = $this->jsonBody();
        $file = trim((string) ($body['file'] ?? ''));
        $content = (string) ($body['content'] ?? '');

        if ($file === '') {
            return json([
                'success' => false,
                'reason' => 'Prompt file is required.',
            ], 400);
        }

        try {
            $saved = (new LocalAiPromptLibrary())->savePromptFile($file, $content);
        } catch (Throwable $exception) {
            return json([
                'success' => false,
                'reason' => $exception->getMessage(),
            ], 500);
        }

        return json([
            'success' => true,
            'file' => [
                'name' => $file,
                'content' => $saved,
            ],
        ]);
    }
}
