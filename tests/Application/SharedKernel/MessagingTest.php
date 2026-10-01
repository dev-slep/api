<?php

declare(strict_types=1);

namespace App\Tests\Application\SharedKernel;

use App\SharedKernel\Application\CommandAuditor;
use App\SharedKernel\Application\CommandBus;
use App\SharedKernel\Application\EventBus;
use App\SharedKernel\Application\QueryBus;
use App\Tests\Support\ApplicationTestCase;
use App\Tests\Support\Fake\RecordingCommandAuditor;
use App\Tests\Support\Fixtures\Messaging\FailingCommand;
use App\Tests\Support\Fixtures\Messaging\FailingQuery;
use App\Tests\Support\Fixtures\Messaging\FixtureDomainException;
use App\Tests\Support\Fixtures\Messaging\FixtureIntegrationEvent;
use App\Tests\Support\Fixtures\Messaging\GreetQuery;
use App\Tests\Support\Fixtures\Messaging\RecordedMessages;
use App\Tests\Support\Fixtures\Messaging\RecordingCommand;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[CoversNothing]
final class MessagingTest extends ApplicationTestCase
{
    private RecordingCommandAuditor $auditor;

    protected function replaceServices(ContainerInterface $container): void
    {
        $this->auditor = new RecordingCommandAuditor();
        $container->set(CommandAuditor::class, $this->auditor);
    }

    public function testCommandIsHandledByItsHandler(): void
    {
        $command = new RecordingCommand('hello');

        $this->commandBus()->dispatch($command);

        self::assertSame([$command], $this->recorded()->handled);
    }

    public function testCommandFailureSurfacesAsTheOriginalDomainException(): void
    {
        $this->expectException(FixtureDomainException::class);
        $this->expectExceptionMessage('Handler failed on purpose.');

        $this->commandBus()->dispatch(new FailingCommand());
    }

    public function testCommandsAreAuditedOnSuccessAndFailure(): void
    {
        $this->commandBus()->dispatch(new RecordingCommand('ok'));
        try {
            $this->commandBus()->dispatch(new FailingCommand());
        } catch (FixtureDomainException) {
        }

        self::assertSame(['success', 'failure'], array_column($this->auditor->entries, 'outcome'));
    }

    public function testQueryReturnsTheHandlersResult(): void
    {
        self::assertSame('Hello Ana', $this->queryBus()->ask(new GreetQuery('Ana')));
    }

    public function testQueryFailureSurfacesAsTheOriginalDomainException(): void
    {
        $this->expectException(FixtureDomainException::class);

        $this->queryBus()->ask(new FailingQuery());
    }

    public function testQueriesAreNotAudited(): void
    {
        $this->queryBus()->ask(new GreetQuery('Ana'));

        self::assertSame([], $this->auditor->entries);
    }

    public function testPublishedIntegrationEventsAreRoutedToTheAsyncTransport(): void
    {
        $event = new FixtureIntegrationEvent();

        $eventBus = static::getContainer()->get(EventBus::class);
        $eventBus->publish($event);

        self::assertEquals([$event], $this->dispatchedEvents());
    }

    private function commandBus(): CommandBus
    {
        $bus = static::getContainer()->get(CommandBus::class);

        return $bus;
    }

    private function queryBus(): QueryBus
    {
        $bus = static::getContainer()->get(QueryBus::class);

        return $bus;
    }

    private function recorded(): RecordedMessages
    {
        $recorded = static::getContainer()->get(RecordedMessages::class);

        return $recorded;
    }
}
