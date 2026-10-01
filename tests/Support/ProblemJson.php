<?php

declare(strict_types=1);

namespace App\Tests\Support;

use function is_string;

use const JSON_THROW_ON_ERROR;

use PHPUnit\Framework\Assert;

/**
 * A decoded RFC 9457 problem response, typed for assertions.
 */
final readonly class ProblemJson
{
    /**
     * @param list<array{field: string, message: string}>|null $errors
     */
    public function __construct(
        public string $type,
        public string $title,
        public int $status,
        public string $instance,
        public ?string $detail,
        public ?array $errors,
    ) {
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        Assert::assertIsArray($data);
        Assert::assertIsString($data['type'] ?? null);
        Assert::assertIsString($data['title'] ?? null);
        Assert::assertIsInt($data['status'] ?? null);
        Assert::assertIsString($data['instance'] ?? null);
        $detail = $data['detail'] ?? null;
        Assert::assertTrue(null === $detail || is_string($detail));

        $errors = null;
        if (isset($data['errors'])) {
            Assert::assertIsArray($data['errors']);
            $errors = [];
            foreach ($data['errors'] as $error) {
                Assert::assertIsArray($error);
                Assert::assertIsString($error['field'] ?? null);
                Assert::assertIsString($error['message'] ?? null);
                $errors[] = ['field' => $error['field'], 'message' => $error['message']];
            }
        }

        return new self($data['type'], $data['title'], $data['status'], $data['instance'], $detail, $errors);
    }

    public function slug(): string
    {
        return basename($this->type);
    }

    /**
     * @return array<string, string> field => message
     */
    public function errorsByField(): array
    {
        return array_column($this->errors ?? [], 'message', 'field');
    }
}
