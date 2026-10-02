<?php

declare(strict_types=1);

namespace App\Tests\Integration\Authentication;

use App\Authentication\Infrastructure\RateLimit\DbalRateLimiterStorage;
use App\Tests\Support\Authentication\AuthenticationIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\RateLimiter\Policy\SlidingWindow;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[CoversClass(DbalRateLimiterStorage::class)]
final class RateLimiterStorageAndSchemaTest extends AuthenticationIntegrationTestCase
{
    private function storage(): DbalRateLimiterStorage
    {
        return new DbalRateLimiterStorage($this->connection());
    }

    public function testTheStateOfALimiterSurvivesInPostgres(): void
    {
        $storage = $this->storage();
        $factory = new RateLimiterFactory(['id' => 'persisted', 'policy' => 'sliding_window', 'limit' => 2, 'interval' => '1 minute'], $storage);

        self::assertTrue($factory->create('client')->consume()->isAccepted());
        self::assertTrue($factory->create('client')->consume()->isAccepted());
        // A new limiter object reads the shared state, as another process would
        $third = (new RateLimiterFactory(['id' => 'persisted', 'policy' => 'sliding_window', 'limit' => 2, 'interval' => '1 minute'], $this->storage()))->create('client')->consume();

        self::assertFalse($third->isAccepted());
        self::assertGreaterThan(0, $third->getRetryAfter()->getTimestamp() - time());
        self::assertSame(1, $this->countRows('authentication.rate_limit'));
    }

    public function testStateThatExpiredIsNotReturned(): void
    {
        $storage = $this->storage();
        $storage->save(new SlidingWindow('gone', 60));
        $this->connection()->executeStatement("UPDATE authentication.rate_limit SET expires_at = NOW() - INTERVAL '1 second' WHERE id = 'gone'");

        self::assertNull($storage->fetch('gone'));
    }

    public function testStateThatStillLivesIsReturnedAndCanBeOverwrittenAndDeleted(): void
    {
        $storage = $this->storage();
        $storage->save(new SlidingWindow('live', 60));
        $storage->save(new SlidingWindow('live', 120));

        self::assertSame(1, $this->countRows('authentication.rate_limit', "id = 'live'"));
        self::assertInstanceOf(SlidingWindow::class, $storage->fetch('live'));

        $storage->delete('live');
        self::assertNull($storage->fetch('live'));
    }

    public function testTheMappingFilesAreValid(): void
    {
        $application = new Application($this->client->getKernel());
        $application->setAutoExit(false);
        $output = new BufferedOutput();

        $mapping = $application->run(new ArrayInput(['command' => 'doctrine:schema:validate', '--skip-sync' => true]), $output);

        self::assertSame(0, $mapping, $output->fetch());
    }

    public function testTheSharedUniqueConstraintsExist(): void
    {
        $indexes = $this->connection()->fetchFirstColumn("SELECT indexname FROM pg_indexes WHERE schemaname = 'authentication'");

        foreach (['uniq_696ae541e7927c74', 'uniq_f7b68fd1d1b862b8', 'uniq_c1f5879f9b6b5fba', 'uniq_one_time_token_hash'] as $index) {
            self::assertContains($index, $indexes);
        }
    }
}
