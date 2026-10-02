<?php
declare(strict_types=1);

namespace Natcodev\Modules;

/**
 * A self-contained feature module. Implementations declare the request paths
 * they own (matches()) and render them (handle()).
 */
interface ModuleInterface
{
    public function name(): string;

    public function matches(string $uri): bool;

    public function handle(string $uri): void;
}
