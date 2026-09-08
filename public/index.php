<?php

/**
 * Sarura Fuel Logistics - Public Entry Point (for Production Virtual Hosts / DocumentRoot = public/)
 */

require_once __DIR__ . '/../bootstrap/app.php';

use App\Core\Router;

$router = new Router();
$routes = require __DIR__ . '/../routes/web.php';
$router->loadRoutes($routes);

$response = $router->dispatch();

echo $response;
