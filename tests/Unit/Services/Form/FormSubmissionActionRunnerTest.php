<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Form;

use Naf\CMS\Services\Form\FormSubmissionActionRunner;
use Naf\CMS\Services\Http\OutboundHttpPolicy;
use Tests\Support\NafTestCase;

final class FormSubmissionActionRunnerTest extends NafTestCase
{
    public function testDefaultsToStoreActionWhenNoActionsAreConfigured(): void
    {
        $stored = [];
        $runner = new FormSubmissionActionRunner(
            static function (int $formId, array $payload, array $meta) use (&$stored): int {
                $stored = compact('formId', 'payload', 'meta');

                return 42;
            },
        );

        $result = $runner->run(['id' => 10, 'settings' => []], ['email' => 'a@example.com'], ['ip' => '127.0.0.1']);

        self::assertTrue($result['success']);
        self::assertSame(10, $stored['formId']);
        self::assertSame(42, $result['results'][0]['submissionId']);
    }

    public function testSendsWebhookActionPayload(): void
    {
        $sent   = [];
        $runner = new FormSubmissionActionRunner(
            static fn(): int => 0,
            static function (string $url, string $method, array $payload) use (&$sent): array {
                $sent = compact('url', 'method', 'payload');

                return ['success' => true, 'status' => 204];
            },
            $this->publicPolicy(),
        );

        $result = $runner->run([
            'id'         => 5,
            'identifier' => 'contact',
            'title'      => 'Contact',
            'settings'   => [
                'actions' => [
                    ['type' => 'webhook', 'url' => 'https://example.test/leads', 'method' => 'PUT'],
                ],
            ],
        ], ['first-name' => 'Ada'], ['ip' => '127.0.0.1']);

        self::assertTrue($result['success']);
        self::assertSame('https://example.test/leads', $sent['url']);
        self::assertSame('PUT', $sent['method']);
        self::assertSame('contact', $sent['payload']['form']['identifier']);
        self::assertSame('Ada', $sent['payload']['values']['first-name']);
    }

    public function testWebhookCanFailTheSubmission(): void
    {
        $runner = new FormSubmissionActionRunner(
            static fn(): int => 0,
            static fn(): array => ['success' => false, 'status' => 500, 'reason' => 'Webhook returned HTTP 500'],
            $this->publicPolicy(),
        );

        $result = $runner->run([
            'settings' => [
                'actions' => [
                    [
                        'type'        => 'webhook',
                        'url'         => 'https://example.test/leads',
                        'failOnError' => true,
                    ],
                ],
            ],
        ], [], []);

        self::assertFalse($result['success']);
        self::assertSame(['Webhook returned HTTP 500'], $result['errors']);
    }

    public function testBlockedWebhookNeverReachesInjectedSender(): void
    {
        $sent   = false;
        $runner = new FormSubmissionActionRunner(
            static fn(): int => 0,
            static function () use (&$sent): array {
                $sent = true;

                return ['success' => true, 'status' => 204];
            },
        );

        $result = $runner->run([
            'settings' => ['actions' => [[
                'type'        => 'webhook',
                'url'         => 'https://169.254.169.254/latest/meta-data',
                'failOnError' => true,
            ]]],
        ], [], []);

        self::assertFalse($result['success']);
        self::assertFalse($sent);
        self::assertSame(['Webhook target is not allowed.'], $result['errors']);
    }

    private function publicPolicy(): OutboundHttpPolicy
    {
        return new OutboundHttpPolicy(static fn(string $host): array => ['93.184.216.34']);
    }
}
