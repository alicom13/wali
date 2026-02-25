<?php

declare(strict_types=1);

namespace Wali\Web;

class Router
{
    protected array $routes = [];

    protected array $MwBefore = [];
    protected array $MwAfter  = [];

    /*
    |--------------------------------------------------------------------------
    | Register Global Middleware
    |--------------------------------------------------------------------------
    */

    public function before(array $middleware): void
    {
        $this->MwBefore = $middleware;
    }

    public function after(array $middleware): void
    {
        $this->MwAfter = $middleware;
    }

    /*
    |--------------------------------------------------------------------------
    | HTTP Methods
    |--------------------------------------------------------------------------
    */

    public function get(string $uri, callable|array $action, array $mw = []): void
    {
        $this->add('GET', $uri, $action, $mw);
    }

    public function post(string $uri, callable|array $action, array $mw = []): void
    {
        $this->add('POST', $uri, $action, $mw);
    }

    protected function add(string $method, string $uri, callable|array $action, array $mw): void
    {
        $this->routes[$method][$this->normalize($uri)] = [
            'action' => $action,
            'mw' => $mw
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Dispatch
    |--------------------------------------------------------------------------
    */

    public function dispatch(): void
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $uri    = $this->currentUri();

            foreach ($this->routes[$method] ?? [] as $route => $data) {

                if (preg_match($this->toRegex($route), $uri, $matches)) {

                    array_shift($matches);

                    // global before middleware
                    if (!$this->runMiddleware($this->MwBefore, $matches)) {
                        return;
                    }

                    // route middleware before
                    if (!$this->runMiddleware($data['mw'], $matches)) {
                        return;
                    }

                    // execute controller
                    $this->execute($data['action'], $matches);

                    // route after middleware
                    $this->runAfter($data['mw']);

                    // global after middleware
                    $this->runAfter($this->MwAfter);

                    return;
                }
            }

            $this->abort(404, 'Not Found');

        } catch (\Throwable $e) {
            $this->abort(500, 'Internal Server Error');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Execute Controller
    |--------------------------------------------------------------------------
    */

    protected function execute(callable|array $action, array $params): void
    {
        if (is_callable($action)) {
            $result = call_user_func_array($action, $params);
        } else {

            [$controller, $method] = $action;

            if (!class_exists($controller)) {
                $this->abort(500, "Controller {$controller} tidak ditemukan");
            }

            $instance = new $controller;

            if (!method_exists($instance, $method)) {
                $this->abort(500, "Method {$method} tidak ditemukan");
            }

            $result = call_user_func_array([$instance, $method], $params);
        }

        // ===== Response lifecycle =====

        if ($result instanceof Response) {
            return;
        }

        if (is_array($result)) {
            Response::json($result);
            return;
        }

        if (is_string($result)) {
            echo $result;
            return;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Middleware Runner
    |--------------------------------------------------------------------------
    */

    protected function runMiddleware(array $middleware, array $params): bool
    {
        foreach ($middleware as $mw) {
            $instance = new $mw;
            if (!$instance->before($params)) {
                return false;
            }
        }
        return true;
    }

    protected function runAfter(array $middleware): void
    {
        foreach ($middleware as $mw) {
            $instance = new $mw;
            $instance->after();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

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

    protected function abort(int $code, string $message): void
    {
        http_response_code($code);
        echo $message;
        exit;
    }
}
