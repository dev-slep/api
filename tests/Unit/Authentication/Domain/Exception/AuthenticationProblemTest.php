<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Domain\Exception;

use App\Authentication\Domain\Exception\AuthenticationProblem;

use function dirname;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

#[CoversClass(AuthenticationProblem::class)]
final class AuthenticationProblemTest extends TestCase
{
    /**
     * @return iterable<int|string, array{AuthenticationProblem, string, int}>
     */
    public static function problems(): iterable
    {
        yield [AuthenticationProblem::invalidCredentials(), 'invalid-credentials', 401];
        yield [AuthenticationProblem::emailNotVerified(), 'email-not-verified', 403];
        yield [AuthenticationProblem::accountBanned(), 'account-banned', 403];
        yield [AuthenticationProblem::emailAlreadyRegistered(), 'email-already-registered', 409];
        yield [AuthenticationProblem::contactBlacklisted(), 'contact-blacklisted', 403];
        yield [AuthenticationProblem::tokenExpired(), 'token-expired', 401];
        yield [AuthenticationProblem::tokenInvalid(), 'token-invalid', 401];
        yield [AuthenticationProblem::tokenRevoked(), 'token-revoked', 401];
        yield [AuthenticationProblem::verificationTokenInvalid(), 'verification-token-invalid', 422];
        yield [AuthenticationProblem::twoFactorRequired(), 'two-factor-required', 401];
        yield [AuthenticationProblem::twoFactorInvalid(), 'two-factor-invalid', 401];
        yield [AuthenticationProblem::twoFactorNotEnrolled(), 'two-factor-not-enrolled', 409];
        yield [AuthenticationProblem::twoFactorAlreadyEnrolled(), 'two-factor-already-enrolled', 409];
        yield [AuthenticationProblem::weakPassword('too weak'), 'weak-password', 422];
        yield [AuthenticationProblem::adminOnly(), 'admin-only', 403];
        yield [AuthenticationProblem::adminRegistrationForbidden(), 'admin-registration-forbidden', 403];
        yield [AuthenticationProblem::socialTokenInvalid(), 'social-token-invalid', 401];
        yield [AuthenticationProblem::socialRoleRequired(), 'social-role-required', 422];
        yield [AuthenticationProblem::identityAlreadyLinked(), 'identity-already-linked', 409];
        yield [AuthenticationProblem::adminSocialLoginForbidden(), 'admin-social-login-forbidden', 403];
    }

    #[DataProvider('problems')]
    public function testEachProblemHasItsSlugAndStatus(AuthenticationProblem $problem, string $slug, int $status): void
    {
        self::assertSame($slug, $problem->problemSlug());
        self::assertSame($status, $problem->httpStatus());
        self::assertNotSame('', $problem->getMessage());
    }

    #[DataProvider('problems')]
    public function testEachProblemHasATitleInBothLanguages(AuthenticationProblem $problem, string $slug, int $status): void
    {
        foreach (['en', 'sr_Latn'] as $locale) {
            $titles = Yaml::parseFile(dirname(__DIR__, 5)."/translations/problems+intl-icu.$locale.yaml");

            self::assertIsArray($titles);
            self::assertArrayHasKey($slug, $titles, "Missing $locale title for $slug");
            self::assertNotSame('', $titles[$slug]);
            self::assertSame($status, $problem->httpStatus());
        }
    }

    public function testTheWeakPasswordReasonIsTheMessage(): void
    {
        self::assertSame('too weak', AuthenticationProblem::weakPassword('too weak')->getMessage());
    }
}
