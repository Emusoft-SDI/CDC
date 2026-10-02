<?php
declare(strict_types=1);

/**
 * Minimal PSR-4-style autoloader for the Natcodev\ namespace.
 * No Composer required; maps Natcodev\Foo\Bar to app/Foo/Bar.php.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Natcodev\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
