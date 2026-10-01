<?php

declare(strict_types=1);

namespace App;

use App\SharedKernel\Application\CommandHandler;
use App\SharedKernel\Application\DomainEventMapper;
use App\SharedKernel\Application\QueryHandler;
use App\SharedKernel\Infrastructure\Health\HealthCheck;
use App\SharedKernel\Infrastructure\Scheduler\ScheduledTaskProvider;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Handlers are registered on their bus through the marker interfaces, not with `#[AsMessageHandler]`,
     * because the Application layer must not depend on vendor code.
     */
    protected function build(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(CommandHandler::class)
            ->addTag('messenger.message_handler', ['bus' => 'command.bus']);
        $container->registerForAutoconfiguration(QueryHandler::class)
            ->addTag('messenger.message_handler', ['bus' => 'query.bus']);
        $container->registerForAutoconfiguration(DomainEventMapper::class)
            ->addTag('app.domain_event_mapper');
        $container->registerForAutoconfiguration(ScheduledTaskProvider::class)
            ->addTag('app.scheduled_task');
        $container->registerForAutoconfiguration(HealthCheck::class)
            ->addTag('app.health_check');
    }

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    // @phpstan-ignore method.unused (called by MicroKernelTrait)
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
