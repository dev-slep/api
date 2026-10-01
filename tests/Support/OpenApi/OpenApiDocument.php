<?php

declare(strict_types=1);

namespace App\Tests\Support\OpenApi;

use function assert;

use InvalidArgumentException;

use function is_object;
use function is_string;

use const JSON_THROW_ON_ERROR;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

use function sprintf;

use Symfony\Component\Yaml\Yaml;

/**
 * An OpenAPI 3.1 document loaded from YAML, checked against the official OAS 3.1 schema
 * (tests/Support/OpenApi/oas-3.1-schema.json, validated with opis/json-schema, which supports JSON Schema 2020-12).
 * The schema file is the official 2022-10-07 one ("without schema validation"), with its `$dynamicRef: #meta`
 * replaced by `$ref: #/$defs/schema`, which is what that reference resolves to when the dialect is not extended.
 */
final readonly class OpenApiDocument
{
    private const string OAS_SCHEMA_ID = 'https://spec.openapis.org/oas/3.1/schema/2022-10-07';

    public function __construct(public object $spec)
    {
    }

    public static function fromYamlFile(string $file): self
    {
        $spec = Yaml::parseFile($file, Yaml::PARSE_OBJECT_FOR_MAP);
        if (!is_object($spec)) {
            throw new InvalidArgumentException(sprintf('"%s" is not an OpenAPI document.', $file));
        }

        return new self($spec);
    }

    public static function fromYaml(string $yaml): self
    {
        $spec = Yaml::parse($yaml, Yaml::PARSE_OBJECT_FOR_MAP);
        if (!is_object($spec)) {
            throw new InvalidArgumentException('The YAML is not an OpenAPI document.');
        }

        return new self($spec);
    }

    /**
     * @return list<string> the violations of the OpenAPI 3.1 structure (empty when the document is valid)
     */
    public function violations(): array
    {
        $validator = new Validator();
        $schema = file_get_contents(__DIR__.'/oas-3.1-schema.json');
        assert(false !== $schema);
        $validator->resolver()?->registerRaw($schema);

        $result = $validator->validate($this->spec, self::OAS_SCHEMA_ID);
        $error = $result->error();
        if (null === $error) {
            return [];
        }

        $flat = new ErrorFormatter()->formatFlat($error);

        $messages = [];
        foreach ($flat as $message) {
            $messages[] = is_string($message) ? $message : json_encode($message, JSON_THROW_ON_ERROR);
        }

        return $messages;
    }
}
