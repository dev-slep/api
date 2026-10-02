<?php

declare(strict_types=1);

namespace App\Tests\Support\Authentication;

use App\Authentication\Application\Port\SocialIdentityVerifier;
use App\Authentication\Application\Port\VerifiedSocialIdentity;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\SocialIdentity;
use App\Authentication\Domain\Model\SocialProvider;
use App\Authentication\Domain\Model\SocialSubject;

/**
 * Accepts the ID tokens it was told about ("trust"), rejects everything else.
 */
final class FakeSocialIdentityVerifier implements SocialIdentityVerifier
{
    /** @var array<string, VerifiedSocialIdentity> */
    private array $tokens = [];

    public function trust(string $idToken, SocialProvider $provider, string $subject, string $email, bool $emailVerified = true, bool $privateRelayEmail = false): void
    {
        $this->tokens[$provider->value.':'.$idToken] = new VerifiedSocialIdentity(new SocialIdentity($provider, new SocialSubject($subject)), new Email($email), $emailVerified, $privateRelayEmail);
    }

    public function verify(SocialProvider $provider, string $idToken, string $nonce): VerifiedSocialIdentity
    {
        return $this->tokens[$provider->value.':'.$idToken] ?? throw AuthenticationProblem::socialTokenInvalid();
    }
}
