<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use NixCMS\Support\Cms;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class CmsApiSurfaceDocumentedTest extends TestCase
{
    public function testEveryPublicCmsEntryPointIsDocumentedInAgentsFile(): void
    {
        $agents = file_get_contents(BASE_PATH . '/AGENTS.md');
        self::assertIsString($agents);

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
                $agents,
                sprintf('Missing CMS API surface documentation for cms()->%s(...).', $method),
            );
        }
    }
}
