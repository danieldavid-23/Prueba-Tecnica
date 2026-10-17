<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Acceso a la configuración (config/config.php) con notación de puntos: Config::get('db.host'). */
final class Config
{
    private static array $data = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            throw new RuntimeException('Falta el archivo de configuración: copia config/config.example.php a config/config.php');
        }
        $data = require $file;
        if (!is_array($data)) {
            throw new RuntimeException('config/config.php debe devolver un arreglo');
        }
        self::$data = $data;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}
