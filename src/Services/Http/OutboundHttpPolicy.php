<?php

declare(strict_types=1);

namespace NixPHP\CMS\Services\Http;

use Closure;
use NixPHP\CMS\Exceptions\OutboundHttpDenied;
use NixPHP\CMS\Services\Http\OutboundHttpProfile;
use NixPHP\CMS\Services\Http\OutboundHttpTarget;

final class OutboundHttpPolicy
{
    /** @var list<string> */
    private const BLOCKED_IPV4_CIDRS = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
    ];

    /** @var list<string> */
    private const BLOCKED_IPV6_CIDRS = [
        '::/128',
        '::1/128',
        '::ffff:0:0/96',
        '64:ff9b:1::/48',
        '100::/64',
        '2001:2::/48',
        '2001:10::/28',
        '2001:db8::/32',
        'fc00::/7',
        'fe80::/10',
        'ff00::/8',
    ];

    private ?Closure $resolver;

    /** @param callable(string):array<int,string>|null $resolver */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver === null ? null : Closure::fromCallable($resolver);
    }

    public function authorize(string $url, OutboundHttpProfile $profile): OutboundHttpTarget
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
            throw new OutboundHttpDenied('Outbound URL is invalid.');
        }

        $parts = parse_url($url);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass'])) {
            throw new OutboundHttpDenied('Outbound URL credentials are not allowed.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $policyHost = trim($host, '[]');
        if ($host === '' || !in_array($scheme, $profile->schemes, true)) {
            throw new OutboundHttpDenied('Outbound URL scheme or host is not allowed.');
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if ($port <= 0 || $port > 65535) {
            throw new OutboundHttpDenied('Outbound URL port is invalid.');
        }

        $isExplicitDevelopmentHost = in_array($policyHost, $profile->developmentHosts, true);
        if ($profile->name === 'ollama') {
            if (!$isExplicitDevelopmentHost || !in_array($port, $profile->allowedPorts, true)) {
                throw new OutboundHttpDenied('Ollama target must use an allowed loopback host and port.');
            }
        } elseif ($scheme === 'http' && !$isExplicitDevelopmentHost) {
            throw new OutboundHttpDenied('Plain HTTP is allowed only for explicitly configured development hosts.');
        }

        $addresses = $this->resolve($policyHost);
        if ($addresses === []) {
            throw new OutboundHttpDenied('Outbound host could not be resolved.');
        }

        foreach ($addresses as $address) {
            $isPublic = $this->isPublicAddress($address);
            if ($profile->name === 'ollama' && !$this->isLoopbackAddress($address)) {
                throw new OutboundHttpDenied('Ollama host resolved outside loopback.');
            }
            if (!$isPublic && !($profile->allowPrivateNetworks && $isExplicitDevelopmentHost)) {
                throw new OutboundHttpDenied('Outbound target resolves to a blocked network.');
            }
        }

        return new OutboundHttpTarget($url, $scheme, $host, $port, $addresses);
    }

    /** @return list<string> */
    private function resolve(string $host): array
    {
        $literal = trim($host, '[]');
        if (filter_var($literal, FILTER_VALIDATE_IP) !== false) {
            return [$literal];
        }

        if ($this->resolver instanceof Closure) {
            $resolved = ($this->resolver)($host);
        } else {
            $records = dns_get_record($host, DNS_A | DNS_AAAA);
            $resolved = [];
            foreach (is_array($records) ? $records : [] as $record) {
                $address = (string) ($record['ip'] ?? $record['ipv6'] ?? '');
                if ($address !== '') {
                    $resolved[] = $address;
                }
            }
        }

        return array_values(array_unique(array_filter(array_map(
            static fn(mixed $address): string => trim((string) $address),
            is_array($resolved) ? $resolved : [],
        ), static fn(string $address): bool => filter_var($address, FILTER_VALIDATE_IP) !== false)));
    }

    private function isPublicAddress(string $address): bool
    {
        if (filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false) {
            return false;
        }

        $cidrs = str_contains($address, ':') ? self::BLOCKED_IPV6_CIDRS : self::BLOCKED_IPV4_CIDRS;
        foreach ($cidrs as $cidr) {
            if ($this->addressInCidr($address, $cidr)) {
                return false;
            }
        }

        return true;
    }

    private function isLoopbackAddress(string $address): bool
    {
        if ($address === '127.0.0.1' || $address === '::1') {
            return true;
        }

        $binary = @inet_pton($address);
        return is_string($binary) && strlen($binary) === 4 && ord($binary[0]) === 127;
    }

    private function addressInCidr(string $address, string $cidr): bool
    {
        [$network, $prefixText] = explode('/', $cidr, 2);
        $addressBytes = @inet_pton($address);
        $networkBytes = @inet_pton($network);
        if (!is_string($addressBytes) || !is_string($networkBytes) || strlen($addressBytes) !== strlen($networkBytes)) {
            return false;
        }

        $prefix = (int) $prefixText;
        $fullBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;
        if ($fullBytes > 0 && substr($addressBytes, 0, $fullBytes) !== substr($networkBytes, 0, $fullBytes)) {
            return false;
        }
        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remainingBits)) & 0xff;
        return (ord($addressBytes[$fullBytes]) & $mask) === (ord($networkBytes[$fullBytes]) & $mask);
    }
}
