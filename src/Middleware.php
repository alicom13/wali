<?php

declare(strict_types=1);

namespace Wali\Web;

/**
 * Kontrak dasar middleware.
 *
 * before() dijalankan sebelum controller.
 * Return false untuk menghentikan eksekusi.
 *
 * after() dijalankan setelah controller.
 */
interface Middleware
{
    public function before(array $params = []): bool;

    public function after(): void;
}
