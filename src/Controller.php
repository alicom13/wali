<?php

declare(strict_types=1);

namespace Wali\Web;

/**
 * Base Controller
 *
 * Menyediakan helper dasar untuk response.
 * Controller turunan dapat menggunakan method ini
 * agar penulisan response lebih ringkas dan konsisten.
 */
abstract class Controller
{
    /**
     * Kirim response JSON.
     *
     * @param array $data   Data yang akan dikirim
     * @param int   $status HTTP status code
     */
    protected function json(array $data, int $status = 200): void
    {
        Response::json($data, $status);
    }

    /**
     * Kirim response sukses dengan format standar API.
     */
    protected function success(array $data = [], string $message = 'OK', int $status = 200): void
    {
        Response::json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * Kirim response error dengan format standar API.
     */
    protected function error(string $message = 'Error', int $status = 400): void
    {
        Response::json([
            'status'  => 'error',
            'message' => $message,
        ], $status);
    }
}
