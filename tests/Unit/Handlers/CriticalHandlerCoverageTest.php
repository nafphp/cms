<?php

declare(strict_types=1);

namespace Tests\Unit\Handlers;

use PHPUnit\Framework\TestCase;

/**
 * Tripwire for the write-handler test scope.
 *
 * Adding a handler below one of these risk-bearing directories requires an
 * explicit review here and a focused behavior test in the mirrored test
 * directory. This keeps the scope visible instead of relying on a stale count.
 */
final class CriticalHandlerCoverageTest extends TestCase
{
    private const array REVIEWED = [
        'Api/Ai/AppendAiMemory.php',
        'Api/Ai/CallAiTool.php',
        'Api/Ai/DeleteAiMistakes.php',
        'Api/Ai/ListAiPromptFiles.php',
        'Api/Ai/ListAiTools.php',
        'Api/Ai/RecordAiMistake.php',
        'Api/Ai/ReviewAiMistakes.php',
        'Api/Ai/SaveAiPromptFile.php',
        'Api/Ai/SelectAiTools.php',
        'Api/Article/DeleteArticleLocale.php',
        'Api/Article/DeleteArticleVersion.php',
        'Api/Article/EnqueueArticleTranslation.php',
        'Api/Article/NextArticleVersion.php',
        'Api/Article/PublishArticle.php',
        'Api/Article/ReviewArticle.php',
        'Api/Article/UploadArticleImage.php',
        'Api/Form/DeleteForm.php',
        'Api/Form/SubmitForm.php',
        'Api/Media/DeleteMedia.php',
        'Api/Media/ListMedia.php',
        'Api/Media/ServeMedia.php',
        'Api/Media/UpdateMedia.php',
        'Api/Media/UploadMedia.php',
        'Api/Navigation/DeleteNavigation.php',
        'Api/Navigation/PublishNavigation.php',
        'Api/Page/DeletePage.php',
        'Api/Page/DeletePageLayout.php',
        'Api/Page/StorePageLayout.php',
        'Api/Rbac/CreatePermission.php',
        'Api/Rbac/CreateRole.php',
        'Api/Rbac/DeletePermission.php',
        'Api/Rbac/DeleteRole.php',
        'Api/Rbac/ListPermissions.php',
        'Api/Rbac/ListRoles.php',
        'Api/Rbac/UpdatePermission.php',
        'Api/Rbac/UpdateRole.php',
        'Api/Rbac/UpdateRolePermissions.php',
        'Api/Settings/GetTranslations.php',
        'Api/Settings/UpdateSettings.php',
        'Api/Settings/UpdateTranslations.php',
        'Api/Theme/StoreTheme.php',
        'Api/User/CreateInvite.php',
        'Api/User/DeleteUser.php',
        'Api/User/ListUsers.php',
        'Api/User/StoreUser.php',
        'Api/User/UpdateUser.php',
        'Api/User/UpdateUserPassword.php',
        'Api/User/UpdateUserRoles.php',
        'Api/User/UserPayloadValidator.php',
        'Page/Auth/LoginUser.php',
        'Page/Auth/RegisterInvitedUser.php',
        'Page/Article/StoreArticleDraft.php',
        'Page/Form/StoreForm.php',
        'Page/Navigation/StoreNavigation.php',
        'Page/Pages/StorePage.php',
        'Page/Theme/StoreTheme.php',
    ];

    public function testCriticalHandlerInventoryCannotGrowSilently(): void
    {
        $root   = BASE_PATH . '/src/Handlers';
        $actual = [];
        foreach ([
            'Api/Ai',
            'Api/Article',
            'Api/Form',
            'Api/Media',
            'Api/Navigation',
            'Api/Page',
            'Api/Rbac',
            'Api/Settings',
            'Api/Theme',
            'Api/User',
            'Page/Article',
            'Page/Auth',
            'Page/Form',
            'Page/Navigation',
            'Page/Pages',
            'Page/Theme',
        ] as $directory) {
            foreach (glob($root . '/' . $directory . '/*.php') ?: [] as $file) {
                $actual[] = substr($file, strlen($root) + 1);
            }
        }

        sort($actual);
        $reviewed = self::REVIEWED;
        sort($reviewed);
        self::assertSame($reviewed, $actual);
    }

    public function testReviewedHandlersExposeAnExplicitExecutionBoundary(): void
    {
        foreach (self::REVIEWED as $file) {
            $source = (string) file_get_contents(BASE_PATH . '/src/Handlers/' . $file);
            if (str_ends_with($file, 'UserPayloadValidator.php')) {
                self::assertStringContainsString('public function validate(', $source, $file);
                continue;
            }
            self::assertStringContainsString('public function __invoke(', $source, $file);
        }
    }
}
