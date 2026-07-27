<?php

declare(strict_types=1);

namespace src\Mcp\Tools;

use NixPHP\MCP\Support\Schema;
use NixPHP\MCP\Tools\ScopedToolInterface;
use NixPHP\MCP\Tools\ToolInterface;
use Throwable;

final class TrendingTopicsTool implements ToolInterface, ScopedToolInterface
{
    public function name(): string
    {
        return 'external_trending_topics';
    }

    public function description(): string
    {
        return 'Returns current public-interest or news trend signals from public sources for content ideas, campaigns, and shop planning.';
    }

    public function inputSchema(): array
    {
        return Schema::object()
            ->description($this->description())
            ->additionalProperties(false)
            ->prop('source', Schema::string()->description('Trend source: wikipedia for public-interest pageviews or news for recent news coverage.')->enum(['wikipedia', 'news'])->default('wikipedia'))
            ->prop('project', Schema::string()->description('Wikimedia project for wikipedia source, for example de.wikipedia or en.wikipedia.')->default('de.wikipedia'))
            ->prop('country_code', Schema::string()->description('Optional ISO alpha-2 country code for country-specific Wikipedia pageviews, for example DE, AT, CH, US.')->default(''))
            ->prop('date', Schema::string()->description('Date as YYYY-MM-DD. Defaults to yesterday for Wikipedia because complete current-day data is usually unavailable.')->default(''))
            ->prop('query', Schema::string()->description('Optional news query for GDELT news source, for example ecommerce, retail, fashion, local events.')->default(''))
            ->prop('timespan', Schema::string()->description('News timespan for GDELT, for example 6h, 1day, 3days, 1week.')->default('1day'))
            ->prop('limit', Schema::integer()->min(1)->max(50)->default(15))
            ->toArray();
    }

    public function requiredScopes(): array
    {
        return ['system:read'];
    }

    public function handle(array $args): array
    {
        $source = strtolower(trim((string) ($args['source'] ?? 'wikipedia'))) === 'news' ? 'news' : 'wikipedia';
        $limit = max(1, min(50, (int) ($args['limit'] ?? 15)));

        try {
            return $source === 'news'
                ? $this->newsTrends($args, $limit)
                : $this->wikipediaTrends($args, $limit);
        } catch (Throwable $throwable) {
            return [
                'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'source' => $source,
                'error' => $throwable->getMessage(),
                'trends' => [],
            ];
        }
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function wikipediaTrends(array $args, int $limit): array
    {
        $project = $this->project((string) ($args['project'] ?? 'de.wikipedia'));
        $countryCode = strtoupper(trim((string) ($args['country_code'] ?? '')));
        $date = $this->date((string) ($args['date'] ?? ''));
        $pathDate = $date->format('Y/m/d');

        $url = $countryCode !== '' && preg_match('/^[A-Z]{2}$/', $countryCode) === 1
            ? sprintf('https://wikimedia.org/api/rest_v1/metrics/pageviews/top-per-country/%s/all-access/%s', rawurlencode($countryCode), $pathDate)
            : sprintf('https://wikimedia.org/api/rest_v1/metrics/pageviews/top/%s/all-access/%s', rawurlencode($project), $pathDate);

        $payload = $this->requestJson($url, 'NixCMS Local AI Wikimedia Trends Tool');
        $articles = $payload['items'][0]['articles'] ?? [];
        $articles = is_array($articles) ? $articles : [];
        $trends = [];
        $seen = [];
        foreach ($articles as $article) {
            $trend = $this->summarizeWikipediaArticle($article);
            if ($trend === null) {
                continue;
            }

            $key = mb_strtolower((string) $trend['title']);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $trends[] = $trend;
            if (count($trends) >= $limit) {
                break;
            }
        }

        return [
            'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'source' => 'Wikimedia Pageviews',
            'signal' => 'Most viewed Wikipedia pages. Treat as public-interest signal, not search volume.',
            'project' => $countryCode !== '' ? null : $project,
            'country_code' => $countryCode !== '' ? $countryCode : null,
            'date' => $date->format('Y-m-d'),
            'trends' => $trends,
        ];
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function newsTrends(array $args, int $limit): array
    {
        $query = trim((string) ($args['query'] ?? ''));
        $timespan = $this->timespan((string) ($args['timespan'] ?? '1day'));
        $gdeltQuery = $query !== '' ? $query : '(retail OR ecommerce OR shopping OR consumer OR marketing)';

        $payload = $this->requestJson('https://api.gdeltproject.org/api/v2/doc/doc?' . http_build_query([
            'query' => $gdeltQuery,
            'mode' => 'artlist',
            'format' => 'json',
            'maxrecords' => $limit,
            'timespan' => $timespan,
            'sort' => 'datedesc',
        ]), 'NixCMS Local AI GDELT Trends Tool');
        $articles = is_array($payload['articles'] ?? null) ? $payload['articles'] : [];

        return [
            'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'source' => 'GDELT DOC 2.0',
            'signal' => 'Recent news coverage matching the query. Treat as media-coverage signal, not search volume.',
            'query' => $gdeltQuery,
            'timespan' => $timespan,
            'trends' => array_map($this->summarizeNewsArticle(...), $articles),
        ];
    }

    private function project(string $value): string
    {
        $project = strtolower(trim($value));

        return preg_match('/^[a-z0-9_-]+\.wikipedia$/', $project) === 1 ? $project : 'de.wikipedia';
    }

    private function date(string $value): \DateTimeImmutable
    {
        $date = trim($value) !== ''
            ? \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value))
            : false;

        return $date instanceof \DateTimeImmutable
            ? $date
            : new \DateTimeImmutable('yesterday');
    }

