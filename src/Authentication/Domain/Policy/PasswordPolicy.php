<?php

declare(strict_types=1);

namespace App\Authentication\Domain\Policy;

use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\PlainPassword;

use function in_array;
use function sprintf;

/**
 * The rules a new password has to satisfy: long enough, not the email address, not a well-known password.
 */
final readonly class PasswordPolicy
{
    private const array COMMON_PASSWORDS = [
        'password', 'password1', 'password123', '1234567890', '12345678910', 'qwertyuiop', 'qwerty123456',
        'iloveyou123', 'letmein1234', 'welcome1234', 'admin12345', 'passw0rd123', 'abcdefghij', '0123456789',
        '1111111111', '0000000000',
    ];

    public function __construct(private int $minLength = 10)
    {
    }

    /**
     * @throws AuthenticationProblem weak-password
     */
    public function assertAcceptable(PlainPassword $password, Email $email): void
    {
        if ($password->length() < $this->minLength) {
            throw AuthenticationProblem::weakPassword(sprintf('The password must be at least %d characters long.', $this->minLength));
        }

        $lower = mb_strtolower($password->reveal());
        if ($lower === $email->toString() || in_array($lower, self::COMMON_PASSWORDS, true)) {
            throw AuthenticationProblem::weakPassword('The password is too easy to guess.');
        }
    }
}
