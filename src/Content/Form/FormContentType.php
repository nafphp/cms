<?php

declare(strict_types=1);

namespace NixPHP\CMS\Content\Form;

use NixPHP\CMS\Api\FormApi;
use NixPHP\CMS\Content\ContentTypeInterface;
use NixPHP\CMS\Models\Form;
use NixPHP\CMS\Repositories\FormRepository;

final class FormContentType implements ContentTypeInterface
{
    private ?FormApi $api = null;

    public function id(): string
    {
        return 'form';
    }

    public function label(): string
    {
        return 'Form';
    }

    public function modelClass(): string
    {
        return Form::class;
    }

    public function api(): FormApi
    {
        return $this->api ??= new FormApi(new FormRepository());
    }
}
