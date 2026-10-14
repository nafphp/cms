<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Form;

use Naf\CMS\Services\Security\FileRateLimiter;
use Naf\CMS\Services\Security\RateLimitDecision;

final class FormSubmissionGuard
{
    public const int MAXIMUM_BODY_BYTES  = 65536;
    public const int MAXIMUM_FIELDS      = 100;
    public const int MAXIMUM_FIELD_BYTES = 16384;

    public function __construct(private readonly FileRateLimiter $limiter = new FileRateLimiter())
    {
    }

    public function consume(string $identifier, string $ipAddress): RateLimitDecision
    {
        $key = 'form:' . hash('sha256', strtolower(trim($identifier))) . ':' . hash('sha256', trim($ipAddress));

        return $this->limiter->hit($key, 10, 60);
    }

    /** @param array<string, mixed> $payload @return array<string, array<int, string>> */
    public function validatePayload(string $rawBody, array $payload): array
    {
        $errors = [];
        if (strlen($rawBody) > self::MAXIMUM_BODY_BYTES) {
            $errors['_payload'][] = 'Form payload is too large.';
        }
        if (count($payload) > self::MAXIMUM_FIELDS) {
            $errors['_payload'][] = 'Form contains too many fields.';
        }

        foreach ($payload as $name => $value) {
            if (!is_scalar($value) && $value !== null) {
                $errors[(string) $name][] = 'Invalid field value.';
                continue;
            }
            if (strlen((string) $value) > self::MAXIMUM_FIELD_BYTES) {
                $errors[(string) $name][] = 'Field value is too large.';
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $payload */
    public function looksLikeSpam(array $payload, int $now = 0): bool
    {
        if (trim((string) ($payload['_nafcms_hp'] ?? '')) !== '') {
            return true;
        }

        $startedAt = (int) ($payload['_nafcms_started_at'] ?? 0);
        $now       = $now > 0 ? $now : time();

        return $startedAt <= 0 || $startedAt > $now || ($now - $startedAt) < 2;
    }
}
