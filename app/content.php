<?php

declare(strict_types=1);

use NixCMS\Content\Article\ArticleContentType;
use NixCMS\Content\Form\FormContentType;
use NixCMS\Content\Media\MediaContentType;
use NixCMS\Content\Navigation\NavigationContentType;

return [
    new ArticleContentType(),
    new NavigationContentType(),
    new FormContentType(),
    new MediaContentType(),
];
