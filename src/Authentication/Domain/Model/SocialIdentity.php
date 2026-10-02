<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Model;

final readonly class SocialIdentity
{
    public function __construct(
        public SocialProvider $provider,
        public SocialSubject $subject,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->provider === $other->provider && $this->subject->equals($other->subject);
    }
}
