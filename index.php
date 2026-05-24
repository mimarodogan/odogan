<?php
declare(strict_types=1);

// Support both stock layout (public/index.php at root, app/ one level up)
// and "public/ moved to web root" hosting layout (everything in public_html/).
$root = is_file(__DIR__ . '/bootstrap.php') ? __DIR__ : dirname(__DIR__);
define('APP_ROOT', $root);
define('APP_START', microtime(true));

require APP_ROOT . '/bootstrap.php';

use App\Core\Router;
use App\Core\Request;
use App\Core\Response;

$router = new Router();
require APP_ROOT . '/app/routes.php';

try {
    $router->dispatch(Request::capture())->send();
} catch (\Throwable $e) {
    Response::error($e)->send();
}
