<?php

declare(strict_types=1);

namespace NixCMS\Services\Form;

use Closure;

final class FormSubmissionActionRunner
{
    private ?Closure $storeSubmission;
    private ?Closure $sendWebhook;

    public function __construct(?callable $storeSubmission = null, ?callable $sendWebhook = null)
    {
        $this->storeSubmission = $storeSubmission instanceof Closure ? $storeSubmission : (
            $storeSubmission !== null ? Closure::fromCallable($storeSubmission) : null
        );
        $this->sendWebhook = $sendWebhook instanceof Closure ? $sendWebhook : (
            $sendWebhook !== null ? Closure::fromCallable($sendWebhook) : null
        );
    }

    /**
     * @param array<string, mixed> $form
     * @param array<string, mixed> $values
     * @param array<string, mixed> $meta
     * @return array{success: bool, errors: list<string>, results: list<array<string, mixed>>}
     */
    public function run(array $form, array $values, array $meta): array
    {
        $actions = $this->actions($form);
        $errors = [];
        $results = [];

        foreach ($actions as $action) {
            $type = (string) ($action['type'] ?? '');
            if ($type === 'store') {
                $results[] = $this->store($form, $values, $meta);
                continue;
            }

            if ($type === 'webhook') {
                $result = $this->webhook($action, $form, $values, $meta);
                $results[] = $result;
                if (($result['success'] ?? false) !== true && ($action['failOnError'] ?? false) === true) {
                    $errors[] = (string) ($result['reason'] ?? 'Webhook action failed.');
                }
            }
        }

        return [
            'success' => $errors === [],
            'errors' => $errors,
            'results' => $results,
        ];
    }

    /**
     * @param array<string, mixed> $form
     * @return list<array<string, mixed>>
     */
    private function actions(array $form): array
    {
        $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
        $actions = is_array($settings['actions'] ?? null) ? $settings['actions'] : [];
        $actions = array_values(array_filter($actions, static fn(mixed $action): bool => is_array($action)
            && ($action['enabled'] ?? true) !== false));

        return $actions !== [] ? $actions : [['type' => 'store', 'enabled' => true]];
    }

    /** @param array<string, mixed> $form @param array<string, mixed> $values @param array<string, mixed> $meta */
    private function store(array $form, array $values, array $meta): array
    {
        $store = $this->storeSubmission ?? static fn(int $formId, array $payload, array $submissionMeta): int
            => \cms()->forms()->storeSubmission($formId, $payload, $submissionMeta);

        $submissionId = $store((int) ($form['id'] ?? 0), $values, $meta);

        return ['type' => 'store', 'success' => true, 'submissionId' => $submissionId];
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $form
     * @param array<string, mixed> $values
     * @param array<string, mixed> $meta
     */
    private function webhook(array $action, array $form, array $values, array $meta): array
    {
        $url = trim((string) ($action['url'] ?? ''));
        if ($url === '') {
            return ['type' => 'webhook', 'success' => false, 'reason' => 'Webhook URL is empty.'];
        }

        $method = strtoupper(trim((string) ($action['method'] ?? 'POST')));
        if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $method = 'POST';
        }

        $payload = [
            'form' => [
                'id' => (int) ($form['id'] ?? 0),
                'identifier' => (string) ($form['identifier'] ?? ''),
                'title' => (string) ($form['title'] ?? ''),
            ],
            'values' => $values,
            'meta' => $meta,
        ];

        $sender = $this->sendWebhook ?? $this->defaultWebhookSender(...);
        $result = $sender($url, $method, $payload);

        return ['type' => 'webhook', ...$result];
    }

    /** @param array<string, mixed> $payload @return array{success: bool, status: int, reason?: string} */
    private function defaultWebhookSender(string $url, string $method, array $payload): array
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false || !preg_match('/^https?:\/\//i', $url)) {
            return ['success' => false, 'status' => 0, 'reason' => 'Webhook URL must be http or https.'];
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            return ['success' => false, 'status' => 0, 'reason' => 'Webhook payload could not be encoded.'];
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => "Accept: application/json\r\nContent-Type: application/json\r\n",
                'content' => $json,
                'ignore_errors' => true,
                'timeout' => 8,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $status = $this->statusCode($http_response_header ?? []);

        return [
            'success' => $status >= 200 && $status < 300,
            'status' => $status,
            'reason' => $status >= 200 && $status < 300 ? '' : ('Webhook returned HTTP ' . ($status > 0 ? $status : '0')),
            'body' => is_string($body) ? mb_substr($body, 0, 1000) : '',
        ];
    }

    /** @param list<string> $headers */
    private function statusCode(array $headers): int
    {
        $line = (string) ($headers[0] ?? '');
        return preg_match('/\s(\d{3})\s/', $line, $matches) === 1 ? (int) $matches[1] : 0;
    }
}
