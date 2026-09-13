<?php

declare(strict_types=1);

namespace Naf\CMS\Page;

use Naf\CMS\Page\Layout;
use Naf\CMS\Support\RuntimePaths;
use function Naf\app;

final class PageTemplateCatalog
{
    /** @return array<int, array<string, mixed>> */
    public function codeDefinitions(): array
    {
        $files = [dirname(__DIR__) . '/Resources/pages/page_templates.php'];
        $hostRoot = app()->getBasePath();

        if (is_string($hostRoot) && $hostRoot !== '') {
            $hostFile = RuntimePaths::hostResource('pages/page_templates.php');
            if (realpath($hostFile) !== realpath($files[0])) {
                $files[] = $hostFile;
            }
        }

        $definitions = [];
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $layouts = require $file;
            if (!is_array($layouts)) {
                continue;
            }

            foreach (Layout::definitions($layouts) as $definition) {
                $id = trim((string) ($definition['id'] ?? ''));
                if ($id !== '') {
                    $definitions[$id] = $definition;
                }
            }
        }

        return array_values($definitions);
    }
}
