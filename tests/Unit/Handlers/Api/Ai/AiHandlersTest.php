<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers\Api\Ai;

use NixPHP\CMS\Handlers\Api\Ai\AppendAiMemory;
use NixPHP\CMS\Handlers\Api\Ai\CallAiTool;
use NixPHP\CMS\Handlers\Api\Ai\DeleteAiMistakes;
use NixPHP\CMS\Handlers\Api\Ai\ListAiPromptFiles;
use NixPHP\CMS\Handlers\Api\Ai\ListAiTools;
use NixPHP\CMS\Handlers\Api\Ai\RecordAiMistake;
use NixPHP\CMS\Handlers\Api\Ai\ReviewAiMistakes;
use NixPHP\CMS\Handlers\Api\Ai\SaveAiPromptFile;
use NixPHP\CMS\Handlers\Api\Ai\SelectAiTools;
use PHPUnit\Framework\TestCase;
use Tests\Support\JsonRequest;

final class AiHandlersTest extends TestCase
{
    public function testPromptWriteHandlersRejectIncompletePayloadsBeforeFileIo(): void
    {
        JsonRequest::bind([]);

        foreach ([
            new AppendAiMemory(),
            new DeleteAiMistakes(),
            new RecordAiMistake(),
            new SaveAiPromptFile(),
        ] as $handler) {
            $response = $handler();
            $payload = $this->json($response);
            self::assertSame(400, $response->getStatusCode(), $handler::class);
            self::assertFalse($payload['success'], $handler::class);
        }
    }

    public function testToolValidationErrorsRemainSuccessfulHttpToolResults(): void
    {
        JsonRequest::bind([]);
        $response = (new CallAiTool())();
        $payload = $this->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($payload['success']);
        self::assertFalse($payload['result']['success']);
        self::assertSame('Tool name is required.', $payload['result']['error']);
    }

    public function testReadAndSelectionHandlersReturnStableJsonShapes(): void
    {
        JsonRequest::bind([]);

        foreach ([
            [new ListAiPromptFiles(), 'files'],
            [new ListAiTools(), 'tools'],
            [new ReviewAiMistakes(), 'mistakes'],
            [new SelectAiTools(), 'tools'],
        ] as [$handler, $key]) {
            $response = $handler();
            $payload = $this->json($response);
            self::assertSame(200, $response->getStatusCode(), $handler::class);
            self::assertTrue($payload['success'], $handler::class);
            self::assertArrayHasKey($key, $payload, $handler::class);
        }
    }

    /** @return array<string, mixed> */
    private function json(\Psr\Http\Message\ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
