<?php

declare(strict_types=1);

namespace NixPHP\CMS\Services\Security;

use Closure;
use NixPHP\CMS\Support\RuntimePaths;
use NixPHP\CMS\Services\Security\RateLimitDecision;

final class FileRateLimiter
{
    private Closure $clock;

    /** @param callable():int|null $clock */
    public function __construct(
        private readonly ?string $directory = null,
        ?callable $clock = null,
    ) {
        $this->clock = $clock === null ? static fn(): int => time() : Closure::fromCallable($clock);
    }

    public function inspect(string $key, int $maximumAttempts, int $windowSeconds): RateLimitDecision
    {
        return $this->mutate($key, $maximumAttempts, $windowSeconds, false);
    }

    public function hit(string $key, int $maximumAttempts, int $windowSeconds): RateLimitDecision
    {
        return $this->mutate($key, $maximumAttempts, $windowSeconds, true);
    }

    public function clear(string $key): void
    {
        $path = $this->path($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function mutate(string $key, int $maximumAttempts, int $windowSeconds, bool $increment): RateLimitDecision
    {
        $maximumAttempts = max(1, $maximumAttempts);
        $windowSeconds = max(1, $windowSeconds);
        $now = ($this->clock)();
        $path = $this->path($key);
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException('Rate-limit storage is not writable.');
        }

        $handle = @fopen($path, 'c+');
        if (!is_resource($handle)) {
            throw new \RuntimeException('Rate-limit state could not be opened.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Rate-limit state could not be locked.');
            }
            rewind($handle);
            $raw = stream_get_contents($handle);
            $state = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            $count = is_array($state) ? max(0, (int) ($state['count'] ?? 0)) : 0;
            $resetAt = is_array($state) ? (int) ($state['reset_at'] ?? 0) : 0;
            if ($resetAt <= $now) {
                $count = 0;
                $resetAt = $now + $windowSeconds;
            }
            if ($increment) {
                $count++;
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode(['count' => $count, 'reset_at' => $resetAt], JSON_THROW_ON_ERROR));
            fflush($handle);
            flock($handle, LOCK_UN);

            return new RateLimitDecision(
                $count <= $maximumAttempts,
                max(0, $maximumAttempts - $count),
                $count > $maximumAttempts ? max(1, $resetAt - $now) : 0,
            );
        } finally {
            fclose($handle);
        }
    }

    private function path(string $key): string
    {
        $hash = hash('sha256', $key);
        $root = $this->directory ?? RuntimePaths::cache('rate-limits');

        return rtrim($root, '/') . '/' . substr($hash, 0, 2) . '/' . $hash . '.json';
    }
}
