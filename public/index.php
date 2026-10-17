<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Kernel;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Servidor embebido de PHP: dejar que sirva los archivos estáticos (css/js).
if (PHP_SAPI === 'cli-server' && $path !== '/' && is_file(__DIR__ . $path) && !str_ends_with($path, '.php')) {
    return false;
}

if ($path === '/api' || str_starts_with($path, '/api/')) {
    Kernel::api();
} else {
    Kernel::spa();
}
