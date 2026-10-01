<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Http;

use function in_array;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets the request locale from `Accept-Language`, limited to the enabled locales (Serbian Latin by default, English).
 */
final readonly class LocaleListener
{
    /**
     * @param list<string> $enabledLocales
     */
    public function __construct(
        private array $enabledLocales,
        private string $defaultLocale,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !ApiPath::matches($event->getRequest()->getPathInfo())) {
            return;
        }

        $event->getRequest()->setLocale($this->resolve($event->getRequest()));
    }

    public function resolve(Request $request): string
    {
        foreach ($request->getLanguages() as $language) {
            if (in_array($language, $this->enabledLocales, true)) {
                return $language;
            }

            // "sr_Latn_RS" -> "sr_Latn", "sr" -> "sr_Latn", "en_GB" -> "en"
            foreach ($this->enabledLocales as $enabled) {
                if (str_starts_with($language, $enabled.'_') || str_starts_with($enabled, $language.'_')) {
                    return $enabled;
                }
            }
        }

        return $this->defaultLocale;
    }
}
