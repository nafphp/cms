<?php

declare(strict_types=1);

namespace NixPHP\CMS\Mcp\Tools;

use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;
use Throwable;

final class ExchangeRatesTool implements ToolInterface, ScopedToolInterface
{
    public function name(): string
    {
        return 'external_exchange_rates';
    }

    public function description(): string
    {
        return 'Returns latest exchange rates and optional converted amounts for international shop pricing, ads, purchasing, and campaign planning.';
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->prop('base', Schema::string()->description('Base currency code, for example EUR, USD, CHF, GBP.')->default('EUR'))
            ->prop('symbols', Schema::string()->description('Comma-separated quote currency codes, for example USD,GBP,CHF.')->default('USD,GBP,CHF'))
            ->prop('amount', Schema::string()->description('Optional amount to convert from base into quote currencies.')->default(''))
            ->toArray();
    }

    public function requiredScopes(): array
    {
        return ['system:read'];
    }

    public function handle(array $args): array
    {
        $base = $this->currency($args['base'] ?? 'EUR', 'EUR');
        $symbols = $this->symbols($args['symbols'] ?? 'USD,GBP,CHF', $base);
        $amount = trim((string) ($args['amount'] ?? ''));

        try {
            $payload = $this->requestJson('https://api.frankfurter.dev/v2/rates?' . http_build_query([
                'base' => $base,
                'quotes' => implode(',', $symbols),
            ]));
            $rates = is_array($payload['rates'] ?? null) ? $payload['rates'] : [];
            $converted = [];

            if ($amount !== '' && is_numeric($amount)) {
                foreach ($rates as $currency => $rate) {
                    if (is_numeric($rate)) {
                        $converted[(string) $currency] = round(((float) $amount) * ((float) $rate), 4);
                    }
                }
            }

            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'source' => 'Frankfurter',
                'base' => (string) ($payload['base'] ?? $base),
                'date' => (string) ($payload['date'] ?? ''),
                'rates' => array_map(static fn(mixed $rate): float => (float) $rate, $rates),
                'amount' => is_numeric($amount) ? (float) $amount : null,
                'converted' => $converted,
            ];
        } catch (Throwable $throwable) {
            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'source' => 'Frankfurter',
                'error' => $throwable->getMessage(),
                'base' => $base,
                'rates' => [],
                'converted' => [],
            ];
        }
    }

    private function currency(mixed $value, string $fallback): string
    {
        $currency = strtoupper(trim((string) $value));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : $fallback;
    }

    /**
     * @return array<int, string>
     */
    private function symbols(mixed $value, string $base): array
    {
        $symbols = array_values(array_unique(array_filter(array_map(
            fn(string $symbol): string => $this->currency($symbol, ''),
            explode(',', (string) $value)
        ))));
        $symbols = array_values(array_filter($symbols, static fn(string $symbol): bool => $symbol !== '' && $symbol !== $base));

        return $symbols !== [] ? array_slice($symbols, 0, 12) : ['USD', 'GBP', 'CHF'];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestJson(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 6,
                'header' => "Accept: application/json\r\nUser-Agent: NixCMS Local AI Exchange Rate Tool\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        if (!is_string($raw) || trim($raw) === '') {
            throw new \RuntimeException('Exchange-rate service did not return a response.');
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Exchange-rate service returned invalid JSON.');
        }

        if (isset($data['message'])) {
            throw new \RuntimeException((string) $data['message']);
        }

        return $data;
    }
}