    private function timespan(string $value): string
    {
        $timespan = strtolower(trim($value));

        return preg_match('/^\d+(h|hour|hours|day|days|week|weeks)$/', $timespan) === 1
            ? $timespan
            : '1day';
    }

    /**
     * @return array<string, mixed>
     */
    private function requestJson(string $url, string $userAgent): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'header' => "Accept: application/json\r\nUser-Agent: {$userAgent}\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        if (!is_string($raw) || trim($raw) === '') {
            throw new \RuntimeException('Trend source did not return a response.');
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Trend source returned invalid JSON.');
        }

        return $data;
    }

    /**
     * @param mixed $article
     * @return array<string, mixed>|null
     */
    private function summarizeWikipediaArticle(mixed $article): ?array
    {
        if (!is_array($article)) {
            return null;
        }

        $title = (string) ($article['article'] ?? '');
        if ($title === '' || $this->isIgnoredWikipediaTitle($title)) {
            return null;
        }

        return [
            'title' => str_replace('_', ' ', $title),
            'views' => (int) ($article['views'] ?? 0),
            'rank' => (int) ($article['rank'] ?? 0),
        ];
    }

    private function isIgnoredWikipediaTitle(string $title): bool
    {
        $normalized = str_replace('_', ' ', trim($title));
        $lower = mb_strtolower($normalized);
        $prefixes = [
            'main page',
            'wikipedia:',
            'special:',
            'spezial:',
            'datei:',
            'file:',
            'hilfe:',
            'help:',
            'portal:',
            'kategorie:',
            'category:',
            'vorlage:',
            'template:',
            'mediawiki:',
            'benutzer:',
            'user:',
            'talk:',
            'diskussion:',
            'liste der ',
            'list of ',
        ];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return true;
            }
        }

        return str_contains($lower, 'wikipedia')
            || str_contains($lower, 'hauptseite')
            || str_contains($lower, 'featured pictures')
            || str_contains($lower, 'spezial:suche');
    }

    /**
     * @param array<string, mixed> $article
     * @return array<string, mixed>
     */
    private function summarizeNewsArticle(array $article): array
    {
        return [
            'title' => (string) ($article['title'] ?? ''),
            'url' => (string) ($article['url'] ?? ''),
            'domain' => (string) ($article['domain'] ?? ''),
            'language' => (string) ($article['language'] ?? ''),
            'source_country' => (string) ($article['sourcecountry'] ?? ''),
            'seen_at' => (string) ($article['seendate'] ?? ''),
        ];
    }
}
