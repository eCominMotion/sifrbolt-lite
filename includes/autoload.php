<?php

declare(strict_types=1);

namespace SifrBolt\Lite;

if (! \defined('ABSPATH')) {
    exit;
}

spl_autoload_register(static function (string $class): void {
    $prefix = __NAMESPACE__ . '\\';
    if (str_starts_with($class, $prefix) === false) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $relative_path = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
    $path = __DIR__ . DIRECTORY_SEPARATOR . $relative_path . '.php';

    if (file_exists($path)) {
        require_once $path;
    }
});
