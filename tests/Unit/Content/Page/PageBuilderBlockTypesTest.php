<?php

declare(strict_types=1);

namespace Tests\Unit\Content\Page;

use NixCMS\Content\Page\Block\AbstractBlockType;
use NixPHP\Core\Config;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tests\Support\CoreBlockTypes;
use function NixPHP\guard;
use function NixPHP\app;
use function NixCMS\cms;

final class PageBuilderBlockTypesTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPageBuilderReadsBlockTypesRegisteredAfterPagesWereLoaded(): void
    {
        $viewRoot = $this->createBuilderProbeView();
        $app = app();
        $this->registerCliViewGuards();

        self::assertNull(cms()->blockTypes()->get('hero'));

        $config = $app->container()->get(Config::class)->all();
        $config['view']['paths'] = [$viewRoot, 'views', 'app/views'];
        $app->container()->set(Config::class, new Config($config));

        $request = new ServerRequest('GET', '/admin/pages/create');
        $app->container()->set(RequestInterface::class, $request);
        $app->container()->set(ServerRequestInterface::class, $request);

        cms()->blockTypes()->addMany(CoreBlockTypes::all());
        cms()->blockTypes()->add(new class extends AbstractBlockType {
            public function id(): string
            {
                return 'late_plugin';
            }

            public function label(): string
            {
                return 'Late plugin';
            }

            public function regions(): array
            {
                return ['main'];
            }
        });

        $page = cms()->pages()->require('admin.pages.create');
        $content = $page->content();
        $response = $content();

        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertIsArray($payload['contentTypes'] ?? null);

        $types = array_column($payload['contentTypes'], 'type');
        self::assertContains('hero', $types);
        self::assertContains('late_plugin', $types);
    }

    private function createBuilderProbeView(): string
    {
        $viewRoot = sys_get_temp_dir() . '/nixcms-page-builder-test-' . bin2hex(random_bytes(6));
        $directory = $viewRoot . '/admin/pages';
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(
            $directory . '/builder.phtml',
            <<<'PHP'
<?php
echo json_encode(['contentTypes' => $contentTypes ?? null], JSON_THROW_ON_ERROR);
PHP
        );

        return $viewRoot;
    }

    private function registerCliViewGuards(): void
    {
        $guard = guard();

        if (!$guard->has('safePath')) {
            $guard->register('safePath', static fn(string $path): string => $path);
        }

        if (!$guard->has('safeOutput')) {
            $guard->register('safeOutput', static function (mixed $value): mixed {
                if (is_array($value)) {
                    return array_map(
                        static fn(mixed $item): string => htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8'),
                        $value
                    );
                }

                return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            });
        }
    }
}
