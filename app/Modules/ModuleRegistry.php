<?php
declare(strict_types=1);

namespace Natcodev\Modules;

final class ModuleRegistry
{
    /** @var list<ModuleInterface> */
    private static array $modules = [];

    public static function register(ModuleInterface $module): void
    {
        self::$modules[] = $module;
    }

    /** @return list<ModuleInterface> */
    public static function all(): array
    {
        return self::$modules;
    }

    /**
     * Hand the request to the first module that owns it.
     *
     * @return bool true when a module handled the request
     */
    public static function dispatch(string $uri): bool
    {
        foreach (self::$modules as $module) {
            if ($module->matches($uri)) {
                $module->handle($uri);
                return true;
            }
        }

        return false;
    }
}
