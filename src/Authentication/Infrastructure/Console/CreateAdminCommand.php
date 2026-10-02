<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Console;

use App\Authentication\Application\Command\CreateAdmin;
use App\Authentication\Application\Command\Result\CreatedAdmin;
use App\SharedKernel\Application\CommandBus;
use App\SharedKernel\Domain\DomainException;

use function assert;
use function is_string;

use const STDIN;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Throwable;

#[AsCommand(name: 'slep:auth:create-admin', description: 'Create an admin account and start its two-factor enrolment')]
final class CreateAdminCommand extends Command
{
    public function __construct(private readonly CommandBus $commandBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email address of the admin')
            ->addOption('password-from-stdin', null, InputOption::VALUE_NONE, 'Read the password from standard input instead of asking for it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $password = $this->password($input, $output);
        if (null === $password) {
            $output->writeln('<error>No password given.</error>');

            return Command::INVALID;
        }

        $email = $input->getArgument('email');
        assert(is_string($email));

        try {
            $created = $this->commandBus->dispatch(new CreateAdmin($email, $password));
        } catch (DomainException $failure) {
            $output->writeln('<error>'.$failure->getMessage().'</error>');

            return Command::FAILURE;
        } catch (Throwable $failure) {
            $output->writeln('<error>The admin could not be created.</error>');

            throw $failure;
        }
        assert($created instanceof CreatedAdmin);

        $output->writeln('Admin created: '.$created->accountId);
        $output->writeln('Scan this URI with an authenticator app (shown once), then confirm with a first code on POST /api/v1/admin/auth/2fa/enrol/confirm:');
        $output->writeln($created->enrolment->provisioningUri);
        $output->writeln('Manual entry secret: '.$created->enrolment->secret);

        return Command::SUCCESS;
    }

    private function password(InputInterface $input, OutputInterface $output): ?string
    {
        if (true === $input->getOption('password-from-stdin')) {
            $stream = $input instanceof StreamableInputInterface ? $input->getStream() : null;
            $line = fgets($stream ?? STDIN);

            return false === $line ? null : rtrim($line, "\r\n");
        }

        $question = (new Question('Password: '))->setHidden(true)->setHiddenFallback(false);
        $answer = (new QuestionHelper())->ask($input, $output, $question);

        return is_string($answer) && '' !== $answer ? $answer : null;
    }
}
