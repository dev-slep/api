<?php

declare(strict_types=1);

namespace App\Audit\Application\Service;

use BackedEnum;
use DateTimeInterface;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;

use Stringable;
use UnitEnum;

/**
 * Turns a command or an integration event into a plain array of what it carries (its public properties),
 * with value objects flattened to strings, ready to be masked and stored.
 */
final readonly class PayloadExtractor
{
    private const int MAX_DEPTH = 3;

    /**
     * @return array<string, mixed>
     */
    public function extract(object $message): array
    {
        return $this->properties($message, 1);
    }

    /**
     * The id of the thing the message is about: the property called `id`, or the first one ending in `Id`.
     *
     * @param array<string, mixed> $payload
     */
    public function targetId(array $payload): ?string
    {
        foreach ($payload as $key => $value) {
            if (('id' === $key || str_ends_with($key, 'Id')) && is_string($value) && '' !== $value) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function properties(object $object, int $depth): array
    {
        $result = [];
        foreach (get_object_vars($object) as $name => $value) {
            $result[(string) $name] = $this->normalise($value, $depth);
        }

        return $result;
    }

    private function normalise(mixed $value, int $depth): mixed
    {
        return match (true) {
            null === $value, is_bool($value), is_int($value), is_float($value), is_string($value) => $value,
            is_array($value) => array_map(fn (mixed $item): mixed => $this->normalise($item, $depth), $value),
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            $value instanceof Stringable => (string) $value,
            is_object($value) && $depth < self::MAX_DEPTH => $this->properties($value, $depth + 1),
            default => is_object($value) ? $value::class : null,
        };
    }
}
