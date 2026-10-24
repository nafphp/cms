<?php

declare(strict_types=1);

namespace Naf\CMS\Content\Page\Block;

/** @api */
interface ProvidesBlockViewInterface
{
    public function view(): string;
}
