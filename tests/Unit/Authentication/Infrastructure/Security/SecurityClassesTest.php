<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Infrastructure\Security;

use App\Authentication\Application\Port\AccessTokenVerifier;
use App\Authentication\Application\Port\AuthenticatedPrincipal;
use App\Authentication\Application\Port\AuthenticationMethod;
use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Infrastructure\Security\AuthenticatedUser;
use App\Authentication\Infrastructure\Security\JwtAuthenticator;
use App\Authentication\Infrastructure\Security\JwtUserProvider;
use App\Authentication\Infrastructure\Security\ProblemEntryPoint;
use App\Authentication\Infrastructure\Security\SecurityCurrentUser;
use App\Authentication\Infrastructure\Security\TokenAuthenticationException;
use App\SharedKernel\Infrastructure\Http\ProblemDetailsFactory;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(AuthenticatedUser::class)]
#[CoversClass(JwtAuthenticator::class)]
#[CoversClass(JwtUserProvider::class)]
#[CoversClass(ProblemEntryPoint::class)]
#[CoversClass(SecurityCurrentUser::class)]
#[CoversClass(TokenAuthenticationException::class)]
final class SecurityClassesTest extends TestCase
{
    private const string ACCOUNT = '01900000-0000-7000-8000-0000000000aa';

    /**
     * @param list<string> $roles
     */
    private function principal(AuthenticationMethod $method = AuthenticationMethod::Password, array $roles = ['ROLE_DRIVER']): AuthenticatedPrincipal
    {
        return new AuthenticatedPrincipal(self::ACCOUNT, $roles, $method);
    }

    /**
     * @return array<string, mixed>
     */
    private function problemOf(Response $response): array
    {
        $body = json_decode((string) $response->getContent(), true);
        self::assertIsArray($body);

        $keyed = [];
        foreach ($body as $key => $value) {
            $keyed[(string) $key] = $value;
        }

        return $keyed;
    }

    private function problems(): ProblemDetailsFactory
    {
        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new ProblemDetailsFactory($translator, 'https://slep.example/problems/', false);
    }

    private function authenticator(?AuthenticatedPrincipal $principal = null, ?AuthenticationProblem $failure = null): JwtAuthenticator
    {
        $verifier = self::createStub(AccessTokenVerifier::class);
        if (null !== $failure) {
            $verifier->method('verify')->willThrowException($failure);
        } else {
            $verifier->method('verify')->willReturn($principal ?? $this->principal());
        }

        return new JwtAuthenticator($verifier, $this->problems());
    }

    private function request(?string $authorization): Request
    {
        $request = Request::create('/api/v1/anything');
        if (null !== $authorization) {
            $request->headers->set('Authorization', $authorization);
        }

        return $request;
    }

    public function testTheUserIsBuiltFromThePrincipal(): void
    {
        $user = new AuthenticatedUser($this->principal(AuthenticationMethod::PasswordAndOtp, ['ROLE_ADMIN']));

        self::assertSame(self::ACCOUNT, $user->getUserIdentifier());
        self::assertSame(['ROLE_ADMIN'], $user->getRoles());
        $user->eraseCredentials();
    }

    public function testTheProviderNeverLoadsOrRefreshesUsers(): void
    {
        $provider = new JwtUserProvider();

        self::assertTrue($provider->supportsClass(AuthenticatedUser::class));
        self::assertFalse($provider->supportsClass(InMemoryUser::class));
        try {
            $provider->loadUserByIdentifier(self::ACCOUNT);
            self::fail('A user was loaded.');
        } catch (UserNotFoundException) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(UnsupportedUserException::class);
        $provider->refreshUser(new AuthenticatedUser($this->principal()));
    }

    /**
     * @return iterable<int|string, array{?string, bool}>
     */
    public static function headers(): iterable
    {
        yield 'no header' => [null, false];
        yield 'bearer' => ['Bearer abc.def.ghi', true];
        yield 'lowercase scheme' => ['bearer abc.def.ghi', true];
        yield 'basic' => ['Basic dXNlcjpwYXNz', true];
        yield 'empty bearer' => ['Bearer', true];
    }

    #[DataProvider('headers')]
    public function testOnlyRequestsWithAnAuthorizationHeaderAreAuthenticated(?string $header, bool $supported): void
    {
        self::assertSame($supported, $this->authenticator()->supports($this->request($header)));
    }

    public function testAValidTokenYieldsAPassportWithTheUser(): void
    {
        $passport = $this->authenticator($this->principal(AuthenticationMethod::Social, ['ROLE_TOWER']))->authenticate($this->request('Bearer abc.def.ghi'));

        $badge = $passport->getBadge(UserBadge::class);
        self::assertInstanceOf(UserBadge::class, $badge);
        self::assertSame(self::ACCOUNT, $badge->getUserIdentifier());
        $user = $badge->getUser();
        self::assertInstanceOf(AuthenticatedUser::class, $user);
        self::assertSame(['ROLE_TOWER'], $user->getRoles());
    }

