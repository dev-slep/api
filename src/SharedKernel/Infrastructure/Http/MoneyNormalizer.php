<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Http;

use App\SharedKernel\Domain\Currency;
use App\SharedKernel\Domain\Money;

use function assert;
use function is_array;
use function is_int;
use function is_string;

use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Throwable;

/**
 * Money on the wire: `{ "amount": <minor units>, "currency": "RSD" }`.
 */
final readonly class MoneyNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * @return array{amount: int, currency: string}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        assert($data instanceof Money);

        return ['amount' => $data->amount, 'currency' => $data->currency->code];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Money;
    }

    /**
     * @return ($type is class-string<object> ? object : mixed)
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $path = isset($context['deserialization_path']) && is_string($context['deserialization_path']) ? $context['deserialization_path'] : null;

        if (!is_array($data) || !is_int($data['amount'] ?? null) || !is_string($data['currency'] ?? null)) {
            throw NotNormalizableValueException::createForUnexpectedDataType('Money must be {"amount": int, "currency": string}.', $data, ['array'], $path, true);
        }

        try {
            return new Money($data['amount'], new Currency($data['currency']));
        } catch (Throwable $invalid) {
            throw NotNormalizableValueException::createForUnexpectedDataType($invalid->getMessage(), $data, ['array'], $path, true, 0, $invalid);
        }
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return Money::class === $type;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Money::class => true];
    }
}
