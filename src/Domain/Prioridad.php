<?php
declare(strict_types=1);

namespace App\Domain;

enum Prioridad: string
{
    case Alta  = 'Alta';
    case Media = 'Media';
    case Baja  = 'Baja';

    /** Tiempo máximo de solución (SLA) en horas. */
    public function horasSla(): int
    {
        return match ($this) {
            self::Alta  => 4,
            self::Media => 24,
            self::Baja  => 72,
        };
    }

    public static function desdeTexto(mixed $texto): ?self
    {
        if (!is_string($texto)) {
            return null;
        }
        return self::tryFrom(ucfirst(strtolower(trim($texto))));
    }
}
