<?php

/**
 * Sarura Fuel Logistics - Main Entry Point
 * Supports both root directory execution (e.g. http://localhost/fuel/) and web server routing.
 */

require_once __DIR__ . '/bootstrap/app.php';

use App\Core\Router;

$router = new Router();
$routes = require __DIR__ . '/routes/web.php';
$router->loadRoutes($routes);

$response = $router->dispatch();

echo $response;
