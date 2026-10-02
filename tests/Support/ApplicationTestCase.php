<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\SharedKernel\Application\Transaction;
use App\SharedKernel\Domain\Clock;
use App\SharedKernel\Domain\IdGenerator;
use App\Tests\Support\Fake\FrozenClock;
use App\Tests\Support\Fake\NullTransaction;
use App\Tests\Support\Fake\SequentialIdGenerator;

use function is_string;

use const JSON_THROW_ON_ERROR;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Base class for application tests: the real kernel with every outside service replaced.
 *
 * Messenger and Lock run in memory and DATABASE_URL points at an unreachable host, so any
 * accidental database use fails loudly. Tests swap repositories and ports for in-memory
 * implementations and fakes in {@see self::replaceServices()}.
 */
abstract class ApplicationTestCase extends WebTestCase
{
    private const ENVIRONMENT_OVERRIDES = [
        'MESSENGER_TRANSPORT_DSN' => 'in-memory://',
        'LOCK_DSN' => 'in-memory',
        'DATABASE_URL' => 'postgresql://unreachable:unreachable@database.invalid:5432/unreachable?serverVersion=18&charset=utf8',
    ];

    /** @var array<string, string|null> */
    private array $previousEnvironment = [];

    protected KernelBrowser $client;

    /** The clock in the container: tests move time with it. */
    protected FrozenClock $frozenClock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->overrideEnvironment();

        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->frozenClock = new FrozenClock();
        static::getContainer()->set(Clock::class, $this->frozenClock);
        static::getContainer()->set(IdGenerator::class, new SequentialIdGenerator());
        static::getContainer()->set(Transaction::class, new NullTransaction());
        $this->replaceServices(static::getContainer());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->restoreEnvironment();
    }

    /**
     * The clock is frozen, ids are sequential and transactions are no-ops by default. Swap repositories for `InMemory*` implementations and ports for fakes
     * with `$container->set(Interface::class, new Fake())`.
     */
    protected function replaceServices(ContainerInterface $container): void
    {
    }

    /**
     * @param array<mixed>          $body
     * @param array<string, string> $headers
     */
    protected function jsonRequest(string $method, string $uri, array $body = [], array $headers = []): Response
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $this->client->request(
            $method,
            $uri,
            server: $server,
            content: [] === $body ? null : json_encode($body, JSON_THROW_ON_ERROR),
        );

        return $this->client->getResponse();
    }

    /**
     * Messages sent to the (in-memory) async transport, i.e. the integration events published so far.
     *
     * @return list<object>
     */
    protected function dispatchedEvents(): array
    {
        $transportId = 'messenger.transport.async';
        $transport = static::getContainer()->get($transportId);
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return array_values(array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), $transport->getSent()));
    }

    private function overrideEnvironment(): void
    {
        foreach (self::ENVIRONMENT_OVERRIDES as $name => $value) {
            $previous = $_SERVER[$name] ?? $_ENV[$name] ?? null;
            $this->previousEnvironment[$name] = is_string($previous) ? $previous : null;

            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    private function restoreEnvironment(): void
    {
        foreach ($this->previousEnvironment as $name => $value) {
            if (null === $value) {
                putenv($name);
                unset($_ENV[$name], $_SERVER[$name]);
            } else {
                putenv("$name=$value");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
        $this->previousEnvironment = [];
    }
}
