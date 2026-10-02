<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Infrastructure\Persistence;

use App\Authentication\Domain\Exception\AuthenticationProblem;
use App\Authentication\Domain\Model\AccountId;
use App\Authentication\Domain\Model\AccountRole;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\Locale;
use App\Authentication\Domain\Model\OneTimeToken;
use App\Authentication\Domain\Model\OneTimeTokenId;
use App\Authentication\Domain\Model\OneTimeTokenPurpose;
use App\Authentication\Domain\Model\PasswordHash;
use App\Authentication\Domain\Model\RefreshToken;
use App\Authentication\Domain\Model\RefreshTokenId;
use App\Authentication\Domain\Model\SocialIdentity;
use App\Authentication\Domain\Model\SocialProvider;
use App\Authentication\Domain\Model\SocialSubject;
use App\Authentication\Domain\Model\TokenFamilyId;
use App\Authentication\Domain\Model\TokenHash;
use App\Authentication\Domain\Model\TwoFactorSecret;
use App\Authentication\Domain\Model\TwoFactorSecretId;
use App\Authentication\Domain\Model\UserAccount;
use App\Authentication\Infrastructure\Persistence\AuthenticationProcessedEvents;
use App\Authentication\Infrastructure\Persistence\DoctrineOneTimeTokenRepository;
use App\Authentication\Infrastructure\Persistence\DoctrineRefreshTokenRepository;
use App\Authentication\Infrastructure\Persistence\DoctrineTwoFactorSecretRepository;
use App\Authentication\Infrastructure\Persistence\DoctrineUserAccountRepository;
use App\Authentication\Infrastructure\Persistence\Entity\RefreshTokenRecord;
use App\Authentication\Infrastructure\Persistence\Entity\SocialIdentityRecord;
use App\Authentication\Infrastructure\Persistence\Entity\TwoFactorSecretRecord;
use App\Authentication\Infrastructure\Persistence\Entity\UserAccountRecord;
use App\Authentication\Infrastructure\Persistence\Mapper\OneTimeTokenMapper;
use App\Authentication\Infrastructure\Persistence\Mapper\RefreshTokenMapper;
use App\Authentication\Infrastructure\Persistence\Mapper\TwoFactorSecretMapper;
use App\Authentication\Infrastructure\Persistence\Mapper\UserAccountMapper;
use App\SharedKernel\Application\AggregateEventCollector;
use App\SharedKernel\Domain\AggregateRoot;
use ArrayObject;
use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Query;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\Query as OrmQuery;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * The Doctrine repositories' behaviour against Postgres is covered by the repository contract tests in the
 * integration suite; here the logic that lives in the classes themselves (record lookup, error translation,
 * event registration) is tested with a doubled entity manager.
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(DoctrineUserAccountRepository::class)]
#[CoversClass(DoctrineRefreshTokenRepository::class)]
#[CoversClass(DoctrineOneTimeTokenRepository::class)]
#[CoversClass(DoctrineTwoFactorSecretRepository::class)]
#[CoversClass(AuthenticationProcessedEvents::class)]
final class DoctrineRepositoriesTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-01-01T12:00:00+00:00');
    }

    private function uuid(int $n): string
    {
        return sprintf('01900000-0000-7000-8000-%012d', $n);
    }

    private function hash(string $seed): TokenHash
    {
        return new TokenHash(hash('sha256', $seed));
    }

    private function account(): UserAccount
    {
        return UserAccount::registerWithPassword(new AccountId($this->uuid(1)), new Email('ana@example.com'), new PasswordHash('h'), AccountRole::Driver, null, new Locale('en'), $this->now);
    }

    private function collector(): AggregateEventCollector&MockObject
    {
        return $this->createMock(AggregateEventCollector::class);
    }

    /**
     * @param class-string $recordClass
     */
    private function repositoryReturning(EntityManagerInterface&MockObject $em, string $recordClass, ?object $record): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($record);
        $em->expects(self::once())->method('getRepository')->with($recordClass)->willReturn($repository);
    }

    public function testFindingByEmailLooksUpTheLowercasedEmail(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with(['email' => 'ana@example.com'])->willReturn(null);
        $em->expects(self::once())->method('getRepository')->with(UserAccountRecord::class)->willReturn($repository);

        self::assertNull((new DoctrineUserAccountRepository($em, new UserAccountMapper(), $this->collector()))->findByEmail(new Email('ANA@example.com')));
    }

    public function testFindingByIdMapsTheRecord(): void
    {
        $record = (new UserAccountMapper())->apply($this->account(), new UserAccountRecord());
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('find')->with(UserAccountRecord::class, $this->uuid(1))->willReturn($record);

        $found = (new DoctrineUserAccountRepository($em, new UserAccountMapper(), $this->collector()))->findById(new AccountId($this->uuid(1)));

        self::assertSame('ana@example.com', $found?->email()->toString());
    }

    public function testFindingBySocialIdentityGoesThroughTheIdentityRecord(): void
    {
        $account = (new UserAccountMapper())->apply($this->account(), new UserAccountRecord());
        $identity = new SocialIdentityRecord();
        $identity->account = $account;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('find')->with(SocialIdentityRecord::class, ['provider' => 'google', 'subject' => 'g-1'])->willReturn($identity);
        $repository = new DoctrineUserAccountRepository($em, new UserAccountMapper(), $this->collector());

        self::assertNotNull($repository->findBySocialIdentity(new SocialIdentity(SocialProvider::Google, new SocialSubject('g-1'))));
    }

    public function testSavingAnAccountPersistsFlushesAndRegistersItForEvents(): void
    {
        $account = $this->account();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(UserAccountRecord::class));
        $em->expects(self::once())->method('flush');
        $collector = $this->collector();
        $collector->expects(self::once())->method('collect')->with($account);

        (new DoctrineUserAccountRepository($em, new UserAccountMapper(), $collector))->save($account);
    }

    public function testSavingAnExistingAccountUpdatesItsRecord(): void
    {
        $existing = new UserAccountRecord();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($existing);
        $em->expects(self::once())->method('persist')->with(self::identicalTo($existing));

        (new DoctrineUserAccountRepository($em, new UserAccountMapper(), $this->collector()))->save($this->account());

        self::assertSame('ana@example.com', $existing->email);
    }

    public function testAUniqueViolationWhileSavingBecomesAnEmailConflict(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);
        $em->method('flush')->willThrowException(new UniqueConstraintViolationException($this->createMock(DriverException::class), null));
        $collector = $this->collector();
        $collector->expects(self::never())->method('collect');

        try {
            (new DoctrineUserAccountRepository($em, new UserAccountMapper(), $collector))->save($this->account());
            self::fail('The violation was not translated.');
        } catch (AuthenticationProblem $problem) {
            self::assertSame('email-already-registered', $problem->problemSlug());
        }
    }

    private function refreshToken(): RefreshToken
    {
        return RefreshToken::issue(new RefreshTokenId($this->uuid(1)), new TokenFamilyId($this->uuid(2)), new AccountId($this->uuid(3)), $this->hash('t'), $this->now, new DateInterval('P30D'));
    }

    public function testFindingARefreshTokenLooksUpTheHash(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with(['hash' => $this->hash('t')->value()])->willReturn(null);
        $em->expects(self::once())->method('getRepository')->with(RefreshTokenRecord::class)->willReturn($repository);

        self::assertNull((new DoctrineRefreshTokenRepository($em, new RefreshTokenMapper()))->findByHash($this->hash('t')));
    }

    public function testAConcurrentRefreshBecomesAnInvalidToken(): void
    {
        $record = new RefreshTokenRecord();
        $record->version = 0;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($record);
        $em->method('flush')->willThrowException(OptimisticLockException::lockFailed($record));

        try {
            (new DoctrineRefreshTokenRepository($em, new RefreshTokenMapper()))->save($this->refreshToken());
            self::fail('The lock failure was not translated.');
        } catch (AuthenticationProblem $problem) {
            self::assertSame('token-invalid', $problem->problemSlug());
        }
    }

    public function testATokenChangedBySomeoneElseSinceItWasLoadedIsNotOverwritten(): void
    {
        $record = new RefreshTokenRecord();
        $record->version = 5;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($record);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        try {
            (new DoctrineRefreshTokenRepository($em, new RefreshTokenMapper()))->save($this->refreshToken());
            self::fail('A stale token was saved.');
        } catch (AuthenticationProblem $problem) {
            self::assertSame('token-invalid', $problem->problemSlug());
        }
    }

    public function testSavingARefreshTokenPersistsAndFlushes(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        (new DoctrineRefreshTokenRepository($em, new RefreshTokenMapper()))->save($this->refreshToken());
    }

    /**
     * @param ArrayObject<string, mixed> $parameters collects the bound query parameters
     */
    private function bulkQuery(string $expectedDqlStart, int $affected, ArrayObject $parameters): EntityManagerInterface
    {
        $query = $this->getMockBuilder(OrmQuery::class)->disableOriginalConstructor()->onlyMethods(['setParameter', 'execute'])->getMock();
        $query->method('setParameter')->willReturnCallback(static function (string $name, mixed $value) use ($parameters, $query): OrmQuery {
            $parameters[$name] = $value;

            return $query;
        });
        $query->expects(self::once())->method('execute')->willReturn($affected);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('createQuery')->with(self::callback(static fn (string $dql): bool => str_starts_with($dql, $expectedDqlStart)))->willReturn($query);

        return $em;
    }

    public function testRevokingAFamilyIsOneBulkUpdate(): void
    {
        /** @var ArrayObject<string, mixed> $parameters */
        $parameters = new ArrayObject();
        $em = $this->bulkQuery('UPDATE '.RefreshTokenRecord::class, 2, $parameters);

        (new DoctrineRefreshTokenRepository($em, new RefreshTokenMapper()))->revokeFamily(new TokenFamilyId($this->uuid(2)), $this->now);

        self::assertSame($this->uuid(2), $parameters['family']);
        self::assertSame($this->now, $parameters['now']);
    }

    public function testRevokingAnAccountIsOneBulkUpdate(): void
    {
        /** @var ArrayObject<string, mixed> $parameters */
        $parameters = new ArrayObject();
        $em = $this->bulkQuery('UPDATE '.RefreshTokenRecord::class, 2, $parameters);

        (new DoctrineRefreshTokenRepository($em, new RefreshTokenMapper()))->revokeAllForAccount(new AccountId($this->uuid(3)), $this->now);

        self::assertSame($this->uuid(3), $parameters['account']);
    }

    public function testDeletingExpiredRefreshTokensReturnsTheCount(): void
    {
        /** @var ArrayObject<string, mixed> $parameters */
        $parameters = new ArrayObject();
        $em = $this->bulkQuery('DELETE FROM '.RefreshTokenRecord::class, 4, $parameters);

        $deleted = (new DoctrineRefreshTokenRepository($em, new RefreshTokenMapper()))->deleteExpiredBefore($this->now);

        self::assertSame(4, $deleted);
        self::assertSame($this->now, $parameters['moment']);
    }

    private function oneTimeToken(): OneTimeToken
    {
        return OneTimeToken::issue(new OneTimeTokenId($this->uuid(1)), new AccountId($this->uuid(2)), OneTimeTokenPurpose::PasswordReset, $this->hash('o'), $this->now, new DateInterval('PT1H'));
    }

    public function testFindingAOneTimeTokenLooksUpPurposeAndHash(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with(['purpose' => 'PASSWORD_RESET', 'hash' => $this->hash('o')->value()])->willReturn(null);
        $em->method('getRepository')->willReturn($repository);

        self::assertNull((new DoctrineOneTimeTokenRepository($em, new OneTimeTokenMapper()))->findByHash(OneTimeTokenPurpose::PasswordReset, $this->hash('o')));
    }

    public function testSavingAOneTimeTokenPersistsAndFlushes(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        (new DoctrineOneTimeTokenRepository($em, new OneTimeTokenMapper()))->save($this->oneTimeToken());
    }

    public function testInvalidatingOneTimeTokensIsOneBulkUpdateForAccountAndPurpose(): void
    {
        /** @var ArrayObject<string, mixed> $parameters */
        $parameters = new ArrayObject();
        $em = $this->bulkQuery('UPDATE '.\App\Authentication\Infrastructure\Persistence\Entity\OneTimeTokenRecord::class, 1, $parameters);

        (new DoctrineOneTimeTokenRepository($em, new OneTimeTokenMapper()))->invalidateAllFor(new AccountId($this->uuid(2)), OneTimeTokenPurpose::EmailVerification, $this->now);

        self::assertSame($this->uuid(2), $parameters['account']);
        self::assertSame('EMAIL_VERIFICATION', $parameters['purpose']);
    }

    public function testDeletingExpiredOneTimeTokensReturnsTheCount(): void
    {
        $em = $this->bulkQuery('DELETE FROM '.\App\Authentication\Infrastructure\Persistence\Entity\OneTimeTokenRecord::class, 3, new ArrayObject());

        self::assertSame(3, (new DoctrineOneTimeTokenRepository($em, new OneTimeTokenMapper()))->deleteExpiredBefore($this->now));
    }

    private function secret(): TwoFactorSecret
    {
        return TwoFactorSecret::enrol(new TwoFactorSecretId($this->uuid(1)), new AccountId($this->uuid(2)), 'enc');
    }

    public function testFindingASecretLooksUpTheAccount(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->repositoryReturning($em, TwoFactorSecretRecord::class, null);

        self::assertNull((new DoctrineTwoFactorSecretRepository($em, new TwoFactorSecretMapper(), $this->collector()))->findByAccount(new AccountId($this->uuid(2))));
    }

    public function testSavingASecretFlushesAndRegistersItForEvents(): void
    {
        $secret = $this->secret();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');
        $collector = $this->collector();
        $collector->expects(self::once())->method('collect')->with(self::isInstanceOf(AggregateRoot::class));

        (new DoctrineTwoFactorSecretRepository($em, new TwoFactorSecretMapper(), $collector))->save($secret);
    }

    public function testDeletingASecretRemovesItsRecordAndFlushesAtOnce(): void
    {
        $record = new TwoFactorSecretRecord();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($record);
        $em->expects(self::once())->method('remove')->with($record);
        $em->expects(self::once())->method('flush');

        (new DoctrineTwoFactorSecretRepository($em, new TwoFactorSecretMapper(), $this->collector()))->delete($this->secret());
    }

    public function testDeletingAMissingSecretDoesNotFlush(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);
        $em->expects(self::never())->method('remove');
        $em->expects(self::never())->method('flush');

        (new DoctrineTwoFactorSecretRepository($em, new TwoFactorSecretMapper(), $this->collector()))->delete($this->secret());
    }

    public function testTheProcessedEventsTableIsTheModulesOwn(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')->with(self::stringContains('authentication.processed_event'))->willReturn(false);

        self::assertFalse((new AuthenticationProcessedEvents($connection))->wasProcessed('sub', 'event'));
    }

    public function testMarkingAnEventProcessedWritesToTheModulesTable(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('executeStatement')->with(self::stringContains('authentication.processed_event'), ['subscriber' => 'sub', 'eventId' => 'event']);

        (new AuthenticationProcessedEvents($connection))->markProcessed('sub', 'event');
    }
}
