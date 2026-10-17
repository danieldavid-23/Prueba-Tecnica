<?php
declare(strict_types=1);

namespace App\Domain;

/** Roles para control de acceso basado en roles (RBAC). */
enum Rol: string
{
    case Admin       = 'ADMIN';
    case Tecnico     = 'TECNICO';
    case Solicitante = 'SOLICITANTE';

    public function esAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function esTecnico(): bool
    {
        return $this === self::Tecnico;
    }

    public function esSolicitante(): bool
    {
        return $this === self::Solicitante;
    }

    public static function desdeTexto(mixed $texto): ?self
    {
        if (!is_string($texto)) {
            return null;
        }
        return self::tryFrom(strtoupper(trim($texto)));
    }
}
