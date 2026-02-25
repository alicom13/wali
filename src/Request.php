<?php

declare(strict_types=1);

namespace Wali\Web;

/**
 * HTTP Request Handler
 *
 * Menyediakan akses mudah ke input HTTP:
 * - GET
 * - POST
 * - JSON body
 * - Headers
 * - Method
 * - URI
 */
class Request
{
    protected array $get;
    protected array $post;
    protected array $server;
    protected array $headers;
    protected ?array $json = null;

    public function __construct()
    {
        $this->get     = $_GET;
        $this->post    = $_POST;
        $this->server  = $_SERVER;
        $this->headers = $this->parseHeaders();
    }

    /*
    |--------------------------------------------------------------------------
    | Basic Info
    |--------------------------------------------------------------------------
    */

    public function method(): string
    {
        return $this->server['REQUEST_METHOD'] ?? 'GET';
    }

    public function uri(): string
    {
        return parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    }

    public function header(string $key, $default = null): mixed
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    /*
    |--------------------------------------------------------------------------
    | Input Handling
    |--------------------------------------------------------------------------
    */

    public function all(): array
    {
        return array_merge($this->get, $this->post, $this->json() ?? []);
    }

    public function input(string $key, $default = null): mixed
    {
        $data = $this->all();
        return $data[$key] ?? $default;
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /*
    |--------------------------------------------------------------------------
    | JSON Body
    |--------------------------------------------------------------------------
    */

    public function json(): ?array
    {
        if ($this->json !== null) {
            return $this->json;
        }

        $contentType = $this->header('content-type');

        if (str_contains((string)$contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $this->json = json_decode($raw, true) ?? [];
        } else {
            $this->json = [];
        }

        return $this->json;
    }

    /*
    |--------------------------------------------------------------------------
    | Files
    |--------------------------------------------------------------------------
    */

    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    /*
    |--------------------------------------------------------------------------
    | Internal
    |--------------------------------------------------------------------------
    */

    protected function parseHeaders(): array
    {
        $headers = [];

        foreach ($this->server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$header] = $value;
            }
        }

        return $headers;
    }
}
