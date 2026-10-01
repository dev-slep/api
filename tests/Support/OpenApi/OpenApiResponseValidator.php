<?php

declare(strict_types=1);

namespace App\Tests\Support\OpenApi;

use function is_object;
use function is_string;

use const JSON_THROW_ON_ERROR;

use JsonException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

use function sprintf;

/**
 * Validates a response (status, content type and JSON body) against the schema the OpenAPI document
 * declares for that operation, using JSON Schema 2020-12 semantics (OpenAPI 3.1).
 */
final readonly class OpenApiResponseValidator
{
    private const string DOCUMENT_ID = 'urn:slep:openapi';

    private Validator $validator;

    public function __construct(private OpenApiDocument $document)
    {
        $this->validator = new Validator();
        $this->validator->resolver()?->registerRaw($document->spec, self::DOCUMENT_ID);
    }

    /**
     * @return list<string> what is wrong with the response (empty when it matches the documented schema)
     */
    public function violations(string $method, string $path, int $status, ?string $contentType, string $body): array
    {
        $pathKey = $this->matchPath($path);
        if (null === $pathKey) {
            return [sprintf('Path "%s" is not documented.', $path)];
        }

        $verb = strtolower($method);
        $operation = $this->property($this->property($this->property($this->document->spec, 'paths'), $pathKey), $verb);
        if (!is_object($operation)) {
            return [sprintf('%s %s is not documented.', strtoupper($method), $pathKey)];
        }

        $responses = $this->property($operation, 'responses');
        $statusKey = null;
        foreach ([(string) $status, 'default'] as $candidate) {
            if (is_object($this->property($responses, $candidate))) {
                $statusKey = $candidate;

                break;
            }
        }
        if (null === $statusKey) {
            return [sprintf('%s %s has no documented %d response.', strtoupper($method), $pathKey, $status)];
        }

        $content = $this->property($this->property($responses, $statusKey), 'content');
        if (!is_object($content) || [] === get_object_vars($content)) {
            return '' === $body ? [] : [sprintf('The %d response of %s %s is documented without a body, but one was returned.', $status, strtoupper($method), $pathKey)];
        }

        $mediaType = strtolower(trim(explode(';', (string) $contentType)[0]));
        $media = $this->property($content, $mediaType);
        if (!is_object($media)) {
            return [sprintf('Content type "%s" is not documented for the %d response of %s %s.', $mediaType, $status, strtoupper($method), $pathKey)];
        }
        if (!is_object($this->property($media, 'schema'))) {
            return [];
        }

        try {
            $data = json_decode($body, false, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return ['The response body is not valid JSON: '.$exception->getMessage()];
        }

        $pointer = sprintf(
            '#/paths/%s/%s/responses/%s/content/%s/schema',
            $this->escape($pathKey),
            $verb,
            $this->escape($statusKey),
            $this->escape($mediaType),
        );
        $error = $this->validator->validate($data, self::DOCUMENT_ID.$pointer)->error();

        return null === $error ? [] : $this->messages((new ErrorFormatter())->formatFlat($error));
    }

    private function matchPath(string $path): ?string
    {
        $paths = $this->property($this->document->spec, 'paths');
        if (!is_object($paths)) {
            return null;
        }

        if (is_object($this->property($paths, $path))) {
            return $path;
        }

        foreach (array_keys(get_object_vars($paths)) as $template) {
            $pattern = '#^'.preg_replace('#\\\{[^/]+?\\\}#', '[^/]+', preg_quote((string) $template, '#')).'$#D';
            if (1 === preg_match($pattern, $path)) {
                return (string) $template;
            }
        }

        return null;
    }

    private function property(mixed $object, string $name): mixed
    {
        return is_object($object) ? (((array) $object)[$name] ?? null) : null;
    }

    /**
     * @param array<array-key, mixed> $errors
     *
     * @return list<string>
     */
    private function messages(array $errors): array
    {
        $messages = [];
        foreach ($errors as $error) {
            $messages[] = is_string($error) ? $error : json_encode($error, JSON_THROW_ON_ERROR);
        }

        return $messages;
    }

    private function escape(string $segment): string
    {
        // JSON pointer escaping, then URI fragment encoding (path templates contain braces)
        return rawurlencode(str_replace(['~', '/'], ['~0', '~1'], $segment));
    }
}
