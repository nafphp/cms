<?php

declare(strict_types=1);

namespace NixPHP\CMS\Contracts;

use NixPHP\CMS\Services\Http\OutboundHttpProfile;
use NixPHP\CMS\Services\Http\OutboundHttpResponse;

interface OutboundHttpClientInterface
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function postJson(string $url, array $payload, OutboundHttpProfile $profile, float $timeout = 5.0, array $headers = []): OutboundHttpResponse;
    /** @param array<string, string> $headers */
    public function request(string $url, string $method, string $body, OutboundHttpProfile $profile, float $timeout = 5.0, array $headers = []): OutboundHttpResponse;
}
