<?php

declare(strict_types=1);

namespace src\Enums;

enum Role: string
{
    case ADMIN = 'admin';
    case EDITOR = 'editor';
    case AUTHOR = 'author';
    case TRANSLATOR = 'translator';
    case REVIEWER = 'reviewer';
    case VIEWER = 'viewer';
}
