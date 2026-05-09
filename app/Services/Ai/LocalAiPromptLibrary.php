<?php

declare(strict_types=1);

namespace NixCMS\Services\Ai;

use function NixPHP\log;

final class LocalAiPromptLibrary
{
    private const ORDER = [
        'IDENTITY.md',
        'MISSION.md',
        'MEMORY.md',
    ];

    private const EDITABLE_FILES = [
        'IDENTITY.md',
        'MISSION.md',
        'MEMORY.md',
        'MISTAKES.md',
    ];

    public function __construct(private readonly ?string $directory = null)
    {
    }

    public function compiledPrompt(): string
    {
        $parts = [];
        foreach (self::ORDER as $file) {
            $content = $this->read($file);
            if ($content === '') {
                continue;
            }

            $parts[] = sprintf("## %s\n\n%s", pathinfo($file, PATHINFO_FILENAME), $content);
        }

        return trim(implode("\n\n---\n\n", $parts));
    }

    public function memory(): string
    {
        return $this->read('MEMORY.md');
    }

    public function mistakesLog(int $maxBytes = 24000): string
    {
        $content = $this->read('MISTAKES.md');
        if ($content === '' || strlen($content) <= $maxBytes) {
            return $content;
        }

        return trim(substr($content, -$maxBytes));
    }

    /**
     * @return array<int, array{id: string, content: string}>
     */
    public function mistakeEntries(): array
    {
        $content = $this->read('MISTAKES.md');
        if ($content === '') {
            return [];
        }

        $entries = [];
        foreach (preg_split('/(?=^## \d{4}-\d{2}-\d{2} )/m', $content) ?: [] as $entry) {
            $entry = trim($entry);
            if ($entry === '' || !str_starts_with($entry, '## ')) {
                continue;
            }

            $entries[] = [
                'id' => substr(hash('sha256', $entry), 0, 16),
                'content' => $entry,
            ];
        }

        return $entries;
    }

    /**
     * @param array<int, string> $ids
     */
    public function removeMistakeEntries(array $ids): string
    {
        $ids = array_values(array_filter(array_map('strval', $ids), fn (string $id): bool => $id !== ''));
        if ($ids === []) {
            return $this->promptFile('MISTAKES.md');
        }

        $remove = array_flip($ids);
        $content = $this->promptFile('MISTAKES.md');
        $intro = [];
        $kept = [];

        foreach (preg_split('/(?=^## \d{4}-\d{2}-\d{2} )/m', $content) ?: [] as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (!str_starts_with($part, '## ')) {
                $intro[] = $part;
                continue;
            }

            $id = substr(hash('sha256', $part), 0, 16);
            if (!isset($remove[$id])) {
                $kept[] = $part;
            }
        }

        $next = trim(implode("\n\n", array_merge($intro, $kept)));
        $this->savePromptFile('MISTAKES.md', $next);

        return $next;
    }

    /**
     * @return array<int, array{name: string, content: string, inPrompt: bool}>
     */
    public function promptFiles(): array
    {
        return array_map(fn (string $file): array => [
            'name' => $file,
            'content' => $this->promptFile($file),
            'inPrompt' => in_array($file, self::ORDER, true),
        ], self::EDITABLE_FILES);
    }

    public function promptFile(string $file): string
    {
        $this->assertEditableFile($file);
        $path = $this->path($file);

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    public function savePromptFile(string $file, string $content): string
    {
        $this->assertEditableFile($file);
        if (strlen($content) > 200000) {
            throw new \InvalidArgumentException('Prompt file is too large.');
        }

        $path = $this->path($file);
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($path, rtrim($content) . "\n", LOCK_EX);

        return $this->promptFile($file);
    }

    public function appendMemory(string $sentence): string
    {
        $sentence = $this->memorySentence($sentence);
        if ($sentence === '') {
            throw new \InvalidArgumentException('Memory sentence is required.');
        }

        $path = $this->path('MEMORY.md');
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $content = $this->memory();
        $content = preg_replace('/^No durable assistant memory has been recorded yet\.\s*/i', '', $content) ?? $content;
        $content = trim((string) $content);
        $entry = '- ' . $sentence;

        foreach (preg_split('/\R+/', $content) ?: [] as $line) {
            if (mb_strtolower(trim(ltrim($line, "- \t"))) === mb_strtolower($sentence)) {
                return $content;
            }
        }

        $next = trim($content . "\n\n" . $entry);
        file_put_contents($path, $next . "\n", LOCK_EX);

        return $next;
    }

    public function appendMistake(string $event, string $context = '', string $correction = ''): void
    {
        $path = $this->path('MISTAKES.md');
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $entry = [
            '',
            sprintf('## %s', (new \DateTimeImmutable())->format('Y-m-d H:i:s T')),
            sprintf('- Event: %s', $this->line($event)),
            sprintf('- Context: %s', $this->line($context !== '' ? $context : 'No additional context provided.')),
            sprintf('- Correction: %s', $this->line($correction !== '' ? $correction : 'Tighten the prompt or UI flow so this behavior does not repeat.')),
            '',
        ];

        try {
            file_put_contents($path, implode("\n", $entry), FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            log()->error('LocalAiPromptLibrary.appendMistake failed', [
                'exception' => $e,
                'path' => $path,
                'event' => $event,
            ]);
        }
    }

    private function read(string $file): string
    {
        $path = $this->path($file);
        if (!is_file($path)) {
            return '';
        }

        $content = trim((string) file_get_contents($path));
        return $content;
    }

    private function path(string $file): string
    {
        $basePath = defined('BASE_PATH') ? constant('BASE_PATH') : dirname(__DIR__, 3);

        return rtrim($this->directory ?? $basePath . '/app/Resources/prompts/local-ai', '/') . '/' . $file;
    }

    private function line(string $value): string
    {
        $line = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return $line === '' ? '-' : $line;
    }

    private function memorySentence(string $value): string
    {
        $sentence = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? '');
        $sentence = preg_replace('/^[-*]\s*/', '', $sentence) ?? $sentence;

        return mb_substr($sentence, 0, 240);
    }

    private function assertEditableFile(string $file): void
    {
        if (!in_array($file, self::EDITABLE_FILES, true)) {
            throw new \InvalidArgumentException('Prompt file is not editable.');
        }
    }
}
