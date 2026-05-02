<?php

declare(strict_types=1);

namespace NixCMS\Content\Form;

use NixCMS\Api\FormApi;
use NixCMS\Content\ContentTypeInterface;
use NixCMS\Models\Form;
use NixCMS\Repositories\FormRepository;

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
