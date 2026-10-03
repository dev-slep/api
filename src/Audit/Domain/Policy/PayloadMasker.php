<?php

declare(strict_types=1);

namespace App\Audit\Domain\Policy;

use function is_array;
use function is_string;

/**
 * Hides secrets before a payload is stored: the value of every key that looks like a credential is replaced.
 */
final readonly class PayloadMasker
{
    public const string MASK = '***';

    private const string SECRET_KEYS = '/password|passwd|secret|token|code|hash|key|authorization|credential/i';

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function mask(array $payload): array
    {
        /** @var array<string, mixed> $masked */
        $masked = $this->walk($payload);

        return $masked;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private function walk(array $values): array
    {
        $masked = [];
        foreach ($values as $key => $value) {
            if (is_string($key) && 1 === preg_match(self::SECRET_KEYS, $key) && null !== $value) {
                $masked[$key] = self::MASK;
            } elseif (is_array($value)) {
                $masked[$key] = $this->walk($value);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }
}
