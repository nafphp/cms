<?php

declare(strict_types=1);

namespace src\Support;

final class AtomicFileWriter
{
    public function write(string $path, string $contents, int $mode = 0640): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Could not create runtime directory "%s".', $directory));
        }

        $temporary = $directory . '/.' . basename($path) . '.' . bin2hex(random_bytes(8)) . '.tmp';

        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
                throw new \RuntimeException(sprintf('Could not write temporary file for "%s".', $path));
            }

            if (!chmod($temporary, $mode)) {
                throw new \RuntimeException(sprintf('Could not set permissions for "%s".', $path));
            }

            if (!rename($temporary, $path)) {
                throw new \RuntimeException(sprintf('Could not publish "%s" atomically.', $path));
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }
}
