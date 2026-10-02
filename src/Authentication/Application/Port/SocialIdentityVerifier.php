<?php

declare(strict_types=1);

namespace App\Authentication\Application\Port;

use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\SocialProvider;

interface SocialIdentityVerifier
{
    /**
     * Verifies the ID token a sign-in with the provider produced (signature, issuer, audience, expiry, and that it
     * was requested with this nonce).
     *
     * @param string $nonce the raw nonce the client generated; the token must carry its SHA-256 hex digest
     *
     * @throws AuthenticationProblem     social-token-invalid when the token is not acceptable
     * @throws SocialProviderUnavailable when the provider's keys can't be fetched
     */
    public function verify(SocialProvider $provider, string $idToken, string $nonce): VerifiedSocialIdentity;
}
