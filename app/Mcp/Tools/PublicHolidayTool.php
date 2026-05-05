<?php

declare(strict_types=1);

namespace NixCMS\Mcp\Tools;

use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;
use Throwable;

final class PublicHolidayTool implements ToolInterface, ScopedToolInterface
{
    public function name(): string
    {
        return 'external_public_holidays';
    }

    public function description(): string
    {
        return 'Returns public holidays for a country and year so CMS or shop owners can plan campaigns, opening hours, staffing, and publishing schedules.';
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->prop('country_code', Schema::string()->description('ISO 3166-1 alpha-2 country code, for example DE, AT, CH, US.')->default('DE'))
            ->prop('year', Schema::integer()->description('Calendar year. Defaults to current year.')->min(1970)->max(2100)->default((int) date('Y')))
            ->prop('only_upcoming', Schema::boolean()->description('Return only holidays from today onward.')->default(false))
            ->prop('limit', Schema::integer()->min(1)->max(100)->default(20))
            ->toArray();
    }

    public function requiredScopes(): array
    {
        return ['system:read'];
    }

    public function handle(array $args): array
    {
        $countryCode = strtoupper(trim((string) ($args['country_code'] ?? 'DE'))) ?: 'DE';
        $year = max(1970, min(2100, (int) ($args['year'] ?? date('Y'))));
        $onlyUpcoming = filter_var($args['only_upcoming'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $limit = max(1, min(100, (int) ($args['limit'] ?? 20)));

        try {
            $holidays = $this->requestJson(sprintf(
                'https://date.nager.at/api/v3/PublicHolidays/%d/%s',
                $year,
                rawurlencode($countryCode)
            ));
            $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
            $filtered = array_values(array_filter(
                is_array($holidays) ? $holidays : [],
                static fn(mixed $holiday): bool => is_array($holiday)
                    && (!$onlyUpcoming || (string) ($holiday['date'] ?? '') >= $today)
            ));

            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'source' => 'Nager.Date',
                'country_code' => $countryCode,
                'year' => $year,
                'only_upcoming' => $onlyUpcoming,
                'total_available' => count($filtered),
                'holidays' => array_slice(array_map($this->summarizeHoliday(...), $filtered), 0, $limit),
            ];
        } catch (Throwable $throwable) {
            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'source' => 'Nager.Date',
                'error' => $throwable->getMessage(),
                'country_code' => $countryCode,
                'year' => $year,
                'holidays' => [],
            ];
        }
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
                'header' => "Accept: application/json\r\nUser-Agent: NixCMS Local AI Holiday Tool\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        if (!is_string($raw) || trim($raw) === '') {
            throw new \RuntimeException('Holiday service did not return a response.');
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Holiday service returned invalid JSON.');
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $holiday
     * @return array<string, mixed>
     */
    private function summarizeHoliday(array $holiday): array
    {
        return [
            'date' => (string) ($holiday['date'] ?? ''),
            'local_name' => (string) ($holiday['localName'] ?? ''),
            'name' => (string) ($holiday['name'] ?? ''),
            'country_code' => (string) ($holiday['countryCode'] ?? ''),
            'global' => (bool) ($holiday['global'] ?? false),
            'counties' => $holiday['counties'] ?? null,
            'types' => is_array($holiday['types'] ?? null) ? array_values($holiday['types']) : [],
        ];
    }
}
