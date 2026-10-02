<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Model;

use App\Authentication\Domain\Exception\InvalidValue;

use function mb_strlen;

use SensitiveParameter;

/**
 * The raw random token handed to a user (refresh token, verification or reset link). It is never stored: only
 * its {@see TokenHash} is. A hash can't be turned back into a token.
 */
final readonly class OpaqueToken
{
    private const int MIN_LENGTH = 20;
    private const int MAX_LENGTH = 256;

    private string $value;

    public function __construct(#[SensitiveParameter] string $value)
    {
        $length = mb_strlen($value);
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH || 1 !== preg_match('/^[A-Za-z0-9_\-]+$/D', $value)) {
            throw InvalidValue::because('The token is not valid.');
        }

        $this->value = $value;
    }

    public function reveal(): string
    {
        return $this->value;
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }

    public function __toString(): string
    {
        return '***';
    }
}
