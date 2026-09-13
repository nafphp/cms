<?php

declare(strict_types=1);

use Naf\CMS\Content\Article\ArticleContentType;
use Naf\CMS\Content\Form\FormContentType;
use Naf\CMS\Content\Media\MediaContentType;
use Naf\CMS\Content\Navigation\NavigationContentType;

return [
    new ArticleContentType(),
    new NavigationContentType(),
    new FormContentType(),
    new MediaContentType(),
];
