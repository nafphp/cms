<?php

declare(strict_types=1);

namespace Naf\CMS\Enums;

enum Role: string
{
    case ADMIN = 'admin';
    case EDITOR = 'editor';
    case AUTHOR = 'author';
    case TRANSLATOR = 'translator';
    case REVIEWER = 'reviewer';
    case VIEWER = 'viewer';
}
