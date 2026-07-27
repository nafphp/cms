<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use NixPHP\CMS\Support\Cms;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use function NixPHP\CMS\cms;

final class CmsApiSurfaceDocumentedTest extends TestCase
{
    public function testEveryPublicCmsEntryPointIsDocumentedInReadme(): void
    {
        $readme = file_get_contents(BASE_PATH . '/README.md');
        self::assertIsString($readme);

        $methods = array_map(
            static fn(ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(Cms::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        );

        $methods = array_values(array_filter(
            $methods,
            static fn(string $method): bool => $method !== '__construct',
        ));
        sort($methods);

        foreach ($methods as $method) {
            self::assertStringContainsString(
                sprintf('cms()->%s(', $method),
                $readme,
                sprintf('Missing CMS API surface documentation for cms()->%s(...).', $method),
            );
        }
    }
}
