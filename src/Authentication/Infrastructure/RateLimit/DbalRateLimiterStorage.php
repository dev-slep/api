<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\RateLimit;

use Doctrine\DBAL\Connection;

use function is_string;

use Symfony\Component\RateLimiter\LimiterStateInterface;
use Symfony\Component\RateLimiter\Policy\SlidingWindow;
use Symfony\Component\RateLimiter\Policy\TokenBucket;
use Symfony\Component\RateLimiter\Policy\Window;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * Keeps the rate limiter state in Postgres (table authentication.rate_limit), so limits hold across processes and restarts.
 */
final readonly class DbalRateLimiterStorage implements StorageInterface
{
    private const array ALLOWED_STATES = [SlidingWindow::class, Window::class, TokenBucket::class];

    public function __construct(private Connection $connection)
    {
    }

    public function save(LimiterStateInterface $limiterState): void
    {
        $expiresAt = null === $limiterState->getExpirationTime() ? null : gmdate('Y-m-d H:i:sP', time() + $limiterState->getExpirationTime());

        $this->connection->executeStatement(
            'INSERT INTO authentication.rate_limit (id, state, expires_at) VALUES (:id, :state, :expires) '
            .'ON CONFLICT (id) DO UPDATE SET state = EXCLUDED.state, expires_at = EXCLUDED.expires_at',
            ['id' => $limiterState->getId(), 'state' => base64_encode(serialize($limiterState)), 'expires' => $expiresAt],
        );

        if (0 === random_int(0, 99)) {
            $this->connection->executeStatement('DELETE FROM authentication.rate_limit WHERE expires_at < NOW()');
        }
    }

    public function fetch(string $limiterStateId): ?LimiterStateInterface
    {
        $data = $this->connection->fetchOne(
            'SELECT state FROM authentication.rate_limit WHERE id = :id AND (expires_at IS NULL OR expires_at > NOW())',
            ['id' => $limiterStateId],
        );
        if (!is_string($data)) {
            return null;
        }

        $state = unserialize((string) base64_decode($data, true), ['allowed_classes' => self::ALLOWED_STATES]);

        return $state instanceof LimiterStateInterface ? $state : null;
    }

    public function delete(string $limiterStateId): void
    {
        $this->connection->executeStatement('DELETE FROM authentication.rate_limit WHERE id = :id', ['id' => $limiterStateId]);
    }
}
