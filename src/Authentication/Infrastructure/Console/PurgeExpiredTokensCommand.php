<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Console;

use App\Authentication\Application\Command\PurgeExpiredTokens;
use App\SharedKernel\Application\CommandBus;

use function is_int;
use function sprintf;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(name: 'app:auth:purge-expired-tokens', description: 'Delete refresh, verification and reset tokens that expired long ago')]
final class PurgeExpiredTokensCommand extends Command
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly LockFactory $lockFactory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lock = $this->lockFactory->createLock('auth_purge_expired_tokens', 300);
        if (!$lock->acquire()) {
            $output->writeln('Another purge is already running; nothing to do.');

            return Command::SUCCESS;
        }

        try {
            $deleted = $this->commandBus->dispatch(new PurgeExpiredTokens());
        } finally {
            $lock->release();
        }

        $output->writeln(sprintf('Deleted %d expired token(s).', is_int($deleted) ? $deleted : 0));

        return Command::SUCCESS;
    }
}
