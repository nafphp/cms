<?php

declare(strict_types=1);

namespace NixPHP\CMS\Enums;

enum Permission: string
{
    case ADMIN_ACCESS = 'admin.access';
    case SYSTEM_READ = 'system.read';
    case USERS_VIEW = 'users.view';
    case USERS_CREATE = 'users.create';
    case USERS_EDIT = 'users.edit';
    case USERS_DELETE = 'users.delete';
    case ROLES_MANAGE = 'roles.manage';
    case PERMISSIONS_MANAGE = 'permissions.manage';
    case ARTICLES_VIEW = 'articles.view';
    case ARTICLES_CREATE = 'articles.create';
    case ARTICLES_EDIT = 'articles.edit';
    case ARTICLES_REVIEW = 'articles.review';
    case ARTICLES_PUBLISH = 'articles.publish';
    case ARTICLES_SCHEDULE = 'articles.schedule';
    case ARTICLES_ARCHIVE = 'articles.archive';
    case ARTICLES_DELETE = 'articles.delete';
    case PAGES_VIEW = 'pages.view';
    case PAGES_CREATE = 'pages.create';
    case PAGES_EDIT = 'pages.edit';
    case PAGES_DELETE = 'pages.delete';
    case THEMES_VIEW = 'themes.view';
    case THEMES_CREATE = 'themes.create';
    case THEMES_EDIT = 'themes.edit';
    case THEMES_DELETE = 'themes.delete';
    case THEMES_CODE_MANAGE = 'themes.code.manage';
    case NAVIGATION_VIEW = 'navigation.view';
    case NAVIGATION_CREATE = 'navigation.create';
    case NAVIGATION_EDIT = 'navigation.edit';
    case NAVIGATION_DELETE = 'navigation.delete';
    case FORMS_VIEW = 'forms.view';
    case FORMS_CREATE = 'forms.create';
    case FORMS_EDIT = 'forms.edit';
    case FORMS_DELETE = 'forms.delete';
    case FORMS_SUBMISSIONS_VIEW = 'forms.submissions.view';
    case MEDIA_VIEW = 'media.view';
    case MEDIA_UPLOAD = 'media.upload';
    case MEDIA_EDIT = 'media.edit';
    case MEDIA_DELETE = 'media.delete';
    case QUEUE_READ = 'queue.read';
    case MCP_TOOLS_CALL = 'mcp.tools.call';
    case AI_INSTRUCTIONS_MANAGE = 'ai.instructions.manage';
}
