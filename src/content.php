<?php

declare(strict_types=1);

use src\Content\Article\ArticleContentType;
use src\Content\Form\FormContentType;
use src\Content\Media\MediaContentType;
use src\Content\Navigation\NavigationContentType;

return [
    new ArticleContentType(),
    new NavigationContentType(),
    new FormContentType(),
    new MediaContentType(),
];
