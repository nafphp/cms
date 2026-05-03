<?php

declare(strict_types=1);

namespace NixCMS\Examples\CustomContentType;

use NixPHP\ORM\Model\AbstractModel;

final class ExampleProduct extends AbstractModel
{
    protected ?string $title = null;

    public function title(): ?string
    {
        return $this->title;
    }
}
