<?php

declare(strict_types=1);

namespace NixCMS\Handlers\Api\Ai;

use NixCMS\Handlers\Support\ReadsJsonBody;
use NixCMS\Services\Ai\LocalAiToolBridge;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use function NixPHP\json;

final class CallAiTool
{
    use ReadsJsonBody;

    public function __invoke(): ResponseInterface
    {
        $body = $this->jsonBody();
        $name = trim((string) ($body['name'] ?? ''));
        $arguments = $body['arguments'] ?? $body['args'] ?? [];

        if ($name === '') {
            return json([
                'success' => true,
                'tool' => '',
                'result' => [
                    'success' => false,
                    'error' => 'Tool name is required.',
                ],
            ]);
        }

        if (!is_array($arguments)) {
            return json([
                'success' => true,
                'tool' => $name,
                'result' => [
                    'success' => false,
                    'error' => 'Tool arguments must be an object.',
                ],
            ]);
        }

        try {
            return json([
                'success' => true,
                'tool' => $name,
                'result' => (new LocalAiToolBridge())->call($name, $arguments),
            ]);
        } catch (Throwable $exception) {
            return json([
                'success' => true,
                'tool' => $name,
                'result' => [
                    'success' => false,
                    'error' => $exception->getMessage(),
                ],
            ]);
        }
    }
}
