<?php
declare(strict_types=1);

namespace App\Domain;

/** Constantes de negocio en un solo lugar. */
final class Reglas
{
    public const CARGA_MAXIMA_TECNICO = 5;   // tickets activos (ABIERTO + EN CURSO) por técnico
    public const DESCRIPCION_MIN      = 20;
    public const DESCRIPCION_MAX      = 1000;
    public const OBSERVACION_MAX      = 1000;
    public const SOLUCION_MIN         = 10;
    public const POR_PAGINA_DEFECTO   = 10;
    public const POR_PAGINA_MAXIMO    = 100;
}
