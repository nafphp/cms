<?php

declare(strict_types=1);

use src\Api\ApiEndpoint;
use src\Enums\Permission;
use src\Handlers\Api\Form\DeleteForm;
use src\Handlers\Api\Form\SubmitForm;

return [
    ApiEndpoint::post('api.admin.forms.delete', '/api/admin/forms/{id}/delete')
        ->requirePermission(Permission::FORMS_DELETE)
        ->setHandler(DeleteForm::class),

    ApiEndpoint::post('api.forms.submit', '/api/forms/{identifier}/submit')
        ->setHandler(SubmitForm::class),
];
