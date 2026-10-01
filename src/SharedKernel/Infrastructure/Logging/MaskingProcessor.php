<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Logging;

use function in_array;
use function is_array;
use function is_string;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;

/**
 * Replaces the values of secret-looking keys (passwords, tokens, authorization headers, card data, …)
 * in the log context, at any depth, so secrets never reach the logs.
 */
#[AsMonologProcessor]
final readonly class MaskingProcessor
{
    public const string MASK = '***';

    /** Keys containing any of these are secret. */
    private const array SECRET_KEY_PARTS = [
        'password', 'passwd', 'secret', 'token', 'authorization', 'cookie',
        'apikey', 'api_key', 'api-key', 'cardnumber', 'card_number', 'card-number',
    ];

    /** Short names that are only secret as a whole key (so "company" is not masked by "pan"). */
    private const array SECRET_KEYS = ['pan', 'cvv', 'cvc', 'iban', 'pin'];

    public function __invoke(LogRecord $record): LogRecord
    {
        /** @var array<string, mixed> $context */
        $context = $this->mask($record->context);

        return $record->with(context: $context);
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function mask(array $data): array
    {
        $masked = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSecret($key)) {
                $masked[$key] = self::MASK;
            } elseif (is_array($value)) {
                $masked[$key] = $this->mask($value);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }

    private function isSecret(string $key): bool
    {
        $normalised = strtolower($key);
        if (in_array($normalised, self::SECRET_KEYS, true)) {
            return true;
        }

        foreach (self::SECRET_KEY_PARTS as $part) {
            if (str_contains($normalised, $part)) {
                return true;
            }
        }

        return false;
    }
}
