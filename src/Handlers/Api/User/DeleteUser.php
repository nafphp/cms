<?php

declare(strict_types=1);

namespace NixPHP\CMS\Handlers\Api\User;

use NixPHP\CMS\Exceptions\PrivilegedActionDenied;
use NixPHP\CMS\Handlers\Support\ReadsJsonBody;
use NixPHP\CMS\Handlers\Support\RejectsPrivilegedAction;
use NixPHP\CMS\Repositories\UserRepository;
use NixPHP\CMS\Support\AuditEntry;
use Psr\Http\Message\ResponseInterface;
use function NixPHP\json;
use function NixPHP\ORM\repo;
use function NixPHP\CMS\user;

final class DeleteUser
{
    use ReadsJsonBody;
    use RejectsPrivilegedAction;

    public function __invoke(): ResponseInterface
    {
        $data = $this->jsonBody();
        $userId = (int) ($data['userId'] ?? 0);
        if ($userId <= 0) {
            return json(['success' => false, 'reason' => 'Invalid user id.'], 400);
        }

        $repository = repo(UserRepository::class);
        $old = $repository->findOneForForm($userId);
        try {
            $deleted = $repository->deleteById((int) user()?->get()?->getId(), $userId);
        } catch (PrivilegedActionDenied $exception) {
            return $this->privilegedActionDenied($exception, 'user.delete', 'user', (string) $userId);
        }
        if (!$deleted) {
            return json(['success' => false, 'reason' => 'Could not delete user.'], 400);
        }

        AuditEntry::create('user.deleted', 'user', (string) $userId, $old)->record();

        return json(['success' => true]);
    }
}
