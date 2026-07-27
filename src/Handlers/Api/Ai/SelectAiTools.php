<?php

declare(strict_types=1);

namespace src\Handlers\Api\Ai;

use src\Handlers\Support\ReadsJsonBody;
use src\Services\Ai\LocalAiToolBridge;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use function NixPHP\json;

final class SelectAiTools
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $body = $this->jsonBody();
        $message = trim((string) ($body['message'] ?? $body['input'] ?? ''));
        $context = $body['context'] ?? [];
        $limit = (int) ($body['limit'] ?? 12);

        if (!is_array($context)) {
            $context = [];
        }

        try {
            return json([
                'success' => true,
                'tools' => (new LocalAiToolBridge())->selectDefinitions($message, $context, $limit),
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
