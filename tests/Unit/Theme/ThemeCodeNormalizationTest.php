<?php

declare(strict_types=1);

namespace Tests\Unit\Theme;

use Naf\CMS\Repositories\ThemeRepository;
use Naf\CMS\Services\Theme\ThemeCodeNormalizer;
use Tests\Support\DatabaseTestCase;

final class ThemeCodeNormalizationTest extends DatabaseTestCase
{
    public function testThemeAssetWriterNormalizesCrLfLineEndings(): void
    {
        self::assertSame("a\nb\nc\n", (new ThemeCodeNormalizer())->normalize("a\r\nb\rc\r\n"));
    }

    public function testThemeRepositoryStoresNormalizedCssButDiscardsCustomJavaScript(): void
    {
        $this->createSchema(<<<SQL
            CREATE TABLE themes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                identifier VARCHAR(120) NOT NULL UNIQUE,
                label VARCHAR(190) NOT NULL,
                surfaces_json TEXT NOT NULL,
                css TEXT NOT NULL,
                js TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL
            )
        SQL);

        $id = (new ThemeRepository($this->pdo))->save([
            'label'      => 'Brand',
            'identifier' => 'brand',
            'surfaces'   => ['card' => 'Card'],
            'css'        => ".theme-brand {\r\n    color: red;\r\n}\r\n",
            'js'         => "export default {\r\n    mounted() {}\r\n};\r\n",
        ]);

        $row = $this->fetchOne('SELECT css, js FROM themes WHERE id = :id', ['id' => $id]);

        self::assertSame(".theme-brand {\n    color: red;\n}\n", $row['css'] ?? null);
        self::assertSame('', $row['js'] ?? null);
        self::assertSame([], (new ThemeRepository($this->pdo))->themes()[0]->scripts());
    }
}
