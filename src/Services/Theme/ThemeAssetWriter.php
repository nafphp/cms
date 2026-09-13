<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Theme;

use Naf\CMS\Support\AtomicFileWriter;
use Naf\CMS\Support\RuntimePaths;
use Naf\CMS\Theme\Theme;
use Naf\CMS\Services\Theme\ThemeCodeNormalizer;

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
