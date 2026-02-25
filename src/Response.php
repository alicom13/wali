<?php

declare(strict_types=1);

namespace Wali\Web;

/**
 * Class Response
 *
 * Mengelola output HTTP.
 */
class Response
{
    protected int $statusCode = 200;
    protected array $headers = [];
    protected string $body = '';
    protected bool $sent = false;

    /*
    |--------------------------------------------------------------------------
    | Core Setter
    |--------------------------------------------------------------------------
    */

    /**
     * Mengatur HTTP status code.
     */
    public function setStatus(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Menambahkan header response.
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Mengatur isi body response.
     */
    public function setBody(string $content): self
    {
        $this->body = $content;
        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Content Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Response JSON.
     */
    public function json(array $data): self
    {
        return $this->setHeader('Content-Type', 'application/json')
                    ->setBody(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Response HTML.
     */
    public function html(string $html): self
    {
        return $this->setHeader('Content-Type', 'text/html; charset=UTF-8')
                    ->setBody($html);
    }

    /**
     * Response plain text.
     */
    public function text(string $text): self
    {
        return $this->setHeader('Content-Type', 'text/plain; charset=UTF-8')
                    ->setBody($text);
    }

    /*
    |--------------------------------------------------------------------------
    | Special Responses
    |--------------------------------------------------------------------------
    */

    /**
     * Redirect ke URL lain.
     */
    public function redirect(string $url, int $status = 302): void
    {
        if ($this->sent) return;

        http_response_code($status);
        header("Location: $url");
        $this->sent = true;
        exit;
    }

    /**
     * Download file ke browser.
     */
    public function download(string $path, ?string $filename = null): void
    {
        if (!file_exists($path)) {
            $this->setStatus(404)->text('File not found')->send();
            return;
        }

        $filename ??= basename($path);

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header('Content-Length: ' . filesize($path));

        readfile($path);
        $this->sent = true;
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Send Response
    |--------------------------------------------------------------------------
    */

    /**
     * Mengirim response ke browser.
     */
    public function send(): void
    {
        if ($this->sent) {
            return;
        }

        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        echo $this->body;

        $this->sent = true;
    }

    /**
     * Otomatis kirim response saat script selesai.
     */
    public function __destruct()
    {
        $this->send();
    }
}
