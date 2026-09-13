<?php

declare(strict_types=1);

namespace Naf\CMS\Enums;

enum ArticleStatus: string
{
    case DRAFT = 'draft';
    case REVIEW = 'review';
    case DECLINED = 'declined';
    case PUBLISHED = 'published';
    case SCHEDULED = 'scheduled';
    case ARCHIVED = 'archived';
}
