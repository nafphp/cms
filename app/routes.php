<?php

declare(strict_types=1);

use Cms\Controllers\Admin\AuthController;
use Cms\Controllers\Admin\DashboardController;
use Cms\Controllers\Admin\ArticleController;
use Cms\Controllers\Admin\RoleController;
use Cms\Controllers\Admin\UserController;
use Cms\Controllers\Api\AdminDataController;
use Cms\Controllers\Api\ArticleContentController;
use Cms\Controllers\Api\ArticleWorkflowController;
use Cms\Controllers\Api\PermissionApiController;
use Cms\Controllers\Api\RoleApiController as ApiRoleController;
use Cms\Controllers\Api\UserApiController as ApiUserController;
use Cms\Controllers\Web\HomeController;
use Cms\Controllers\Web\InviteRegistrationController;
use function NixPHP\route;

route()->add('GET', '/', [HomeController::class, 'index'], 'home');
route()->add('GET', '/register/{token}', [InviteRegistrationController::class, 'show'], 'auth.register');
route()->add('POST', '/register/{token}', [InviteRegistrationController::class, 'register'], 'auth.register.submit');

route()->add('GET', '/admin/login', [AuthController::class, 'showLogin'], 'auth.login');
route()->add('POST', '/admin/login', [AuthController::class, 'login'], 'auth.login.submit');
route()->add('GET', '/admin/logout', [AuthController::class, 'logout'], 'auth.logout');

route()->add('GET', '/admin', [DashboardController::class, 'index'], 'admin.dashboard');
route()->add('GET', '/admin/articles', [ArticleController::class, 'index'], 'admin.articles');
route()->add('GET', '/admin/articles/create', [ArticleController::class, 'create'], 'admin.articles.create');
route()->add('GET', '/admin/articles/{articleId}/version/create', [ArticleController::class, 'createVersion'], 'admin.articles.version.create');
route()->add('GET', '/admin/articles/{articleId}/{language}/create', [ArticleController::class, 'createTranslation'], 'admin.articles.translation.create');
route()->add('GET', '/admin/articles/{articleId}/{language}/edit', [ArticleController::class, 'edit'], 'admin.articles.edit');
route()->add('GET', '/admin/articles/{articleId}/{language}/{version}/edit', [ArticleController::class, 'editVersion'], 'admin.articles.version.edit');
route()->add('GET', '/admin/articles/{articleId}/{language}/preview', [ArticleController::class, 'preview'], 'admin.articles.preview');
route()->add('GET', '/admin/users', [UserController::class, 'index'], 'admin.users');
route()->add('GET', '/admin/users/create', [UserController::class, 'create'], 'admin.users.create');
route()->add('GET', '/admin/users/{userId}/edit', [UserController::class, 'edit'], 'admin.users.edit');
route()->add('GET', '/admin/roles', [RoleController::class, 'index'], 'admin.roles');

route()->add('GET', '/api/admin/summary', [AdminDataController::class, 'summary'], 'api.admin.summary');
route()->add('GET', '/api/admin/users', [AdminDataController::class, 'users'], 'api.admin.users');
route()->add('GET', '/api/admin/users/list', [ApiUserController::class, 'list'], 'api.admin.users.list');
route()->add('POST', '/api/admin/users/store', [ApiUserController::class, 'store'], 'api.admin.users.store');
route()->add('POST', '/api/admin/users/update', [ApiUserController::class, 'update'], 'api.admin.users.update');
route()->add('POST', '/api/admin/users/roles', [ApiUserController::class, 'updateRoles'], 'api.admin.users.roles');
route()->add('POST', '/api/admin/users/delete', [ApiUserController::class, 'delete'], 'api.admin.users.delete');
route()->add('POST', '/api/admin/users/invite', [ApiUserController::class, 'createInvite'], 'api.admin.users.invite');
route()->add('GET', '/api/admin/roles', [AdminDataController::class, 'roles'], 'api.admin.roles');
route()->add('GET', '/api/admin/rbac/roles', [ApiRoleController::class, 'list'], 'api.admin.rbac.roles');
route()->add('POST', '/api/admin/rbac/roles/create', [ApiRoleController::class, 'create'], 'api.admin.rbac.roles.create');
route()->add('POST', '/api/admin/rbac/roles/update', [ApiRoleController::class, 'update'], 'api.admin.rbac.roles.update');
route()->add('POST', '/api/admin/rbac/roles/delete', [ApiRoleController::class, 'delete'], 'api.admin.rbac.roles.delete');
route()->add('POST', '/api/admin/rbac/roles/permissions', [ApiRoleController::class, 'updatePermissions'], 'api.admin.rbac.roles.permissions');
route()->add('GET', '/api/admin/rbac/permissions', [PermissionApiController::class, 'list'], 'api.admin.rbac.permissions');
route()->add('POST', '/api/admin/rbac/permissions/create', [PermissionApiController::class, 'create'], 'api.admin.rbac.permissions.create');
route()->add('POST', '/api/admin/rbac/permissions/update', [PermissionApiController::class, 'update'], 'api.admin.rbac.permissions.update');
route()->add('POST', '/api/admin/rbac/permissions/delete', [PermissionApiController::class, 'delete'], 'api.admin.rbac.permissions.delete');
route()->add('GET', '/api/admin/articles', [AdminDataController::class, 'articles'], 'api.admin.articles');
route()->add('GET', '/api/admin/articles/{articleId}/{language}/next', [ArticleContentController::class, 'nextVersion'], 'api.admin.articles.next');
route()->add('POST', '/admin/articles/store', [ArticleContentController::class, 'store'], 'admin.articles.store');
route()->add('POST', '/admin/articles/image', [ArticleContentController::class, 'uploadImage'], 'admin.articles.image');
route()->add('POST', '/api/admin/articles/{articleId}/{language}/publish', [ArticleWorkflowController::class, 'publish'], 'api.admin.articles.publish');
route()->add('POST', '/api/admin/articles/{articleId}/{language}/review', [ArticleWorkflowController::class, 'review'], 'api.admin.articles.review');
route()->add('POST', '/api/admin/articles/{articleId}/{language}/delete', [ArticleWorkflowController::class, 'delete'], 'api.admin.articles.delete');
route()->add('POST', '/api/admin/articles/{articleId}/{language}/{version}/delete', [ArticleWorkflowController::class, 'deleteVersion'], 'api.admin.articles.version.delete');
route()->add('POST', '/api/admin/articles/{articleId}/translate/enqueue', [ArticleWorkflowController::class, 'enqueueTranslation'], 'api.admin.articles.translate.enqueue');
