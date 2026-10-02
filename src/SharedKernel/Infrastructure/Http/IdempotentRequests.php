<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Http;

use function is_array;

use Psr\Cache\CacheItemPoolInterface;

use function strlen;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;

/**
 * Makes a POST endpoint safe to retry (backend-specification §9.1): a request that carries an `Idempotency-Key`
 * header is handled once; the same key with the same request replays the first successful response
 * (marked `Idempotency-Replayed: true`) without running the handler again, and the same key with a different
 * request is refused. Without the header the handler simply runs. Failures are never stored, so a retry after an
 * error runs the handler again. Responses are kept for 24 hours.
 */
final readonly class IdempotentRequests
{
    public const string HEADER = 'Idempotency-Key';

    private const int TTL_SECONDS = 86400;
    private const int MAX_KEY_LENGTH = 255;

    public function __construct(
        private CacheItemPoolInterface $cache,
        private LockFactory $lockFactory,
    ) {
    }

    /**
     * @param callable(): Response $handler
     */
    public function run(Request $request, callable $handler): Response
    {
        $key = $request->headers->get(self::HEADER);
        if (null === $key || '' === trim($key) || strlen($key) > self::MAX_KEY_LENGTH) {
            return $handler();
        }

        $scope = hash('sha256', $request->getMethod().' '.$request->getPathInfo().' '.$key);
        $fingerprint = hash('sha256', $request->getContent());

        // Concurrent retries with the same key wait for the first one to finish
        $lock = $this->lockFactory->createLock('idempotency_'.$scope, 30);
        $lock->acquire(true);
        try {
            $item = $this->cache->getItem('idempotency_'.$scope);
            $stored = $item->isHit() ? $item->get() : null;
            if (is_array($stored)) {
                /** @var array{fingerprint: string, status: int, content: string, headers: array<string, string>} $stored */
                if ($stored['fingerprint'] !== $fingerprint) {
                    throw new IdempotencyKeyReused();
                }

                $response = new Response($stored['content'], $stored['status'], $stored['headers']);
                $response->headers->set('Idempotency-Replayed', 'true');

                return $response;
            }

            $response = $handler();
            if ($response->isSuccessful()) {
                $item->set([
                    'fingerprint' => $fingerprint,
                    'status' => $response->getStatusCode(),
                    'content' => (string) $response->getContent(),
                    'headers' => ['Content-Type' => $response->headers->get('Content-Type', 'application/json')],
                ]);
                $item->expiresAfter(self::TTL_SECONDS);
                $this->cache->save($item);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }
}
