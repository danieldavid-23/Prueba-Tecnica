<?php
declare(strict_types=1);

namespace App\Domain;

/** Estados del ticket. Flujo permitido: ABIERTO -> EN CURSO <-> PENDIENTE -> CERRADO (sin reapertura). */
enum Estado: string
{
    case Abierto   = 'ABIERTO';
    case EnCurso   = 'EN CURSO';
    case Pendiente = 'PENDIENTE';
    case Cerrado   = 'CERRADO';

    /** Estados a los que puede transicionar el ticket desde su estado actual. */
    public function transicionesValidas(): array
    {
        return match ($this) {
            self::Abierto   => [self::EnCurso],
            self::EnCurso   => [self::Pendiente, self::Cerrado],
            self::Pendiente => [self::EnCurso, self::Cerrado],
            self::Cerrado   => [],
        };
    }

    public function puedePasarA(self $nuevo): bool
    {
        return in_array($nuevo, $this->transicionesValidas(), true);
    }

    public function esActivo(): bool
    {
        return $this !== self::Cerrado;
    }

    /** Acepta "en curso", "EN_CURSO", " EN  CURSO " => Estado::EnCurso; null si no es válido. */
    public static function desdeTexto(mixed $texto): ?self
    {
        if (!is_string($texto)) {
            return null;
        }
        $normalizado = strtoupper(trim((string) preg_replace('/[\s_]+/', ' ', $texto)));
        return self::tryFrom($normalizado);
    }
}
