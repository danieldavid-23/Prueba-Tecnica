<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Error controlado de la API: se traduce al formato único {"error": {code, message, fields}}. */
final class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $fields = []
    ) {
        parent::__construct($message);
    }

    public static function validacion(array $fields, string $message = 'Los datos enviados no son válidos'): self
    {
        return new self(422, 'VALIDACION', $message, $fields);
    }

    public static function noEncontrado(string $message): self
    {
        return new self(404, 'NO_ENCONTRADO', $message);
    }

    public static function conflicto(string $code, string $message, array $fields = []): self
    {
        return new self(409, $code, $message, $fields);
    }

    public static function noAutorizado(string $message = 'Se requiere autenticación'): self
    {
        return new self(401, 'NO_AUTORIZADO', $message);
    }

    public static function prohibido(string $message = 'No tienes permisos para realizar esta acción'): self
    {
        return new self(403, 'ACCESO_DENEGADO', $message);
    }
}
