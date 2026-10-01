<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Health;

use App\SharedKernel\Infrastructure\Health\HealthCheck;
use App\SharedKernel\Infrastructure\Health\HealthResult;
use App\SharedKernel\Infrastructure\Http\Controller\HealthController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HealthController::class)]
final class HealthControllerTest extends TestCase
{
    public function testLiveDoesNotConsultAnyCheck(): void
    {
        $check = self::createMock(HealthCheck::class);
        $check->expects(self::never())->method('check');

        $response = (new HealthController([$check]))->live();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"status":"ok"}', $response->getContent());
    }

    public function testReadyIsOkWhenEveryCheckPasses(): void
    {
        $response = (new HealthController([$this->check('database', true), $this->check('messenger', true)]))->ready();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"status":"ok","checks":{"database":{"status":"ok"},"messenger":{"status":"ok"}}}', $response->getContent());
    }

    public function testReadyIs503AndListsTheFailedChecks(): void
    {
        $response = (new HealthController([$this->check('database', true), $this->check('messenger', false)]))->ready();

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('{"status":"fail","checks":{"database":{"status":"ok"},"messenger":{"status":"fail"}}}', $response->getContent());
    }

    public function testReadyRunsEveryCheckEvenAfterAFailure(): void
    {
        $second = self::createMock(HealthCheck::class);
        $second->method('name')->willReturn('second');
        $second->expects(self::once())->method('check')->willReturn(HealthResult::healthy());

        (new HealthController([$this->check('first', false), $second]))->ready();
    }

    public function testReadyWithoutChecksIsOk(): void
    {
        $response = (new HealthController([]))->ready();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"status":"ok","checks":{}}', $response->getContent());
    }

    private function check(string $name, bool $healthy): HealthCheck
    {
        $check = self::createStub(HealthCheck::class);
        $check->method('name')->willReturn($name);
        $check->method('check')->willReturn($healthy ? HealthResult::healthy() : HealthResult::unhealthy());

        return $check;
    }
}
