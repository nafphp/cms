<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers\Api\Page;

use Naf\CMS\Examples\BlockType\ExampleQuoteBlockType;
use Naf\CMS\Handlers\Api\Page\PreviewPageBlock;
use Naf\CMS\Support\Cms;
use Naf\Core\Config;
use PHPUnit\Framework\TestCase;
use Tests\Support\CoreBlockTypes;
use Tests\Support\JsonRequest;

use function Naf\app;
use function Naf\CMS\cms;

final class PreviewPageBlockTest extends TestCase
{
    private Cms $previousCms;
    private Config $previousConfig;

    /**
     * The example file registers its type on cms() when it is first loaded.
     * Load it before setUp() swaps in a fresh registry, so that registration
     * reaches the application registry like in every other test.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once BASE_PATH . '/examples/03-block-type/block_type.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousCms    = cms();
        $this->previousConfig = app()->container()->get(Config::class);
        $fresh                = new Cms();
        $fresh->blockTypes()->addMany(CoreBlockTypes::all());
        cms(override: $fresh);
    }

    protected function tearDown(): void
    {
        cms(override: $this->previousCms);
        app()->container()->set(Config::class, $this->previousConfig);
        parent::tearDown();
    }

    public function testRendersAValidBlockWithItsRealView(): void
    {
        [$status, $payload] = $this->preview(['type' => 'quote', 'props' => ['quote' => 'Preview <quote>', 'source' => 'Ada']]);

        self::assertSame(200, $status);
        self::assertTrue($payload['success']);
        self::assertStringContainsString('<!doctype html>', strtolower($payload['html']));
        self::assertStringContainsString('Preview &lt;quote&gt;', $payload['html'], 'stored output stays escaped');
    }

    public function testRendersAPluginBlockThroughItsConventionalView(): void
    {
        cms()->blockTypes()->add(new ExampleQuoteBlockType(), replace: true);
        $values                  = $this->previousConfig->all();
        $values['view']['paths'] = [BASE_PATH . '/examples/03-block-type/src/views', ...(array) ($values['view']['paths'] ?? [])];
        app()->container()->set(Config::class, new Config($values));

        [$status, $payload] = $this->preview(['type' => 'example_quote', 'props' => ['quote' => 'Plugin quote', 'source' => 'A reader']]);

        self::assertSame(200, $status);
        self::assertStringContainsString('Plugin quote', $payload['html']);
        self::assertStringContainsString('A reader', $payload['html']);
    }

    public function testRejectsAMissingBlock(): void
    {
        [$status, $payload] = $this->preview(null);

        self::assertSame(400, $status);
        self::assertFalse($payload['success']);
    }

    public function testRejectsInvalidDataLikeASave(): void
    {
        [$status, $payload] = $this->preview(['type' => 'unknown-block', 'props' => []]);
        self::assertSame(422, $status);
        self::assertSame(['block uses unknown block type "unknown-block".'], $payload['errors']['content']);

        [$status, $payload] = $this->preview(['type' => 'embed', 'props' => ['url' => 'javascript:alert(1)']]);
        self::assertSame(422, $status);
        self::assertSame(['block.props.url contains an unsafe URL.'], $payload['errors']['content']);
    }

    public function testPreviewsBlocksTheBuilderNoLongerOffers(): void
    {
        cms()->blockTypes()->disable('quote');

        [$status] = $this->preview(['type' => 'quote', 'props' => ['quote' => 'Existing']]);

        self::assertSame(200, $status, 'existing blocks of a disabled type still need a preview');
    }

    /**
     * @param array<string, mixed>|null $content
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function preview(?array $content): array
    {
        JsonRequest::bind($content === null ? [] : ['content' => $content], '/api/admin/pages/block-preview');
        $response = (new PreviewPageBlock())();

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)];
    }
}
