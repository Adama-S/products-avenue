<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * A fresh, empty directory per test, deleted afterwards: tests never share cache files.
 */
final readonly class TemporaryDirectory
{
    public string $path;

    public function __construct()
    {
        $this->path = sys_get_temp_dir() . '/products-avenue-' . bin2hex(random_bytes(8));
    }

    public function remove(): void
    {
        if (!is_dir($this->path)) {
            return;
        }

        foreach (glob($this->path . '/{,.}[!.]*', GLOB_BRACE) ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->path);
    }
}