    #[DataProvider('malformedHeaders')]
    public function testAMalformedHeaderIsRejected(string $header): void
    {
        try {
            $this->authenticator()->authenticate($this->request($header));
            self::fail('A malformed header was accepted.');
        } catch (TokenAuthenticationException $failure) {
            self::assertSame('token-invalid', $failure->problem->problemSlug());
        }
    }

    /**
     * @return iterable<int|string, array{string}>
     */
    public static function malformedHeaders(): iterable
    {
        yield 'basic auth' => ['Basic dXNlcjpwYXNz'];
        yield 'bearer without token' => ['Bearer'];
        yield 'two tokens' => ['Bearer a b'];
        yield 'bare token' => ['abc.def.ghi'];
    }

    public function testAVerificationFailureKeepsItsSpecificProblem(): void
    {
        try {
            $this->authenticator(null, AuthenticationProblem::tokenExpired())->authenticate($this->request('Bearer abc.def.ghi'));
            self::fail('An expired token was accepted.');
        } catch (TokenAuthenticationException $failure) {
            self::assertSame('token-expired', $failure->problem->problemSlug());
            self::assertSame($failure->problem, $failure->getPrevious());
        }
    }

    public function testASuccessfulAuthenticationDoesNotInterceptTheResponse(): void
    {
        self::assertNull($this->authenticator()->onAuthenticationSuccess($this->request('Bearer x'), new NullToken(), 'api'));
    }

    public function testAFailedAuthenticationAnswersWithTheProblemAndABearerChallenge(): void
    {
        $response = $this->authenticator()->onAuthenticationFailure($this->request('Bearer x'), new TokenAuthenticationException(AuthenticationProblem::tokenExpired()));

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame('Bearer', $response->headers->get('WWW-Authenticate'));
        $body = $this->problemOf($response);
        self::assertSame('https://slep.example/problems/token-expired', $body['type']);
        self::assertSame('/api/v1/anything', $body['instance']);
    }

    public function testAnyOtherAuthenticationFailureIsAGeneric401(): void
    {
        $response = $this->authenticator()->onAuthenticationFailure($this->request('Bearer x'), new AuthenticationException());

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('https://slep.example/problems/unauthorized', $this->problemOf($response)['type']);
    }

    public function testAnonymousRequestsToProtectedRoutesGetA401WithAChallenge(): void
    {
        $response = (new ProblemEntryPoint($this->problems()))->start($this->request(null));

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Bearer', $response->headers->get('WWW-Authenticate'));
        self::assertSame('https://slep.example/problems/unauthorized', $this->problemOf($response)['type']);
    }

    public function testTheEntryPointKeepsTheFailureItWasGiven(): void
    {
        $response = (new ProblemEntryPoint($this->problems()))->start($this->request(null), new AuthenticationException());

        self::assertSame(401, $response->getStatusCode());
    }

    public function testMissingRightsAreA403Problem(): void
    {
        $response = (new ProblemEntryPoint($this->problems()))->handle($this->request('Bearer x'), new AccessDeniedException());

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame('https://slep.example/problems/forbidden', $this->problemOf($response)['type']);
    }

    private function currentUser(?AuthenticatedPrincipal $principal): SecurityCurrentUser
    {
        $storage = new TokenStorage();
        if (null !== $principal) {
            $user = new AuthenticatedUser($principal);
            $storage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));
        }

        return new SecurityCurrentUser($storage);
    }

    public function testTheCurrentUserComesFromTheSecurityToken(): void
    {
        $current = $this->currentUser($this->principal(AuthenticationMethod::PasswordAndOtp, ['ROLE_ADMIN']));

        self::assertTrue($current->isAuthenticated());
        self::assertSame(self::ACCOUNT, $current->id()->toString());
        self::assertSame(['ROLE_ADMIN'], $current->roles());
        self::assertTrue($current->isTwoFactorVerified());
    }

    public function testAPendingTokenIsAuthenticatedButNotTwoFactorVerified(): void
    {
        $current = $this->currentUser($this->principal(AuthenticationMethod::PendingTwoFactor, []));

        self::assertTrue($current->isAuthenticated());
        self::assertSame([], $current->roles());
        self::assertFalse($current->isTwoFactorVerified());
    }

    public function testNobodyIsAuthenticatedWithoutAToken(): void
    {
        $current = $this->currentUser(null);

        self::assertFalse($current->isAuthenticated());
        self::assertSame([], $current->roles());
        self::assertFalse($current->isTwoFactorVerified());
        $this->expectException(LogicException::class);
        $current->id();
    }

    public function testAForeignUserObjectIsNotAnAuthenticatedUser(): void
    {
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken(new InMemoryUser('x', null, ['ROLE_ADMIN']), 'main', ['ROLE_ADMIN']));

        self::assertFalse((new SecurityCurrentUser($storage))->isAuthenticated());
    }
}
