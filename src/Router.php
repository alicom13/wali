<?php

declare(strict_types=1);

namespace Wali\Web;

class Router
{
    /**
     * Format:
     * [
     *   'GET' => [
     *       '/users/{id}' => callable|array
     *   ]
     * ]
     */
    protected array $routes = [];

    /*
    |--------------------------------------------------------------------------
    | HTTP Methods
    |--------------------------------------------------------------------------
    */

    public function get(string $uri, callable|array $action): void
    {
        $this->add('GET', $uri, $action);
    }

    public function post(string $uri, callable|array $action): void
    {
        $this->add('POST', $uri, $action);
    }

    public function put(string $uri, callable|array $action): void
    {
        $this->add('PUT', $uri, $action);
    }

    public function patch(string $uri, callable|array $action): void
    {
        $this->add('PATCH', $uri, $action);
    }

    public function delete(string $uri, callable|array $action): void
    {
        $this->add('DELETE', $uri, $action);
    }

    public function options(string $uri, callable|array $action): void
    {
        $this->add('OPTIONS', $uri, $action);
    }

    /**
     * Daftarkan route untuk banyak method sekaligus
     */
    public function match(array $methods, string $uri, callable|array $action): void
    {
        foreach ($methods as $method) {
            $this->add(strtoupper($method), $uri, $action);
        }
    }

    /**
     * Daftarkan untuk semua method
     */
    public function any(string $uri, callable|array $action): void
    {
        $this->match(
            ['GET','POST','PUT','PATCH','DELETE','OPTIONS'],
            $uri,
            $action
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Core
    |--------------------------------------------------------------------------
    */

    protected function add(string $method, string $uri, callable|array $action): void
    {
        $this->routes[$method][$this->normalize($uri)] = $action;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = $this->currentUri();

        // Cek method tersedia untuk URI
        $allowed = [];

        foreach ($this->routes as $httpMethod => $routes) {
            foreach ($routes as $route => $action) {
                if (preg_match($this->toRegex($route), $uri)) {
                    $allowed[] = $httpMethod;
                }
            }
        }

        if (!isset($this->routes[$method])) {
            $this->abort(405, 'Method Not Allowed', $allowed);
        }

        foreach ($this->routes[$method] as $route => $action) {

            if (preg_match($this->toRegex($route), $uri, $matches)) {

                array_shift($matches);
                $this->execute($action, $matches);
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
    | Helpers
    |--------------------------------------------------------------------------
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

    protected function toRegex(string $route): string
    {
        $pattern = preg_replace('#\{[^}]+\}#', '([^/]+)', $route);
        return "#^{$pattern}$#";
    }

    protected function normalize(string $uri): string
    {
        $uri = '/' . trim($uri, '/');
        return $uri === '/' ? '/' : rtrim($uri, '/');
    }

    protected function currentUri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        return $this->normalize($uri ?: '/');
    }

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
