<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Social;

/**
 * How to verify the ID tokens of one provider (config/services/authentication.yaml).
 */
final readonly class SocialProviderSettings
{
    /**
     * @param list<string> $issuers   accepted `iss` values
     * @param list<string> $audiences accepted `aud` values (the app's client ids)
     */
    public function __construct(
        public string $jwksUrl,
        public array $issuers,
        public array $audiences,
    ) {
    }
}
