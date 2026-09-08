<?php

namespace App\Core;

use RuntimeException;

class Router
{
    protected array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
    ];

    /**
     * Routes accessible without logging in.
     */
    protected array $publicRoutes = [
        '/login',
        '/register',
        '/currency/toggle',
        '/fleet/template',
    ];

    public function get(string $uri, callable|array $handler): void
    {
        $this->addRoute('GET', $uri, $handler);
    }

    public function post(string $uri, callable|array $handler): void
    {
        $this->addRoute('POST', $uri, $handler);
    }

    public function put(string $uri, callable|array $handler): void
    {
        $this->addRoute('PUT', $uri, $handler);
    }

    public function delete(string $uri, callable|array $handler): void
    {
        $this->addRoute('DELETE', $uri, $handler);
    }

    protected function addRoute(string $method, string $uri, callable|array $handler): void
    {
        $uri = '/' . trim($uri, '/');
        $this->routes[$method][$uri] = $handler;
    }

    public function loadRoutes(array $routes): void
    {
        foreach ($routes as $route) {
            [$method, $uri, $handler] = $route;
            $method = strtoupper($method);
            if (isset($this->routes[$method])) {
                $this->addRoute($method, $uri, $handler);
            }
        }
    }

    public function dispatch(?string $requestUri = null): string
    {
        $method = $this->resolveMethod();
        $uri = $this->resolveUri($requestUri);

        // Verify CSRF for POST/PUT/DELETE requests (exclude /logout or specify exceptions if needed)
        if (in_array($method, ['POST', 'PUT', 'DELETE'], true) && $uri !== '/logout') {
            if (!verify_csrf()) {
                http_response_code(419);
                return view('errors.404', [
                    'title' => 'Page Expired (419)',
                    'message' => 'Your session token has expired. Please refresh the page and try again.',
                ]);
            }
        }

        // Check Force Session Termination & Record Heartbeat
        if (!empty($_SESSION['is_logged_in'])) {
            if (is_current_session_terminated()) {
                $_SESSION = [];
                session_destroy();
                session_start();
                flash('login_error', 'Your session was terminated by an administrator for security purposes.');
                redirect('/login');
            }
            record_session_heartbeat();
        }

        // Maintenance Mode Gating
        if (is_maintenance_mode()) {
            $isAllowed = is_super_admin() || is_developer();
            $isAuthRoute = in_array($uri, ['/login', '/logout', '/currency/toggle']);
            if (!$isAllowed && !$isAuthRoute) {
                http_response_code(503);
                return view('errors.maintenance', [
                    'title' => 'Scheduled Maintenance — Sarura Fuel',
                    'message' => maintenance_message(),
                ]);
            }
        }

        // Enforce Authentication
        if ($this->requiresAuth($uri) && empty($_SESSION['is_logged_in'])) {
            redirect('/login');
        }

        // Exact match
        if (isset($this->routes[$method][$uri])) {
            return $this->callHandler($this->routes[$method][$uri]);
        }

        // Regex match for parameters like /trips/{id}
        foreach ($this->routes[$method] as $routeUri => $handler) {
            if (!str_contains($routeUri, '{')) {
                continue;
            }

            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $routeUri);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                $params = array_values(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
                return $this->callHandler($handler, $params);
            }
        }

        return $this->pageNotFound();
    }

    protected function resolveMethod(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $method = strtoupper($method);

        if ($method === 'POST' && isset($_POST['_method'])) {
            $spoofed = strtoupper($_POST['_method']);
            if (in_array($spoofed, ['PUT', 'DELETE', 'PATCH'], true)) {
                return $spoofed;
            }
        }

        return $method;
    }

    protected function resolveUri(?string $requestUri = null): string
    {
        if ($requestUri === null) {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        }

        // Strip query string
        $uri = parse_url($requestUri, PHP_URL_PATH) ?? '/';

        // Strip subfolder if present
        $subfolder = app_subfolder();
        if ($subfolder !== '' && str_starts_with($uri, $subfolder)) {
            $uri = substr($uri, strlen($subfolder));
        }

        // Normalize
        $uri = '/' . trim($uri, '/');
        return $uri;
    }

    protected function callHandler(callable|array $handler, array $params = []): string
    {
        if (is_callable($handler)) {
            return (string) call_user_func_array($handler, $params);
        }

        if (is_array($handler)) {
            [$class, $action] = $handler;
            if (class_exists($class)) {
                $controller = new $class();
                if (method_exists($controller, $action)) {
                    return (string) call_user_func_array([$controller, $action], $params);
                }
            }
        }

        throw new RuntimeException("Handler could not be invoked for current route.");
    }

    protected function requiresAuth(string $uri): bool
    {
        if (in_array($uri, $this->publicRoutes, true)) {
            return false;
        }

        return !str_starts_with($uri, '/login') && !str_starts_with($uri, '/currency/toggle');
    }

    protected function pageNotFound(): string
    {
        http_response_code(404);
        return view('errors.404', [
            'title' => 'Page Not Found',
            'message' => 'The page you requested could not be found.',
        ]);
    }
}
