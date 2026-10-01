<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Logging;

use App\SharedKernel\Infrastructure\Correlation\CorrelationContext;
use App\SharedKernel\Infrastructure\Logging\CorrelationProcessor;
use App\SharedKernel\Infrastructure\Logging\MaskingProcessor;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CorrelationProcessor::class)]
#[CoversClass(MaskingProcessor::class)]
final class ProcessorsTest extends TestCase
{
    public function testCorrelationProcessorAddsBothIds(): void
    {
        $context = new CorrelationContext();
        $context->start('corr-1', 'cause-1');

        $record = new CorrelationProcessor($context)($this->record());

        self::assertSame(['correlationId' => 'corr-1', 'causationId' => 'cause-1'], $record->extra);
    }

    public function testCausationIsOmittedWhenThereIsNone(): void
    {
        $context = new CorrelationContext();
        $context->start('corr-1');

        $record = new CorrelationProcessor($context)($this->record());

        self::assertSame(['correlationId' => 'corr-1'], $record->extra);
    }

    public function testRecordIsLeftAloneWithoutContext(): void
    {
        $original = $this->record(extra: ['existing' => 1]);

        $record = new CorrelationProcessor(new CorrelationContext())($original);

        self::assertSame(['existing' => 1], $record->extra);
    }

    public function testExistingExtraIsPreserved(): void
    {
        $context = new CorrelationContext();
        $context->start('corr-1');

        $record = new CorrelationProcessor($context)($this->record(extra: ['existing' => 1]));

        self::assertSame(['existing' => 1, 'correlationId' => 'corr-1'], $record->extra);
    }

    public function testSecretKeysAreMasked(): void
    {
        $record = new MaskingProcessor()($this->record(context: [
            'password' => 'hunter2',
            'newPassword' => 'hunter3',
            'access_token' => 'abc',
            'refreshToken' => 'def',
            'Authorization' => 'Bearer xyz',
            'Cookie' => 'session=1',
            'api_key' => 'k',
            'card_number' => '4111111111111111',
            'cardNumber' => '4111111111111111',
            'cvv' => '123',
            'pan' => '4111',
            'client_secret' => 's',
            'userId' => 'u-1',
            'email' => 'ana@example.com',
        ]));

        self::assertSame([
            'password' => '***',
            'newPassword' => '***',
            'access_token' => '***',
            'refreshToken' => '***',
            'Authorization' => '***',
            'Cookie' => '***',
            'api_key' => '***',
            'card_number' => '***',
            'cardNumber' => '***',
            'cvv' => '***',
            'pan' => '***',
            'client_secret' => '***',
            'userId' => 'u-1',
            'email' => 'ana@example.com',
        ], $record->context);
    }

    public function testNestedSecretsAreMasked(): void
    {
        $record = new MaskingProcessor()($this->record(context: [
            'request' => ['headers' => ['authorization' => ['Bearer abc'], 'accept' => 'json'], 'body' => ['user' => ['password' => 'x', 'name' => 'Ana']]],
        ]));

        self::assertSame([
            'request' => ['headers' => ['authorization' => '***', 'accept' => 'json'], 'body' => ['user' => ['password' => '***', 'name' => 'Ana']]],
        ], $record->context);
    }

    public function testShortSecretNamesOnlyMatchWholeKeys(): void
    {
        $record = new MaskingProcessor()($this->record(context: ['company' => 'Acme', 'panel' => 1, 'pin' => '1234', 'expand' => true]));

        self::assertSame(['company' => 'Acme', 'panel' => 1, 'pin' => '***', 'expand' => true], $record->context);
    }

    public function testListsAndScalarsAreKept(): void
    {
        $record = new MaskingProcessor()($this->record(context: ['ids' => [1, 2, 3], 'count' => 3, 'nothing' => null, 'flag' => false]));

        self::assertSame(['ids' => [1, 2, 3], 'count' => 3, 'nothing' => null, 'flag' => false], $record->context);
    }

    public function testEmptyContextStaysEmpty(): void
    {
        self::assertSame([], new MaskingProcessor()($this->record())->context);
    }

    public function testMessageAndLevelAreUntouched(): void
    {
        $record = new MaskingProcessor()($this->record(context: ['password' => 'x']));

        self::assertSame('hello', $record->message);
        self::assertSame(Level::Info, $record->level);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     */
    private function record(array $context = [], array $extra = []): LogRecord
    {
        return new LogRecord(new DateTimeImmutable('2026-01-01T00:00:00+00:00'), 'app', Level::Info, 'hello', $context, $extra);
    }
}
