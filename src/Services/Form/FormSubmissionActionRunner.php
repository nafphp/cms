<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Form;

use Closure;
use Naf\CMS\Exceptions\OutboundHttpDenied;
use Naf\CMS\Services\Http\OutboundHttpClient;
use Naf\CMS\Services\Http\OutboundHttpPolicy;
use Naf\CMS\Services\Http\OutboundHttpProfile;
use Throwable;

use function Naf\CMS\cms;
use function Naf\config;

final class FormSubmissionActionRunner
{
    private ?Closure $storeSubmission;
    private ?Closure $sendWebhook;
    private OutboundHttpPolicy $outboundPolicy;
    private OutboundHttpClient $outboundClient;
    private OutboundHttpProfile $webhookProfile;

    public function __construct(
        ?callable $storeSubmission = null,
        ?callable $sendWebhook = null,
        ?OutboundHttpPolicy $outboundPolicy = null,
        ?OutboundHttpClient $outboundClient = null,
        ?OutboundHttpProfile $webhookProfile = null,
    ) {
        $this->storeSubmission = $storeSubmission instanceof Closure ? $storeSubmission : (
            $storeSubmission !== null ? Closure::fromCallable($storeSubmission) : null
        );
        $this->sendWebhook = $sendWebhook instanceof Closure ? $sendWebhook : (
            $sendWebhook !== null ? Closure::fromCallable($sendWebhook) : null
        );
        $this->outboundPolicy = $outboundPolicy ?? new OutboundHttpPolicy();
        $this->outboundClient = $outboundClient ?? new OutboundHttpClient(
            $this->outboundPolicy,
            maxResponseBytes: (int) config('cms:outbound_http:webhooks:max_response_bytes', 65536),
        );
        $this->webhookProfile = $webhookProfile ?? OutboundHttpProfile::webhook(
            (bool) config('cms:outbound_http:webhooks:allow_development_http', false),
            (array) config('cms:outbound_http:webhooks:development_hosts', []),
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
        $errors  = [];
        $results = [];

        foreach ($actions as $action) {
            $type = (string) ($action['type'] ?? '');
            if ($type === 'store') {
                $results[] = $this->store($form, $values, $meta);
                continue;
            }

            if ($type === 'webhook') {
                $result    = $this->webhook($action, $form, $values, $meta);
                $results[] = $result;
                if (($result['success'] ?? false) !== true && ($action['failOnError'] ?? false) === true) {
                    $errors[] = (string) ($result['reason'] ?? 'Webhook action failed.');
                }
            }
        }

        return [
            'success' => $errors === [],
            'errors'  => $errors,
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
        $actions  = is_array($settings['actions'] ?? null) ? $settings['actions'] : [];
        $actions  = array_values(array_filter($actions, static fn(mixed $action): bool => is_array($action)
            && ($action['enabled'] ?? true) !== false));

        return $actions !== [] ? $actions : [['type' => 'store', 'enabled' => true]];
    }

    /** @param array<string, mixed> $form @param array<string, mixed> $values @param array<string, mixed> $meta */
    private function store(array $form, array $values, array $meta): array
    {
        $store = $this->storeSubmission ?? static fn(int $formId, array $payload, array $submissionMeta): int
            => cms()->forms()->storeSubmission($formId, $payload, $submissionMeta);

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
                'id'         => (int) ($form['id'] ?? 0),
                'identifier' => (string) ($form['identifier'] ?? ''),
                'title'      => (string) ($form['title'] ?? ''),
            ],
            'values' => $values,
            'meta'   => $meta,
        ];

        try {
            $this->outboundPolicy->authorize($url, $this->webhookProfile);
            $sender = $this->sendWebhook ?? $this->defaultWebhookSender(...);
            $result = $sender($url, $method, $payload);
        } catch (OutboundHttpDenied) {
            $result = ['success' => false, 'status' => 0, 'reason' => 'Webhook target is not allowed.'];
        } catch (Throwable) {
            $result = ['success' => false, 'status' => 0, 'reason' => 'Webhook request failed.'];
        }

        return ['type' => 'webhook', ...$result];
    }

    /** @param array<string, mixed> $payload @return array{success: bool, status: int, reason?: string} */
    private function defaultWebhookSender(string $url, string $method, array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            return ['success' => false, 'status' => 0, 'reason' => 'Webhook payload could not be encoded.'];
        }

        $response = $this->outboundClient->request(
            $url,
            $method,
            $json,
            $this->webhookProfile,
            (float) config('cms:outbound_http:webhooks:timeout', 8.0),
            ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
        );
        $status = $response->status;

        return [
            'success' => $response->successful(),
            'status'  => $status,
            'reason'  => $status >= 200 && $status < 300 ? '' : ('Webhook returned HTTP ' . ($status > 0 ? $status : '0')),
        ];
    }
}
