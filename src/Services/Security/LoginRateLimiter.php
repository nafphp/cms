<?php

declare(strict_types=1);

namespace NixPHP\CMS\Services\Security;

use NixPHP\CMS\Contracts\RateLimiterInterface;
use NixPHP\CMS\Services\Security\FileRateLimiter;
use NixPHP\CMS\Services\Security\RateLimitDecision;

final class LoginRateLimiter implements RateLimiterInterface
{
    private const int PAIR_ATTEMPTS = 5;
    private const int IP_ATTEMPTS = 30;
    private const int WINDOW_SECONDS = 300;

    public function __construct(private readonly FileRateLimiter $limiter = new FileRateLimiter())
    {
    }

    public function inspect(string $username, string $ipAddress): RateLimitDecision
    {
        $pair = $this->limiter->inspect($this->pairKey($username, $ipAddress), self::PAIR_ATTEMPTS, self::WINDOW_SECONDS);
        $ip = $this->limiter->inspect($this->ipKey($ipAddress), self::IP_ATTEMPTS, self::WINDOW_SECONDS);
        return $this->strictest($pair, $ip);
    }

    public function recordFailure(string $username, string $ipAddress): RateLimitDecision
    {
        $pair = $this->limiter->hit($this->pairKey($username, $ipAddress), self::PAIR_ATTEMPTS, self::WINDOW_SECONDS);
        $ip = $this->limiter->hit($this->ipKey($ipAddress), self::IP_ATTEMPTS, self::WINDOW_SECONDS);
        return $this->strictest($pair, $ip);
    }

    public function clearSuccessfulPair(string $username, string $ipAddress): void
    {
        $this->limiter->clear($this->pairKey($username, $ipAddress));
    }

    private function pairKey(string $username, string $ipAddress): string
    {
        return 'login:pair:' . hash('sha256', strtolower(trim($username))) . ':' . hash('sha256', trim($ipAddress));
    }

    private function ipKey(string $ipAddress): string
    {
        return 'login:ip:' . hash('sha256', trim($ipAddress));
    }

    private function strictest(RateLimitDecision $left, RateLimitDecision $right): RateLimitDecision
    {
        return new RateLimitDecision(
            $left->allowed && $right->allowed,
            min($left->remaining, $right->remaining),
            max($left->retryAfter, $right->retryAfter),
        );
    }
}
