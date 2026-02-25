<?php

declare(strict_types=1);

namespace Wali\Web;

/**
 * Class Router
 *
 * Router ringan untuk menangani routing HTTP,
 * parameter dinamis, dan eksekusi middleware.
 *
 * Fitur:
 * - Routing berdasarkan HTTP method
 * - Parameter dinamis {id}
 * - Closure & Controller handler
 * - Middleware before & after
 * - 404 & 405 handling
 */
class Router
{
    /**
     * Daftar route.
     *
     * Format:
     * [
     *   'GET' => [
     *      '/users/{id}' => [
     *          'action' => callable|array,
     *          'middleware' => []
     *      ]
     *   ]
     * ]
     */
    protected array $routes = [];

    /**
     * Middleware global sebelum controller dijalankan.
     */
    protected array $MwBefore = [];

    /**
     * Middleware global setelah controller dijalankan.
     */
    protected array $MwAfter = [];

    /*
    |--------------------------------------------------------------------------
    | HTTP Methods
    |--------------------------------------------------------------------------
    */

    public function get(string $uri, callable|array $action, array $middleware = []): void
    {
        $this->add('GET', $uri, $action, $middleware);
    }

    public function post(string $uri, callable|array $action, array $middleware = []): void
    {
        $this->add('POST', $uri, $action, $middleware);
    }

    public function put(string $uri, callable|array $action, array $middleware = []): void
    {
        $this->add('PUT', $uri, $action, $middleware);
    }

    public function patch(string $uri, callable|array $action, array $middleware = []): void
    {
        $this->add('PATCH', $uri, $action, $middleware);
    }

    public function delete(string $uri, callable|array $action, array $middleware = []): void
    {
        $this->add('DELETE', $uri, $action, $middleware);
    }

    public function options(string $uri, callable|array $action, array $middleware = []): void
    {
        $this->add('OPTIONS', $uri, $action, $middleware);
    }

    /**
     * Daftarkan route untuk banyak method sekaligus.
     */
    public function match(array $methods, string $uri, callable|array $action, array $middleware = []): void
    {
        foreach ($methods as $method) {
            $this->add(strtoupper($method), $uri, $action, $middleware);
        }
    }

    /**
     * Route untuk semua method.
     */
    public function any(string $uri, callable|array $action, array $middleware = []): void
    {
        $this->match(
            ['GET','POST','PUT','PATCH','DELETE','OPTIONS'],
            $uri,
            $action,
            $middleware
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Middleware Global
    |--------------------------------------------------------------------------
    */

    /**
     * Daftarkan middleware global sebelum controller.
     */
    public function before(string $class): void
    {
        $this->MwBefore[] = $class;
    }

    /**
     * Daftarkan middleware global setelah controller.
     */
    public function after(string $class): void
    {
        $this->MwAfter[] = $class;
    }

    /*
    |--------------------------------------------------------------------------
    | Core Routing
    |--------------------------------------------------------------------------
    */

    protected function add(
        string $method,
        string $uri,
        callable|array $action,
        array $middleware = []
    ): void {
        $this->routes[$method][$this->normalize($uri)] = [
            'action' => $action,
            'middleware' => $middleware,
        ];
    }

    /**
     * Menjalankan proses routing berdasarkan request.
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = $this->currentUri();

        $allowed = [];

        // cek method yang tersedia untuk URI
        foreach ($this->routes as $httpMethod => $routes) {
            foreach ($routes as $route => $data) {
                if (preg_match($this->toRegex($route), $uri)) {
                    $allowed[] = $httpMethod;
                }
            }
        }

        if (!isset($this->routes[$method])) {
            $this->abort(405, 'Method Not Allowed', $allowed);
        }

        foreach ($this->routes[$method] as $route => $routeData) {

            if (preg_match($this->toRegex($route), $uri, $matches)) {

                array_shift($matches);

                // gabungkan middleware global & route
                $middlewares = array_merge(
                    $this->MwBefore,
                    $routeData['middleware']
                );

                // jalankan middleware before
                if (!$this->runBefore($middlewares)) {
                    return;
                }

                // jalankan controller / handler
                $this->execute($routeData['action'], $matches);

                // jalankan middleware after
                $this->runAfter($this->MwAfter);

                return;
            }
        }

        if (!empty($allowed)) {
            $this->abort(405, 'Method Not Allowed', $allowed);
        }

        $this->abort(404, 'Not Found');
    }

    /*
    |--------------------------------------------------------------------------
    | Middleware Runner
    |--------------------------------------------------------------------------
    */

    /**
     * Menjalankan middleware sebelum controller.
     * Return false jika proses harus dihentikan.
     */
    protected function runBefore(array $middlewares): bool
    {
        foreach ($middlewares as $middleware) {
            if ((new $middleware)->before() === false) {
                return false;
            }
        }
        return true;
    }

    /**
     * Menjalankan middleware setelah controller.
     */
    protected function runAfter(array $middlewares): void
    {
        foreach ($middlewares as $middleware) {
            (new $middleware)->after();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Eksekusi handler route.
     */
    protected function execute(callable|array $action, array $params): void
    {
        if (is_callable($action)) {
            call_user_func_array($action, $params);
            return;
        }

        [$controller, $method] = $action;

        if (!class_exists($controller)) {
            $this->abort(500, "Controller {$controller} tidak ditemukan");
        }

        $instance = new $controller;

        if (!method_exists($instance, $method)) {
            $this->abort(500, "Method {$method} tidak ditemukan");
        }

        call_user_func_array([$instance, $method], $params);
    }

    /**
     * Mengubah route menjadi regex.
     */
    protected function toRegex(string $route): string
    {
        $pattern = preg_replace('#\{[^}]+\}#', '([^/]+)', $route);
        return "#^{$pattern}$#";
    }

    /**
     * Normalisasi URI.
     */
    protected function normalize(string $uri): string
    {
        $uri = '/' . trim($uri, '/');
        return $uri === '/' ? '/' : rtrim($uri, '/');
    }

    /**
     * Mengambil URI request saat ini.
     */
    protected function currentUri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        return $this->normalize($uri ?: '/');
    }

    /**
     * Menghentikan request dengan status HTTP.
     */
    protected function abort(int $code, string $message, array $allowed = []): void
    {
        http_response_code($code);

        if ($code === 405 && !empty($allowed)) {
            header('Allow: ' . implode(', ', $allowed));
        }

        echo $message;
        exit;
    }
}
