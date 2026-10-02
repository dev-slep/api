<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Jwt;

use App\Authentication\Application\Port\AccessTokenVerifier;
use App\Authentication\Application\Port\AuthenticatedPrincipal;
use App\Authentication\Application\Port\AuthenticationMethod;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\SharedKernel\Domain\Clock;

use function is_array;
use function is_string;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Throwable;

/**
 * Verifies RS256 access tokens. Only that algorithm is accepted (`alg=none` and HMAC tokens signed with the
 * public key are rejected), the signing key is picked by the `kid` header among the configured public keys,
 * and the clock has no leeway.
 */
final readonly class JwtAccessTokenVerifier implements AccessTokenVerifier
{
    public function __construct(
        private JwtSettings $settings,
        private Clock $clock,
    ) {
    }

    public function verify(string $token): AuthenticatedPrincipal
    {
        if ('' === $token) {
            throw AuthenticationProblem::tokenInvalid();
        }

        try {
            $parsed = (new Parser(new JoseEncoder()))->parse($token);
        } catch (Throwable) {
            throw AuthenticationProblem::tokenInvalid();
        }
        if (!$parsed instanceof UnencryptedToken) {
            throw AuthenticationProblem::tokenInvalid();
        }

        $keyId = $parsed->headers()->get('kid');
        $path = is_string($keyId) ? ($this->settings->publicKeyPaths[$keyId] ?? null) : null;
        if (null === $path) {
            throw AuthenticationProblem::tokenInvalid();
        }

        $valid = (new Validator())->validate(
            $parsed,
            new SignedWith(new Sha256(), InMemory::file($path)),
            new IssuedBy($this->settings->issuer),
            new PermittedFor($this->settings->audience),
        );
        if (!$valid) {
            throw AuthenticationProblem::tokenInvalid();
        }

        $now = $this->clock->now();
        if ($parsed->isExpired($now)) {
            throw AuthenticationProblem::tokenExpired();
        }
        if (!$parsed->hasBeenIssuedBefore($now) || !$parsed->isMinimumTimeBefore($now)) {
            throw AuthenticationProblem::tokenInvalid();
        }

        $claims = $parsed->claims();
        $subject = $claims->get('sub');
        $roles = $claims->get('roles');
        $amr = $claims->get('amr', '');
        $method = is_string($amr) ? AuthenticationMethod::tryFrom($amr) : null;
        if (!is_string($subject) || '' === $subject || !is_array($roles) || null === $method) {
            throw AuthenticationProblem::tokenInvalid();
        }

        return new AuthenticatedPrincipal($subject, array_values(array_filter($roles, '\is_string')), $method);
    }
}
