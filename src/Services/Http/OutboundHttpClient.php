<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Http;

use Closure;
use Naf\CMS\Contracts\OutboundHttpClientInterface;
use Naf\CMS\Exceptions\OutboundHttpDenied;
use RuntimeException;

use function Naf\log;

final class OutboundHttpClient implements OutboundHttpClientInterface
{
    private ?Closure $transport;

    /**
     * @param callable(OutboundHttpTarget,string,string,array<string,string>,float,int):array{status:int,headers:array<string,string>,body:string}|null $transport
     */
    public function __construct(
        private readonly OutboundHttpPolicy $policy = new OutboundHttpPolicy(),
        ?callable $transport = null,
        private readonly int $maxRedirects = 3,
        private readonly int $maxResponseBytes = 262144,
    ) {
        $this->transport = $transport === null ? null : Closure::fromCallable($transport);
    }

    /** @param array<string, mixed> $payload @param array<string, string> $headers */
    public function postJson(
        string $url,
        array $payload,
        OutboundHttpProfile $profile,
        float $timeout = 5.0,
        array $headers = [],
    ): OutboundHttpResponse {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->request($url, 'POST', $body, $profile, $timeout, [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            ...$headers,
        ]);
    }

    /** @param array<string, string> $headers */
    public function request(
        string $url,
        string $method,
        string $body,
        OutboundHttpProfile $profile,
        float $timeout = 5.0,
        array $headers = [],
    ): OutboundHttpResponse {
        $method     = strtoupper($method);
        $currentUrl = $url;

        for ($redirect = 0; $redirect <= $this->maxRedirects; $redirect++) {
            $target = $this->policy->authorize($currentUrl, $profile);
            $raw    = $this->transport instanceof Closure
                ? ($this->transport)($target, $method, $body, $headers, $timeout, $this->maxResponseBytes)
                : $this->curl($target, $method, $body, $headers, $timeout);
            $responseBody = (string) ($raw['body'] ?? '');
            if (strlen($responseBody) > $this->maxResponseBytes) {
                throw new RuntimeException('Outbound response exceeded the configured size limit.');
            }
            $response = new OutboundHttpResponse(
                (int) ($raw['status'] ?? 0),
                is_array($raw['headers'] ?? null) ? $raw['headers'] : [],
                $responseBody,
            );

            if (!in_array($response->status, [301, 302, 303, 307, 308], true)) {
                return $response;
            }

            $location = trim((string) ($response->headers['location'] ?? ''));
            if ($location === '' || $redirect === $this->maxRedirects) {
                throw new OutboundHttpDenied('Outbound redirect limit exceeded or location is missing.');
            }
            $currentUrl = $this->resolveRedirect($currentUrl, $location);
            if ($response->status === 303) {
                $method = 'GET';
                $body   = '';
            }
        }

        throw new OutboundHttpDenied('Outbound redirect limit exceeded.');
    }

    /** @param array<string, string> $headers @return array{status:int,headers:array<string,string>,body:string} */
    private function curl(OutboundHttpTarget $target, string $method, string $body, array $headers, float $timeout): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The curl extension is required for outbound HTTP requests.');
        }

        $handle = curl_init($target->url);
        if ($handle === false) {
            throw new RuntimeException('Outbound HTTP client could not be initialized.');
        }

        $responseHeaders = [];
        $responseBody    = '';
        $tooLarge        = false;
        $headerLines     = array_map(static fn(string $name, string $value): string => $name . ': ' . $value, array_keys($headers), $headers);
        $address         = $target->addresses[0];
        if (str_contains($address, ':')) {
            $address = '[' . $address . ']';
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST     => $method,
            CURLOPT_POSTFIELDS        => $body !== '' ? $body : null,
            CURLOPT_HTTPHEADER        => $headerLines,
            CURLOPT_FOLLOWLOCATION    => false,
            CURLOPT_CONNECTTIMEOUT_MS => max(100, (int) ($timeout * 500)),
            CURLOPT_TIMEOUT_MS        => max(100, (int) ($timeout * 1000)),
            CURLOPT_PROTOCOLS         => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS   => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_PROXY             => '',
            CURLOPT_NOPROXY           => '*',
            CURLOPT_RESOLVE           => [$target->host . ':' . $target->port . ':' . $address],
            CURLOPT_HEADERFUNCTION    => static function ($curl, string $line) use (&$responseHeaders): int {
                $separator = strpos($line, ':');
                if ($separator !== false) {
                    $responseHeaders[strtolower(trim(substr($line, 0, $separator)))] = trim(substr($line, $separator + 1));
                }

                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function ($curl, string $chunk) use (&$responseBody, &$tooLarge): int {
                if (strlen($responseBody) + strlen($chunk) > $this->maxResponseBytes) {
                    $tooLarge = true;

                    return 0;
                }
                $responseBody .= $chunk;

                return strlen($chunk);
            },
        ]);

        $success = curl_exec($handle);
        $status  = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error   = curl_error($handle);

        if ($tooLarge) {
            throw new RuntimeException('Outbound response exceeded the configured size limit.');
        }
        if ($success === false) {
            // curl details can expose resolved hostnames and IP addresses. Keep
            // them in operational logs instead of user-facing exceptions.
            log()->warning('Outbound request failed.', ['error' => $error, 'host' => $target->host]);
            throw new RuntimeException('Outbound request failed.');
        }

        return ['status' => $status, 'headers' => $responseHeaders, 'body' => $responseBody];
    }

    private function resolveRedirect(string $baseUrl, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }

        $parts = parse_url($baseUrl);
        if (!is_array($parts)) {
            throw new OutboundHttpDenied('Redirect base URL is invalid.');
        }
        if (str_starts_with($location, '//')) {
            return (string) ($parts['scheme'] ?? '') . ':' . $location;
        }
        $authority = (string) ($parts['scheme'] ?? '') . '://' . (string) ($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $authority .= ':' . (int) $parts['port'];
        }
        if (str_starts_with($location, '/')) {
            return $authority . $location;
        }

        $path = (string) ($parts['path'] ?? '/');

        return $authority . rtrim(str_replace('\\', '/', dirname($path)), '/') . '/' . $location;
    }
}
