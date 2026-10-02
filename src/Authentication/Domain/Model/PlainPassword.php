<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Model;

use App\Authentication\Domain\Exception\InvalidValue;

use function mb_strlen;

use SensitiveParameter;

/**
 * A password as typed by the user. It can't be printed, serialised or dumped by accident;
 * the only way to read it is {@see self::reveal()}, used by the password hasher.
 * Strength rules live in the PasswordPolicy, because a login attempt with a short password must fail as
 * "wrong credentials", not as a validation error.
 */
final readonly class PlainPassword
{
    public const int MAX_LENGTH = 128;

    private string $value;

    public function __construct(#[SensitiveParameter] string $value)
    {
        if ('' === trim($value)) {
            throw InvalidValue::because('The password must not be empty.');
        }
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::because('The password is too long.');
        }

        $this->value = $value;
    }

    public function reveal(): string
    {
        return $this->value;
    }

    public function length(): int
    {
        return mb_strlen($this->value);
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

    /**
     * @return array<never>
     */
    public function __serialize(): array
    {
        throw InvalidValue::because('A password cannot be serialised.');
    }
}
