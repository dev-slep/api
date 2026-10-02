<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Domain\Policy;

use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\PlainPassword;
use App\Authentication\Domain\Policy\PasswordPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PasswordPolicy::class)]
final class PasswordPolicyTest extends TestCase
{
    public function testALongEnoughUncommonPasswordIsAcceptable(): void
    {
        (new PasswordPolicy(10))->assertAcceptable(new PlainPassword('tenletters'), new Email('ana@example.com'));

        $this->addToAssertionCount(1);
    }

    public function testOneCharacterBelowTheMinimumIsRejected(): void
    {
        try {
            (new PasswordPolicy(10))->assertAcceptable(new PlainPassword('ninechars'), new Email('ana@example.com'));
            self::fail('A short password was accepted.');
        } catch (AuthenticationProblem $problem) {
            self::assertSame('weak-password', $problem->problemSlug());
            self::assertSame(422, $problem->httpStatus());
            self::assertStringContainsString('at least 10 characters', $problem->getMessage());
        }
    }

    public function testLengthIsCountedInCharactersNotBytes(): void
    {
        (new PasswordPolicy(5))->assertAcceptable(new PlainPassword('šđčćž'), new Email('ana@example.com'));

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<int|string, array{string}>
     */
    public static function tooEasyPasswords(): iterable
    {
        yield 'the email address' => ['ana.petrovic@example.com'];
        yield 'the email address in capitals' => ['ANA.PETROVIC@EXAMPLE.COM'];
        yield 'a common password' => ['password123'];
        yield 'a common password in capitals' => ['PASSWORD123'];
        yield 'digits only' => ['1234567890'];
    }

    #[DataProvider('tooEasyPasswords')]
    public function testEasyToGuessPasswordsAreRejected(string $password): void
    {
        $this->expectException(AuthenticationProblem::class);
        $this->expectExceptionMessage('too easy to guess');

        (new PasswordPolicy(10))->assertAcceptable(new PlainPassword($password), new Email('ana.petrovic@example.com'));
    }
}
