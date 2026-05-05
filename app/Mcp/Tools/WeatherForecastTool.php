<?php

declare(strict_types=1);

namespace NixCMS\Mcp\Tools;

use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;
use Throwable;

final class WeatherForecastTool implements ToolInterface, ScopedToolInterface
{
    public function name(): string
    {
        return 'external_weather_forecast';
    }

    public function description(): string
    {
        return 'Returns current weather and a short forecast for a location so CMS or shop owners can plan campaigns, events, and operational actions.';
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->prop('location', Schema::string()->description('City or place name, for example Berlin or Cologne.')->default(''))
            ->prop('latitude', Schema::string()->description('Latitude as decimal number. Optional when location is provided.')->default(''))
            ->prop('longitude', Schema::string()->description('Longitude as decimal number. Optional when location is provided.')->default(''))
            ->prop('days', Schema::integer()->description('Forecast days to return.')->min(1)->max(7)->default(2))
            ->prop('timezone', Schema::string()->description('IANA timezone or auto.')->default('auto'))
            ->toArray();
    }

    public function requiredScopes(): array
    {
        return ['system:read'];
    }

    public function handle(array $args): array
    {
        $location = trim((string) ($args['location'] ?? ''));
        $latitude = trim((string) ($args['latitude'] ?? ''));
        $longitude = trim((string) ($args['longitude'] ?? ''));
        $days = max(1, min(7, (int) ($args['days'] ?? 2)));
        $timezone = trim((string) ($args['timezone'] ?? 'auto')) ?: 'auto';

        try {
            $place = $this->resolvePlace($location, $latitude, $longitude);
            $forecast = $this->requestJson('https://api.open-meteo.com/v1/forecast?' . http_build_query([
                'latitude' => $place['latitude'],
                'longitude' => $place['longitude'],
                'timezone' => $timezone,
                'forecast_days' => $days,
                'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,precipitation,rain,weather_code,wind_speed_10m',
                'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,precipitation_probability_max,wind_speed_10m_max',
            ]));

            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'source' => 'Open-Meteo',
                'location' => $place,
                'timezone' => (string) ($forecast['timezone'] ?? $timezone),
                'current' => $this->currentWeather($forecast['current'] ?? []),
                'daily' => $this->dailyForecast($forecast['daily'] ?? []),
            ];
        } catch (Throwable $throwable) {
            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'source' => 'Open-Meteo',
                'error' => $throwable->getMessage(),
                'location' => $location,
                'current' => null,
                'daily' => [],
            ];
        }
    }

    /**
     * @return array{name:string,country:string,latitude:float,longitude:float}
     */
    private function resolvePlace(string $location, string $latitude, string $longitude): array
    {
        if ($latitude !== '' && $longitude !== '' && is_numeric($latitude) && is_numeric($longitude)) {
            return [
                'name' => $location !== '' ? $location : 'coordinates',
                'country' => '',
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
            ];
        }

        if ($location === '') {
            throw new \InvalidArgumentException('A location or latitude/longitude is required.');
        }

        $data = $this->requestJson('https://geocoding-api.open-meteo.com/v1/search?' . http_build_query([
            'name' => $location,
            'count' => 1,
            'language' => 'de',
            'format' => 'json',
        ]));

        $result = is_array($data['results'] ?? null) ? ($data['results'][0] ?? null) : null;
        if (!is_array($result)) {
            throw new \RuntimeException('Location could not be resolved.');
        }

        return [
            'name' => (string) ($result['name'] ?? $location),
            'country' => (string) ($result['country'] ?? ''),
            'latitude' => (float) ($result['latitude'] ?? 0.0),
            'longitude' => (float) ($result['longitude'] ?? 0.0),
        ];
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
                'header' => "Accept: application/json\r\nUser-Agent: NixCMS Local AI Weather Tool\r\n",
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if (!is_string($raw) || trim($raw) === '') {
            throw new \RuntimeException('Weather service did not return a response.');
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Weather service returned invalid JSON.');
        }

        return $data;
    }

    /**
     * @param mixed $current
     * @return array<string, mixed>
     */
    private function currentWeather(mixed $current): array
    {
        $current = is_array($current) ? $current : [];

        return [
            'time' => (string) ($current['time'] ?? ''),
            'temperature_c' => $this->number($current['temperature_2m'] ?? null),
            'apparent_temperature_c' => $this->number($current['apparent_temperature'] ?? null),
            'humidity_percent' => $this->number($current['relative_humidity_2m'] ?? null),
            'precipitation_mm' => $this->number($current['precipitation'] ?? null),
            'rain_mm' => $this->number($current['rain'] ?? null),
            'wind_speed_kmh' => $this->number($current['wind_speed_10m'] ?? null),
            'weather_code' => $this->number($current['weather_code'] ?? null),
            'condition' => $this->weatherCodeLabel((int) ($current['weather_code'] ?? -1)),
        ];
    }

    /**
     * @param mixed $daily
     * @return array<int, array<string, mixed>>
     */
    private function dailyForecast(mixed $daily): array
    {
        $daily = is_array($daily) ? $daily : [];
        $dates = is_array($daily['time'] ?? null) ? $daily['time'] : [];
        $rows = [];

        foreach ($dates as $index => $date) {
            $code = (int) ($daily['weather_code'][$index] ?? -1);
            $rows[] = [
                'date' => (string) $date,
                'condition' => $this->weatherCodeLabel($code),
                'weather_code' => $code,
                'temperature_min_c' => $this->number($daily['temperature_2m_min'][$index] ?? null),
                'temperature_max_c' => $this->number($daily['temperature_2m_max'][$index] ?? null),
                'precipitation_sum_mm' => $this->number($daily['precipitation_sum'][$index] ?? null),
                'precipitation_probability_max_percent' => $this->number($daily['precipitation_probability_max'][$index] ?? null),
                'wind_speed_max_kmh' => $this->number($daily['wind_speed_10m_max'][$index] ?? null),
            ];
        }

        return $rows;
    }

    private function number(mixed $value): int|float|null
    {
        return is_numeric($value) ? $value + 0 : null;
    }

    private function weatherCodeLabel(int $code): string
    {
        return match ($code) {
            0 => 'clear sky',
            1, 2, 3 => 'partly cloudy',
            45, 48 => 'fog',
            51, 53, 55, 56, 57 => 'drizzle',
            61, 63, 65, 66, 67 => 'rain',
            71, 73, 75, 77 => 'snow',
            80, 81, 82 => 'rain showers',
            85, 86 => 'snow showers',
            95, 96, 99 => 'thunderstorm',
            default => 'unknown',
        };
    }
}
