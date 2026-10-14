<?php

declare(strict_types=1);

namespace Naf\CMS\Services\Security;

use Naf\Auth\Session\SessionStateStore;
use Naf\Session\Core\Session;

/** Keep pre-migration sessions and the session table's user_id metadata working. */
final class CmsSessionStateStore extends SessionStateStore
{
    public function __construct(private readonly Session $cmsSession)
    {
        parent::__construct($cmsSession);
    }

    public function read(): ?array
    {
        if ($this->cmsSession->get('auth') !== null) {
            return parent::read();
        }
        $legacyId = $this->cmsSession->get('userId');
        if ((is_int($legacyId) || is_string($legacyId)) && ctype_digit((string) $legacyId) && (int) $legacyId > 0) {
            $record = ['provider' => 'database', 'identifier' => (string) (int) $legacyId];
            $this->cmsSession->set('auth', $record);

            return $record;
        }
        $this->cmsSession->forget('userId');

        return null;
    }

    public function write(string $provider, string $identifier): void
    {
        $this->cmsSession->forget('userId');
        $this->cmsSession->forget('user_id');
        parent::write($provider, $identifier);
        if ($provider === 'database') {
            $this->cmsSession->set('userId', (int) $identifier);
        }
    }

    public function clear(): void
    {
        $this->cmsSession->forget('userId');
        $this->cmsSession->forget('user_id');
        parent::clear();
    }
}
