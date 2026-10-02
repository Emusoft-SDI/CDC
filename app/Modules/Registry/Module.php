<?php
declare(strict_types=1);

namespace Natcodev\Modules\Registry;

use Natcodev\Modules\ModuleInterface;
use Natcodev\Modules\Registry\Http\HomeController;

final class Module implements ModuleInterface
{
    public function name(): string
    {
        return 'registry';
    }

    public function matches(string $uri): bool
    {
        return (bool) preg_match('#/registry/#', $uri);
    }

    public function handle(string $uri): void
    {
        (new HomeController())();
    }
}
