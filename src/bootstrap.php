<?php
declare(strict_types=1);

/**
 * Arranque: autoloader PSR-4 mínimo (namespace App\ => src/) y manejo seguro de errores.
 * Nunca se muestran errores de PHP al cliente; se escriben al log.
 */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
