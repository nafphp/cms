<?php

declare(strict_types=1);

namespace Cms\Enums;

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
    case MEDIA_VIEW = 'media.view';
    case MEDIA_UPLOAD = 'media.upload';
    case MEDIA_EDIT = 'media.edit';
    case MEDIA_DELETE = 'media.delete';
    case QUEUE_READ = 'queue.read';
    case MCP_TOOLS_CALL = 'mcp.tools.call';
}
