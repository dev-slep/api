<?php

declare(strict_types=1);

namespace App\Audit\Domain\Model;

use InvalidArgumentException;

/**
 * Who caused an entry: a signed-in user (id and role), somebody not signed in (login, register), or the system
 * itself (worker, scheduler, console).
 */
final readonly class Actor
{
    private function __construct(
        public ActorType $type,
        public ?string $userId,
        public ?string $role,
    ) {
    }

    public static function user(string $userId, ?string $role): self
    {
        if ('' === $userId) {
            throw new InvalidArgumentException('A user actor needs an id.');
        }

        return new self(ActorType::User, $userId, $role);
    }

    public static function anonymous(): self
    {
        return new self(ActorType::Anonymous, null, null);
    }

    public static function system(): self
    {
        return new self(ActorType::System, null, null);
    }
}
