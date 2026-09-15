<?php

declare(strict_types=1);

namespace Naf\CMS\Contracts;

use Naf\CMS\Services\Security\RateLimitDecision;

interface RateLimiterInterface
{
    public function inspect(string $username, string $ipAddress): RateLimitDecision;

    public function recordFailure(string $username, string $ipAddress): RateLimitDecision;

    public function clearSuccessfulPair(string $username, string $ipAddress): void;
}
