<?php

declare(strict_types=1);

namespace App\Tests\Support\Authentication;

use App\Authentication\Application\Port\AuthEmailSender;
use App\Authentication\Application\Port\SocialIdentityVerifier;
use App\Authentication\Domain\Repository\OneTimeTokenRepository;
use App\Authentication\Domain\Repository\RefreshTokenRepository;
use App\Authentication\Domain\Repository\TwoFactorSecretRepository;
use App\Authentication\Domain\Repository\UserAccountRepository;
use App\Authentication\Infrastructure\Persistence\InMemoryOneTimeTokenRepository;
use App\Authentication\Infrastructure\Persistence\InMemoryRefreshTokenRepository;
use App\Authentication\Infrastructure\Persistence\InMemoryTwoFactorSecretRepository;
use App\Authentication\Infrastructure\Persistence\InMemoryUserAccountRepository;
use App\SharedKernel\Application\AggregateEventCollector;
use App\Tests\Support\ApplicationTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Application tests of the Authentication module: the real kernel, container, buses, security and serialisation,
 * with in-memory repositories and fakes for the mail server and the social providers.
 */
abstract class AuthenticationApplicationTestCase extends ApplicationTestCase
{
    use ReadsJsonResponses;

    /** @var list<object> every integration event published so far (the in-memory transport is reset after each request) */
    private array $publishedEvents = [];

    protected FakeAuthEmailSender $mailer;
    protected FakeSocialIdentityVerifier $social;

    protected function setUp(): void
    {
        parent::setUp();

        // One kernel for the whole test: a reboot would drop the in-memory repositories between requests
        $this->client->disableReboot();
    }

    /**
     * Requests without an `Accept-Language` header get Serbian, the application default (BrowserKit would add "en").
     */
    protected function jsonRequest(string $method, string $uri, array $body = [], array $headers = []): Response
    {
        $response = parent::jsonRequest($method, $uri, $body, $headers + ['Accept-Language' => '']);
        array_push($this->publishedEvents, ...$this->dispatchedEvents());

        return $response;
    }

    /**
     * @return list<object>
     */
    protected function allPublishedEvents(): array
    {
        return $this->publishedEvents;
    }

    protected function replaceServices(ContainerInterface $container): void
    {
        $collector = $container->get(AggregateEventCollector::class);

        $container->set(UserAccountRepository::class, new InMemoryUserAccountRepository($collector));
        $container->set(RefreshTokenRepository::class, new InMemoryRefreshTokenRepository());
        $container->set(OneTimeTokenRepository::class, new InMemoryOneTimeTokenRepository());
        $container->set(TwoFactorSecretRepository::class, new InMemoryTwoFactorSecretRepository($collector));
        $container->set(AuthEmailSender::class, $this->mailer = new FakeAuthEmailSender());
        $container->set(SocialIdentityVerifier::class, $this->social = new FakeSocialIdentityVerifier());
    }
}
