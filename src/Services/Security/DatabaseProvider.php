<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Security;

use Naf\Auth\Credentials\CredentialsInterface;
use Naf\Auth\Credentials\PasswordCredentials;
use Naf\Auth\Identity\IdentityInterface;
use Naf\Auth\Provider\ProviderInterface;
use Naf\CMS\Models\User;
use Naf\CMS\Repositories\UserRepository;
use SensitiveParameter;

use function Naf\ORM\repo;

final readonly class DatabaseProvider implements ProviderInterface
{
    public function __construct(private CredentialService $credentials)
    {
    }

    public function find(string $identifier): ?IdentityInterface
    {
        if (!ctype_digit($identifier) || (string) (int) $identifier !== $identifier || (int) $identifier <= 0) {
            return null;
        }
        $user = repo(UserRepository::class)->findOneBy('id', (int) $identifier);

        return $user instanceof User ? new UserIdentity($user) : null;
    }

    public function authenticate(#[SensitiveParameter] CredentialsInterface $credentials): ?IdentityInterface
    {
        if (!$credentials instanceof PasswordCredentials) {
            return null;
        }
        $repository   = repo(UserRepository::class);
        $user         = $repository->findOneBy('username', $credentials->username);
        $verification = $this->credentials->verifyPassword($credentials->password, $user instanceof User ? $user->getPassword() : null);
        if (!$user instanceof User || !$verification->isVerified()) {
            return null;
        }
        if ($verification->requiresUpgrade()) {
            $repository->rehashAuthenticatedPassword((int) $user->getId(), $credentials->password);
        }

        return new UserIdentity($user);
    }
}
