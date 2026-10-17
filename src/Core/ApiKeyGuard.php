<?php
declare(strict_types=1);

namespace App\Core;

/** Exige el encabezado X-API-Key con la clave definida en la configuración (401 si falta o es inválida). */
final class ApiKeyGuard
{
    public static function check(Request $request): void
    {
        $expected = (string) Config::get('api_key', '');
        $given    = $request->header('X-API-Key');
        if ($expected === '' || $given === null || !hash_equals($expected, $given)) {
            throw new ApiException(401, 'NO_AUTORIZADO', 'API key ausente o inválida');
        }
    }
}
