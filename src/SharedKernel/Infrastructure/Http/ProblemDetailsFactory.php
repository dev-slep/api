<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Http;

use App\SharedKernel\Domain\DomainException;
use App\SharedKernel\Domain\ProblemType;

use function sprintf;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Turns any exception into an RFC 9457 `application/problem+json` response.
 */
final readonly class ProblemDetailsFactory
{
    public const string CONTENT_TYPE = 'application/problem+json';

    public function __construct(
        private TranslatorInterface $translator,
        private string $typeBaseUri,
        private bool $debug,
    ) {
    }

    public function create(Throwable $exception, string $instance, ?string $locale = null): JsonResponse
    {
        [$status, $slug, $detail, $errors] = $this->describe($exception);

        $body = [
            'type' => rtrim($this->typeBaseUri, '/').'/'.$slug,
            'title' => $this->translator->trans($slug, [], 'problems', $locale),
            'status' => $status,
        ];
        if (null !== $detail) {
            $body['detail'] = $detail;
        }
        $body['instance'] = $instance;
        if (null !== $errors) {
            $body['errors'] = $errors;
        }

        $headers = ['Content-Type' => self::CONTENT_TYPE];
        if ($exception instanceof HttpExceptionInterface) {
            $headers += $exception->getHeaders();
            $headers['Content-Type'] = self::CONTENT_TYPE;
        }

        return new JsonResponse($body, $status, $headers);
    }

    /**
     * @return array{0: int, 1: string, 2: string|null, 3: list<array{field: string, message: string}>|null}
     */
    private function describe(Throwable $exception): array
    {
        if ($exception instanceof ProblemType) {
            return [$exception->httpStatus(), $exception->problemSlug(), $exception->getMessage(), null];
        }

        if ($exception instanceof DomainException) {
            return [Response::HTTP_UNPROCESSABLE_ENTITY, 'domain-error', $exception->getMessage(), null];
        }

        $validation = $this->validationFailure($exception);
        if (null !== $validation) {
            return [Response::HTTP_UNPROCESSABLE_ENTITY, 'validation-failed', null, $this->errors($validation)];
        }

        if ($exception instanceof BadRequestHttpException && $exception->getPrevious() instanceof NotEncodableValueException) {
            return [Response::HTTP_UNPROCESSABLE_ENTITY, 'malformed-request', 'The request body is not valid JSON.', null];
        }

        if (is_a($exception, 'Symfony\Component\Security\Core\Exception\AccessDeniedException')) {
            return [Response::HTTP_FORBIDDEN, 'forbidden', null, null];
        }
        if (is_a($exception, 'Symfony\Component\Security\Core\Exception\AuthenticationException')) {
            return [Response::HTTP_UNAUTHORIZED, 'unauthorized', null, null];
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            return [$status, $this->httpSlug($status), '' !== $exception->getMessage() ? $exception->getMessage() : null, null];
        }

        return [
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'internal-error',
            $this->debug ? sprintf('%s: %s', $exception::class, $exception->getMessage()) : null,
            null,
        ];
    }

    private function validationFailure(Throwable $exception): ?ValidationFailedException
    {
        for ($current = $exception; null !== $current; $current = $current->getPrevious()) {
            if ($current instanceof ValidationFailedException) {
                return $current;
            }
        }

        return null;
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    private function errors(ValidationFailedException $failure): array
    {
        $errors = [];
        foreach ($failure->getViolations() as $violation) {
            $errors[] = ['field' => $violation->getPropertyPath(), 'message' => (string) $violation->getMessage()];
        }

        return $errors;
    }

    private function httpSlug(int $status): string
    {
        return match ($status) {
            Response::HTTP_UNAUTHORIZED => 'unauthorized',
            Response::HTTP_FORBIDDEN => 'forbidden',
            Response::HTTP_NOT_FOUND => 'not-found',
            Response::HTTP_METHOD_NOT_ALLOWED => 'method-not-allowed',
            Response::HTTP_UNSUPPORTED_MEDIA_TYPE => 'unsupported-media-type',
            Response::HTTP_TOO_MANY_REQUESTS => 'too-many-requests',
            default => $status >= 500 ? 'internal-error' : 'bad-request',
        };
    }
}
