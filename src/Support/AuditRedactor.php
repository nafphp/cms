<?php

declare(strict_types=1);

namespace Naf\CMS\Support;

final class AuditRedactor
{
    private const string REDACTED = '[redacted]';

    /** @var list<string> */
    private const array SENSITIVE_KEY_PARTS = [
        'authorization',
        'cookie',
        'csrf',
        'passphrase',
        'password',
        'secret',
        'session',
        'token',
    ];

    public function redact(mixed $value): mixed
    {
        if ($value instanceof \SensitiveParameterValue) {
            return self::REDACTED;
        }

        if (is_array($value)) {
            return $this->redactArray($value);
        }

        if (is_object($value)) {
            return $this->redactObject($value);
        }

        return $value;
    }

    /**
     * @param array<mixed> $value
     * @return array<mixed>
     */
    private function redactArray(array $value): array
    {
        $redacted = [];

        foreach ($value as $key => $entry) {
            $redacted[$key] = is_string($key) && $this->isSensitiveKey($key)
                ? self::REDACTED
                : $this->redact($entry);
        }

        return $redacted;
    }

    /**
     * @return array<string, mixed>|string
     */
    private function redactObject(object $value): array|string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $sensitiveProperties = $this->sensitiveConstructorParameterNames($value);
        $reflection = new \ReflectionObject($value);
        $redacted = [
            '_class' => $reflection->getName(),
        ];

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $name = $property->getName();
            if (!$property->isPublic()) {
                $property->setAccessible(true);
            }

            if (!$property->isInitialized($value)) {
                continue;
            }

            $redacted[$name] = isset($sensitiveProperties[$name]) || $this->isSensitiveKey($name)
                ? self::REDACTED
                : $this->redact($property->getValue($value));
        }

        return $redacted;
    }

    /**
     * PHP's built-in #[\SensitiveParameter] is parameter-only. For DTOs with constructor-promoted
     * properties, treat a sensitive constructor parameter as the matching property being sensitive.
     *
     * @return array<string, true>
     */
    private function sensitiveConstructorParameterNames(object $value): array
    {
        $constructor = (new \ReflectionObject($value))->getConstructor();
        if (!$constructor instanceof \ReflectionMethod) {
            return [];
        }

        $names = [];
        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->getAttributes(\SensitiveParameter::class) === []) {
                continue;
            }

            $names[$parameter->getName()] = true;
        }

        return $names;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '.'], '_', $key));

        foreach (self::SENSITIVE_KEY_PARTS as $part) {
            if (str_contains($normalized, $part)) {
                return true;
            }
        }

        return false;
    }
}
