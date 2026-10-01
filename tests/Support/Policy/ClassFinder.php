<?php

declare(strict_types=1);

namespace App\Tests\Support\Policy;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function strlen;

/**
 * Finds the classes, interfaces and enums declared under a PSR-4 directory without booting the kernel.
 */
final readonly class ClassFinder
{
    public function __construct(
        private string $directory,
        private string $namespace,
    ) {
    }

    /**
     * @return array<class-string, string> class name => path relative to the directory (e.g. "Billing/Domain/Model/Invoice.php")
     */
    public function find(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $classes = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen(rtrim($this->directory, '/')) + 1);
            $name = $this->namespace.str_replace('/', '\\', substr($relative, 0, -4));

            if (class_exists($name) || interface_exists($name) || enum_exists($name)) {
                $classes[$name] = $relative;
            }
        }

        ksort($classes);

        return $classes;
    }
}
