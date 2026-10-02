<?php

declare(strict_types=1);

namespace App\Tests\Support\Authentication;

use function is_string;

use Symfony\Component\HttpFoundation\Response;

/**
 * Typed readers for the JSON responses of the last request, so tests do not pass `mixed` around.
 * Used by the test cases that have a `$this->client`.
 */
trait ReadsJsonResponses
{
    /**
     * @return array<string, mixed>
     */
    protected function json(?Response $response = null): array
    {
        $decoded = json_decode((string) ($response ?? $this->client->getResponse())->getContent(), true);
        self::assertIsArray($decoded, 'The response is not a JSON object.');

        return $this->stringKeyed($decoded);
    }

    /**
     * @param array<mixed, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function stringKeyed(array $values): array
    {
        $keyed = [];
        foreach ($values as $key => $value) {
            $keyed[(string) $key] = $value;
        }

        return $keyed;
    }

    protected function jsonString(string $field, ?Response $response = null): string
    {
        $value = $this->json($response)[$field] ?? null;
        self::assertIsString($value, "The response field \"$field\" is not a string.");

        return $value;
    }

    protected function jsonBool(string $field, ?Response $response = null): bool
    {
        $value = $this->json($response)[$field] ?? null;
        self::assertIsBool($value, "The response field \"$field\" is not a boolean.");

        return $value;
    }

    /**
     * @return list<mixed>
     */
    protected function jsonList(string $field, ?Response $response = null): array
    {
        $value = $this->json($response)[$field] ?? null;
        self::assertIsArray($value, "The response field \"$field\" is not a list.");

        return array_values($value);
    }

    /**
     * @return list<string>
     */
    protected function jsonStrings(string $field, ?Response $response = null): array
    {
        return array_map(static fn (mixed $value): string => is_string($value) ? $value : '', $this->jsonList($field, $response));
    }

    /**
     * The `type` of the problem the last response describes.
     */
    protected function problemType(?Response $response = null): string
    {
        return $this->jsonString('type', $response);
    }

    /**
     * The sorted, distinct names of the fields a validation problem blames.
     *
     * @return list<string>
     */
    protected function errorFields(?Response $response = null): array
    {
        $fields = [];
        foreach ($this->jsonList('errors', $response) as $error) {
            self::assertIsArray($error);
            self::assertIsString($error['field'] ?? null);
            $fields[$error['field']] = true;
        }
        $names = array_keys($fields);
        sort($names);

        return $names;
    }

    /**
     * The claims of a JWT, read without verifying it.
     *
     * @return array<string, mixed>
     */
    protected function claims(string $jwt): array
    {
        $parts = explode('.', $jwt);
        self::assertCount(3, $parts, 'Not a JWT.');
        $decoded = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true);
        self::assertIsArray($decoded);

        return $this->stringKeyed($decoded);
    }

    /**
     * @return array<string, string>
     */
    protected function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }
}
