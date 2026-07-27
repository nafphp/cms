<?php

declare(strict_types=1);

namespace src\Services\Theme;

use src\Support\AtomicFileWriter;
use src\Support\RuntimePaths;
use src\Theme\Theme;
use src\Services\Theme\ThemeCodeNormalizer;

final class ThemeAssetWriter
{
    public function __construct(
        private ?ThemeCodeNormalizer $normalizer = null,
        private readonly ?string $publicRoot = null,
        private readonly ?AtomicFileWriter $writer = null,
    ) {
        $this->normalizer ??= new ThemeCodeNormalizer();
    }

    public function write(string $identifier, string $css): void
    {
        $identifier = Theme::slug($identifier);
        if ($identifier === '') {
            throw new \InvalidArgumentException('Theme identifier cannot be empty.');
        }

        $root = rtrim($this->publicRoot ?? RuntimePaths::publicRoot(), '/\\');
        ($this->writer ?? new AtomicFileWriter())->write(
            $root . '/css/themes/' . $identifier . '.css',
            $this->normalizer->normalize($css),
            0644,
        );
    }
}
