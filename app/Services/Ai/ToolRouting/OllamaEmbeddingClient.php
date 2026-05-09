<?php

declare(strict_types=1);

namespace NixCMS\Services\Ai\ToolRouting;

final class OllamaEmbeddingClient implements EmbeddingClientInterface
{
    /** @var array<string, array<int, float>> */
    private array $cache = [];
    private ?string $cacheDirectory;

    public function __construct(
        private string $baseUrl,
        private string $model,
        private float $timeout = 1.5,
        ?string $cacheDirectory = null,
    ) {
        $this->baseUrl = rtrim(trim($baseUrl), '/');
        $this->model = trim($model);
        $this->cacheDirectory = $cacheDirectory ?? $this->defaultCacheDirectory();
    }

    /**
     * @return array<int, float>|null
     */
    public function embed(string $text): ?array
    {
        $text = trim($text);
        if ($text === '' || $this->baseUrl === '' || $this->model === '') {
            return null;
        }

        $cacheKey = hash('sha256', $this->model . "\n" . $text);
        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }
        if (($cached = $this->readCached($cacheKey)) !== null) {
            return $this->cache[$cacheKey] = $cached;
        }

        $embedding = $this->embedViaApiEmbed($text) ?? $this->embedViaApiEmbeddings($text);
        if ($embedding === null) {
            return null;
        }

        $this->writeCached($cacheKey, $embedding);

        return $this->cache[$cacheKey] = $embedding;
    }

    /**
     * @param array<int, string> $texts
     * @return array<int, array<int, float>|null>
     */
    public function embedMany(array $texts): array
    {
        $results = [];
        $missingTexts = [];
        $missingIndexes = [];

        foreach ($texts as $index => $text) {
            $text = trim($text);
            if ($text === '') {
                $results[$index] = null;
                continue;
            }

            $cacheKey = hash('sha256', $this->model . "\n" . $text);
            if (array_key_exists($cacheKey, $this->cache)) {
                $results[$index] = $this->cache[$cacheKey];
                continue;
            }
            if (($cached = $this->readCached($cacheKey)) !== null) {
                $results[$index] = $this->cache[$cacheKey] = $cached;
                continue;
            }

            $missingIndexes[] = $index;
            $missingTexts[] = $text;
        }

        if ($missingTexts !== []) {
            $batch = $this->embedBatchViaApiEmbed($missingTexts);
            foreach ($missingIndexes as $offset => $index) {
                $embedding = $batch[$offset] ?? null;
                if ($embedding !== null) {
                    $cacheKey = hash('sha256', $this->model . "\n" . $missingTexts[$offset]);
                    $this->cache[$cacheKey] = $embedding;
                    $this->writeCached($cacheKey, $embedding);
                }

                $results[$index] = $embedding;
            }
        }

        ksort($results);

        return array_values($results);
    }

    /**
     * @return array<int, float>|null
     */
    private function embedViaApiEmbed(string $text): ?array
    {
        $payload = $this->request('/api/embed', [
            'model' => $this->model,
            'input' => $text,
        ]);
        $embeddings = is_array($payload['embeddings'] ?? null) ? $payload['embeddings'] : [];
        $embedding = is_array($embeddings[0] ?? null) ? $embeddings[0] : null;

        return $this->normalizeEmbedding($embedding);
    }

    /**
     * @param array<int, string> $texts
     * @return array<int, array<int, float>|null>
     */
    private function embedBatchViaApiEmbed(array $texts): array
    {
        $payload = $this->request('/api/embed', [
            'model' => $this->model,
            'input' => array_values($texts),
        ]);
        $embeddings = is_array($payload['embeddings'] ?? null) ? $payload['embeddings'] : [];

        return array_map(
            fn(mixed $embedding): ?array => is_array($embedding) ? $this->normalizeEmbedding($embedding) : null,
            $embeddings,
        );
    }

    /**
     * @return array<int, float>|null
     */
    private function embedViaApiEmbeddings(string $text): ?array
    {
        $payload = $this->request('/api/embeddings', [
            'model' => $this->model,
            'prompt' => $text,
        ]);

        return $this->normalizeEmbedding(is_array($payload['embedding'] ?? null) ? $payload['embedding'] : null);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function request(string $path, array $payload): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => $this->timeout,
            ],
        ]);

        $raw = @file_get_contents($this->baseUrl . $path, false, $context);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<int, mixed>|null $embedding
     * @return array<int, float>|null
     */
    private function normalizeEmbedding(?array $embedding): ?array
    {
        if ($embedding === null || $embedding === []) {
            return null;
        }

        $normalized = [];
        foreach ($embedding as $value) {
            if (!is_numeric($value)) {
                return null;
            }

            $normalized[] = (float) $value;
        }

        return $normalized !== [] ? $normalized : null;
    }

    private function defaultCacheDirectory(): ?string
    {
        if (!defined('BASE_PATH')) {
            return null;
        }

        return rtrim((string) constant('BASE_PATH'), '/') . '/storage/cache/ai-embeddings';
    }

    private function cachePath(string $cacheKey): ?string
    {
        if ($this->cacheDirectory === null || $this->cacheDirectory === '') {
            return null;
        }

        return rtrim($this->cacheDirectory, '/') . '/' . substr($cacheKey, 0, 2) . '/' . $cacheKey . '.json';
    }

    /**
     * @return array<int, float>|null
     */
    private function readCached(string $cacheKey): ?array
    {
        $path = $this->cachePath($cacheKey);
        if ($path === null || !is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (($payload['model'] ?? '') !== $this->model || !is_array($payload['embedding'] ?? null)) {
            return null;
        }

        return $this->normalizeEmbedding($payload['embedding']);
    }

    /**
     * @param array<int, float> $embedding
     */
    private function writeCached(string $cacheKey, array $embedding): void
    {
        $path = $this->cachePath($cacheKey);
        if ($path === null) {
            return;
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }

        try {
            $payload = json_encode([
                'model' => $this->model,
                'embedding' => $embedding,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable) {
            return;
        }

        $temporary = $path . '.' . str_replace('.', '', uniqid('', true)) . '.tmp';
        if (@file_put_contents($temporary, $payload, LOCK_EX) === false) {
            return;
        }

        @rename($temporary, $path);
    }
}
