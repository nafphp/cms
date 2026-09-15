<?php

declare(strict_types=1);

namespace Naf\CMS\Examples\CustomContentType;

use Naf\CMS\Content\ContentTypeInterface;

use function Naf\CMS\cms;

final class ExampleProductContentType implements ContentTypeInterface
{
    public function id(): string
    {
        return 'example_product';
    }

    public function label(): string
    {
        return 'Example product';
    }

    public function modelClass(): string
    {
        return ExampleProduct::class;
    }

    public function api(): ExampleProductApi
    {
        return new ExampleProductApi();
    }
}

cms()->contentTypes()->add(new ExampleProductContentType(), replace: true);
