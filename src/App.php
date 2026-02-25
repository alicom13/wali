<?php

declare(strict_types=1);

namespace Wali\Web;

/**
 * Class App
 *
 * Kernel utama aplikasi.
 * Bertanggung jawab menjalankan siklus HTTP
 * dan menangani error yang tidak tertangkap.
 */
class App
{
    /**
     * Instance Router untuk menangani proses routing.
     */
    protected Router $router;

    /**
     * Menentukan apakah aplikasi berjalan dalam mode debug.
     */
    protected bool $debug;

    /**
     * Konstruktor App.
     *
     * @param Router $router Instance router yang akan digunakan.
     * @param bool   $debug  Aktifkan mode debug (menampilkan detail error).
     */
    public function __construct(Router $router, bool $debug = false)
    {
        $this->router = $router;
        $this->debug  = $debug;
    }

    /**
     * Menjalankan aplikasi.
     *
     * Memulai proses dispatch route dan menangani
     * exception yang tidak tertangani.
     */
    public function run(): void
    {
        try {
            $this->router->dispatch();
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Menangani exception yang tidak tertangkap.
     *
     * Mengirim status HTTP 500 dan menampilkan detail error
     * jika mode debug aktif.
     *
     * @param \Throwable $e
     */
    protected function handleException(\Throwable $e): void
    {
        http_response_code(500);

        if ($this->debug) {
            echo '<pre>' . (string) $e . '</pre>';
            return;
        }

        echo 'Internal Server Error';
    }
}
