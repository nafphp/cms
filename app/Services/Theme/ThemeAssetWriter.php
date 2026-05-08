<?php

declare(strict_types=1);

namespace NixCMS\Services\Theme;

use NixCMS\Theme\Theme;

final class ThemeAssetWriter
{
    public function __construct(private ?ThemeCodeNormalizer $normalizer = null)
    {
        $this->normalizer ??= new ThemeCodeNormalizer();
    }

    public function write(string $identifier, string $css, string $js): void
    {
        $identifier = Theme::slug($identifier);
        if ($identifier === '') {
            throw new \InvalidArgumentException('Theme identifier cannot be empty.');
        }

        $this->writeFile(BASE_PATH . '/public/css/themes/' . $identifier . '.css', $this->normalizer->normalize($css));
        $this->writeFile(BASE_PATH . '/public/js/themes/' . $identifier . '.js', $this->normalizer->normalize($js));
    }

    private function writeFile(string $path, string $content): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Could not create theme asset directory "%s".', $directory));
        }

        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException(sprintf('Could not write theme asset "%s".', $path));
        }
    }
}
