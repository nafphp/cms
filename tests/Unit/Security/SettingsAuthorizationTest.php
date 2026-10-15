<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use Naf\CMS\Contracts\SettingsServiceInterface;
use Naf\CMS\Contracts\TranslationServiceInterface;
use Naf\CMS\Controllers\Api\ApiController;
use Naf\CMS\Enums\Permission;
use Naf\CMS\Exceptions\AuthException;
use Naf\CMS\Migrations\Migration202610150001;
use Naf\CMS\Repositories\SettingRepository;
use Naf\CMS\Services\Settings\SettingsService;
use Naf\CMS\Services\Settings\TranslationFileService;
use Naf\CMS\Support\UserHelper;
use Naf\Core\Route;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\Support\DatabaseTestCase;
use Tests\Support\JsonRequest;

use function Naf\app;
use function Naf\CMS\cms;
use function Naf\CMS\user;
use function Naf\route;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SettingsAuthorizationTest extends DatabaseTestCase
{
    public function testAdminAccessAloneCannotWriteSettingsOrTranslations(): void
    {
        $this->createSchema('CREATE TABLE cms_settings (setting_key TEXT PRIMARY KEY, value TEXT, updated_at TEXT)');
        $directory = sys_get_temp_dir() . '/cms-authorization-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $settings             = new SettingsService(new SettingRepository($this->pdo));
        $translations         = new TranslationFileService($directory, $directory);
        $container            = app()->container();
        $previousSettings     = $container->get(SettingsServiceInterface::class);
        $previousTranslations = $container->get(TranslationServiceInterface::class);
        $container->set(SettingsServiceInterface::class, $settings);
        $container->set(TranslationServiceInterface::class, $translations);
        cms()->api()->addMany(require BASE_PATH . '/src/Resources/api/settings.php', true);
        cms()->api()->registerRoutes();
        cms()->settings()->addMany(require BASE_PATH . '/src/settings.php', true);
        $router = route();
        self::assertInstanceOf(Route::class, $router);

        try {
            user(new UserHelper(null, [Permission::ADMIN_ACCESS], [], true));
            foreach ([
                ['/api/admin/settings', ['app.name' => 'changed'], []],
                ['/api/admin/translations/en', ['entries' => ['Delete' => 'Save']], ['en']],
            ] as [$path, $payload, $parameters]) {
                JsonRequest::bind($payload, $path);
                $router->find($path, 'POST');

                try {
                    (new ApiController())->handle(...$parameters);
                    self::fail('Viewer was allowed to write.');
                } catch (AuthException $exception) {
                    self::assertSame(403, $exception->getCode());
                }
            }
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM cms_settings')->fetchColumn());
            self::assertFileDoesNotExist($directory . '/en.json');

            user(new UserHelper(null, [Permission::ADMIN_ACCESS, Permission::SETTINGS_MANAGE, Permission::TRANSLATIONS_MANAGE], [], true));
            JsonRequest::bind(['app.name' => 'authorized'], '/api/admin/settings');
            $router->find('/api/admin/settings', 'POST');
            self::assertSame(200, (new ApiController())->handle()->getStatusCode());
            self::assertSame('authorized', $this->pdo->query('SELECT value FROM cms_settings')->fetchColumn());
            JsonRequest::bind(['entries' => ['Hello' => 'Authorized']], '/api/admin/translations/en');
            $router->find('/api/admin/translations/en', 'POST');
            self::assertSame(200, (new ApiController())->handle('en')->getStatusCode());
            self::assertSame('Authorized', $translations->entries('en')['Hello']);
        } finally {
            user(reset: true);
            $container->set(SettingsServiceInterface::class, $previousSettings);
            $container->set(TranslationServiceInterface::class, $previousTranslations);
            foreach (glob($directory . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    public function testUpgradeGrantsNewPermissionsOnlyToAdminsAndRollsBack(): void
    {
        $this->createSchema(
            'CREATE TABLE roles (id INTEGER PRIMARY KEY, name TEXT UNIQUE)',
            'CREATE TABLE permissions (id INTEGER PRIMARY KEY, name TEXT UNIQUE)',
            'CREATE TABLE role_permissions (role_id INTEGER, permission_id INTEGER, PRIMARY KEY (role_id, permission_id))',
        );
        $this->pdo->exec("INSERT INTO roles VALUES (1, 'admin'), (2, 'viewer')");
        $migration = new Migration202610150001();
        $migration->up($this->pdo);
        $migration->up($this->pdo);
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM permissions')->fetchColumn());
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM role_permissions WHERE role_id = 1')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM role_permissions WHERE role_id = 2')->fetchColumn());
        $migration->down($this->pdo);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM permissions')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM role_permissions')->fetchColumn());
    }
}
