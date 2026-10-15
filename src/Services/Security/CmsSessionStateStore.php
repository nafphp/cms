<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Security;

use Naf\Auth\Session\SessionStateStore;
use Naf\CMS\Models\User;
use Naf\CMS\Repositories\UserRepository;
use Naf\Session\Core\Session;
use RuntimeException;
use SensitiveParameter;

use function Naf\ORM\repo;

/** Bind persisted authentication to the credentials that established it. */
final class CmsSessionStateStore extends SessionStateStore
{
    private const PASSWORD_KEY = 'cms.auth.password';
    /** @var array{identifier: string, fingerprint: string}|null */
    private ?array $verifiedPassword = null;

    public function __construct(private readonly Session $cmsSession)
    {
        parent::__construct($cmsSession);
    }

    public function read(): ?array
    {
        $record = parent::read();
        if ($record === null) {
            $this->cmsSession->forget('userId');

            return null;
        }
        if ($record['provider'] !== 'database') {
            return $record;
        }

        $stored  = $this->cmsSession->get(self::PASSWORD_KEY);
        $current = $this->passwordFingerprint($record['identifier']);
        if (!is_string($stored) || $current === null || !hash_equals($current, $stored)) {
            $this->clear();

            return null;
        }

        return $record;
    }

    public function verifiedPassword(string $identifier, #[SensitiveParameter] string $hash): void
    {
        $this->verifiedPassword = ['identifier' => $identifier, 'fingerprint' => hash('sha256', $hash)];
    }

    public function write(string $provider, string $identifier): void
    {
        $verified               = $this->verifiedPassword;
        $this->verifiedPassword = null;
        $fingerprint            = $provider === 'database' ? $this->passwordFingerprint($identifier) : null;
        if ($provider === 'database' && ($fingerprint === null || ($verified !== null
            && ($verified['identifier'] !== $identifier || !hash_equals($verified['fingerprint'], $fingerprint))))) {
            $this->clear();
            throw new RuntimeException('Account credentials changed during authentication.');
        }
        $this->cmsSession->forget('userId');
        $this->cmsSession->forget('user_id');
        $this->cmsSession->forget(self::PASSWORD_KEY);
        parent::write($provider, $identifier);
        if ($provider === 'database') {
            $this->cmsSession->set('userId', (int) $identifier);
            // Store the verified fingerprint. A concurrent reset remains visible
            // on restoration even when an old request writes its session later.
            $this->cmsSession->set(self::PASSWORD_KEY, $verified['fingerprint'] ?? $fingerprint);
        }
    }

    public function clear(): void
    {
        $this->verifiedPassword = null;
        $this->cmsSession->forget(self::PASSWORD_KEY);
        $this->cmsSession->forget('userId');
        $this->cmsSession->forget('user_id');
        parent::clear();
    }

    private function passwordFingerprint(string $identifier): ?string
    {
        if (!ctype_digit($identifier) || (int) $identifier <= 0) {
            return null;
        }
        $user = repo(UserRepository::class)->findOneBy('id', (int) $identifier);
        $hash = $user instanceof User ? $user->getPassword() : null;

        return is_string($hash) && $hash !== '' ? hash('sha256', $hash) : null;
    }
}
