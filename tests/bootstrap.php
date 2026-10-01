<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// The dev container sets APP_ENV=dev as a real environment variable; tests always run in `test`.
putenv('APP_ENV=test');
$_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'test';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if (true === (bool) ($_SERVER['APP_DEBUG'] ?? false)) {
    umask(0000);
}
