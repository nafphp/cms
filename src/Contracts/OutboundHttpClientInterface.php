<?php

declare(strict_types=1);

namespace Naf\CMS\Contracts;

use Naf\CMS\Services\Http\OutboundHttpProfile;
use Naf\CMS\Services\Http\OutboundHttpResponse;

/** @api */
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
