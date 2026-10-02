<?php
declare(strict_types=1);

namespace Natcodev\Support;

final class View
{
    /**
     * Render a view file, exposing $data as local variables.
     *
     * @param array<string, mixed> $data
     */
    public static function render(string $viewFile, array $data = []): void
    {
        if (!is_file($viewFile)) {
            throw new \RuntimeException('View not found: ' . $viewFile);
        }

        extract($data, EXTR_SKIP);
        require $viewFile;
    }
}
