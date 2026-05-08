<?php

declare(strict_types=1);

namespace Tests\Unit\Content\Page\Block;

use NixCMS\Content\BlockViewRegistry;
use NixCMS\Content\Page\Block\BlockTypeInterface;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\CoreBlockTypes;
use function NixCMS\cms;

final class BlockTypeContractTest extends TestCase
{
    private const array ALLOWED_REGIONS = [
        'header' => true,
        'hero' => true,
        'main' => true,
        'aside' => true,
        'footer' => true,
    ];

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRegisteredBlockTypesFollowBuilderContract(): void
    {
        cms()->blockTypes()->addMany(CoreBlockTypes::all());

        $types = cms()->blockTypes()->all();
        self::assertGreaterThanOrEqual(27, count($types));

        $seen = [];
        $blockViews = new BlockViewRegistry();

        foreach ($types as $registryId => $type) {
            self::assertInstanceOf(BlockTypeInterface::class, $type);

            $id = $type->id();
            self::assertSame($id, $registryId, sprintf('BlockType "%s" is registered under a different id.', $id));
            self::assertMatchesRegularExpression(
                '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/',
                $id,
                sprintf('BlockType "%s" must use lowercase hyphenated IDs.', $id),
            );
            self::assertArrayNotHasKey($id, $seen, sprintf('BlockType id "%s" must be unique.', $id));
            $seen[$id] = true;

            foreach ($type->regions() as $region) {
                self::assertArrayHasKey(
                    (string) $region,
                    self::ALLOWED_REGIONS,
                    sprintf('BlockType "%s" declares unsupported region "%s".', $id, (string) $region),
                );
            }

            $fieldNames = $this->fieldNames($type);
            foreach (array_keys($type->defaults()) as $defaultKey) {
                self::assertArrayHasKey(
                    (string) $defaultKey,
                    $fieldNames,
                    sprintf('BlockType "%s" default "%s" is not declared in fields().', $id, (string) $defaultKey),
                );
            }

            self::assertFileExists(
                $this->viewFile($blockViews->viewFor($id)),
                sprintf('BlockType "%s" must resolve to an existing block view.', $id),
            );

            $builderArray = $type->toBuilderArray();
            self::assertIsArray($builderArray);
            $encoded = json_encode($builderArray, JSON_THROW_ON_ERROR);
            self::assertIsArray(json_decode($encoded, true, flags: JSON_THROW_ON_ERROR));
        }
    }

    /**
     * @return array<string, true>
     */
    private function fieldNames(BlockTypeInterface $type): array
    {
        $names = [];

        foreach ($type->fields() as $field) {
            self::assertIsArray($field, sprintf('BlockType "%s" fields() must contain arrays.', $type->id()));
            $name = trim((string) ($field['name'] ?? ''));
            self::assertNotSame('', $name, sprintf('BlockType "%s" fields() entries need a name.', $type->id()));
            $names[$name] = true;
        }

        return $names;
    }

    private function viewFile(string $view): string
    {
        return BASE_PATH . '/views/' . str_replace('.', '/', trim($view, '/')) . '.phtml';
    }
}
