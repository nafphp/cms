<?php

declare(strict_types=1);

namespace NixPHP\CMS\Mcp\Tools;

use NixPHP\CMS\Contracts\LocalAiToolMetadataInterface;
use NixPHP\CMS\Enums\ToolRisk;

use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;
use Throwable;

final class AirQualityTool implements ToolInterface, ScopedToolInterface, LocalAiToolMetadataInterface
{
    use DeclaresLocalAiToolRisk;

    private const ToolRisk AI_RISK = ToolRisk::READ;
    private const bool AI_DESTRUCTIVE = false;
    public function name(): string
    {
        return 'external_air_quality';
    }

    public function description(): string
    {
        return 'Returns current air quality, UV index, and pollen context for a location so local businesses can plan outdoor, health, sport, and seasonal campaigns.';
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->prop('location', Schema::string()->description('City or place name, for example Berlin or Cologne.')->default(''))
            ->prop('latitude', Schema::string()->description('Latitude as decimal number. Optional when location is provided.')->default(''))
            ->prop('longitude', Schema::string()->description('Longitude as decimal number. Optional when location is provided.')->default(''))
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
        $timezone = trim((string) ($args['timezone'] ?? 'auto')) ?: 'auto';

        try {
            $place = $this->resolvePlace($location, $latitude, $longitude);
            $payload = $this->requestJson('https://air-quality-api.open-meteo.com/v1/air-quality?' . http_build_query([
                'latitude' => $place['latitude'],
                'longitude' => $place['longitude'],
                'timezone' => $timezone,
                'current' => 'pm10,pm2_5,carbon_monoxide,nitrogen_dioxide,ozone,uv_index,uv_index_clear_sky,alder_pollen,birch_pollen,grass_pollen,mugwort_pollen,ragweed_pollen',
                'forecast_days' => 1,
            ]));

            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'source' => 'Open-Meteo Air Quality',
                'location' => $place,
                'timezone' => (string) ($payload['timezone'] ?? $timezone),
                'current' => $this->current($payload['current'] ?? []),
            ];
        } catch (Throwable $throwable) {
            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'source' => 'Open-Meteo Air Quality',
                'error' => $throwable->getMessage(),
                'location' => $location,
                'current' => null,
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
                'header' => "Accept: application/json\r\nUser-Agent: NixCMS Local AI Air Quality Tool\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        if (!is_string($raw) || trim($raw) === '') {
            throw new \RuntimeException('Air-quality service did not return a response.');
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Air-quality service returned invalid JSON.');
        }

        return $data;
    }

    /**
     * @param mixed $current
     * @return array<string, mixed>
     */
    private function current(mixed $current): array
    {
        $current = is_array($current) ? $current : [];
        $pm25 = $this->number($current['pm2_5'] ?? null);
        $pm10 = $this->number($current['pm10'] ?? null);
        $uv = $this->number($current['uv_index'] ?? null);

        return [
            'time' => (string) ($current['time'] ?? ''),
            'pm2_5_ug_m3' => $pm25,
            'pm10_ug_m3' => $pm10,
            'air_quality_hint' => $this->airQualityHint($pm25, $pm10),
            'uv_index' => $uv,
            'uv_hint' => $this->uvHint($uv),
            'ozone_ug_m3' => $this->number($current['ozone'] ?? null),
            'nitrogen_dioxide_ug_m3' => $this->number($current['nitrogen_dioxide'] ?? null),
            'carbon_monoxide_ug_m3' => $this->number($current['carbon_monoxide'] ?? null),
            'pollen' => [
                'alder' => $this->number($current['alder_pollen'] ?? null),
                'birch' => $this->number($current['birch_pollen'] ?? null),
                'grass' => $this->number($current['grass_pollen'] ?? null),
                'mugwort' => $this->number($current['mugwort_pollen'] ?? null),
                'ragweed' => $this->number($current['ragweed_pollen'] ?? null),
            ],
        ];
    }

    private function number(mixed $value): int|float|null
    {
        return is_numeric($value) ? $value + 0 : null;
    }

    private function airQualityHint(int|float|null $pm25, int|float|null $pm10): string
    {
        if ($pm25 === null && $pm10 === null) {
            return 'unknown';
        }

        if (($pm25 ?? 0) <= 10 && ($pm10 ?? 0) <= 20) {
            return 'good';
        }

        if (($pm25 ?? 0) <= 25 && ($pm10 ?? 0) <= 50) {
            return 'moderate';
        }

        return 'elevated';
    }

    private function uvHint(int|float|null $uv): string
    {
        if ($uv === null) {
            return 'unknown';
        }

        return match (true) {
            $uv < 3 => 'low',
            $uv < 6 => 'moderate',
            $uv < 8 => 'high',
            default => 'very high',
        };
    }
}
