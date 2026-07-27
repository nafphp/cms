<?php

declare(strict_types=1);

namespace src\Services\Developer;

use src\Support\AtomicFileWriter;

final class AgentGuideInstaller
{
    public const MARKER = '<!-- managed-by: fkde/nixcms agent-guide -->';

    public function __construct(
        private readonly string $source,
        private readonly string $destination,
        private readonly ?AtomicFileWriter $writer = null,
    ) {}

    /** @return array{status: string, path: string} */
    public function install(bool $update = false): array
    {
        $template = file_get_contents($this->source);
        if (!is_string($template) || !str_contains($template, self::MARKER)) {
            throw new \RuntimeException('The shipped NixCMS agent guide template is missing or unmanaged.');
        }

        $existed = is_file($this->destination);
        if ($existed) {
            $current = (string) file_get_contents($this->destination);
            if (hash_equals(hash('sha256', $template), hash('sha256', $current))) {
                return ['status' => 'current', 'path' => $this->destination];
            }
            if (!str_contains($current, self::MARKER)) {
                return ['status' => 'unmanaged', 'path' => $this->destination];
            }
            if (!$update) {
                return ['status' => 'update_required', 'path' => $this->destination];
            }
        }

        ($this->writer ?? new AtomicFileWriter())->write($this->destination, $template, 0644);

        return ['status' => $existed ? 'updated' : 'created', 'path' => $this->destination];
    }
}
