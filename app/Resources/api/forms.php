<?php

declare(strict_types=1);

use NixCMS\Api\ApiEndpoint;
use NixCMS\Enums\Permission;
use NixCMS\Handlers\Api\Form\DeleteForm;
use NixCMS\Handlers\Api\Form\SubmitForm;

return [
    ApiEndpoint::post('api.admin.forms.delete', '/api/admin/forms/{id}/delete')
        ->requirePermission(Permission::FORMS_DELETE)
        ->setHandler(DeleteForm::class),

    ApiEndpoint::post('api.forms.submit', '/api/forms/{identifier}/submit')
        ->setHandler(SubmitForm::class),
];
