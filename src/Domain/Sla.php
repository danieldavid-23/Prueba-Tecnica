<?php
declare(strict_types=1);

namespace App\Domain;

use DateInterval;
use DateTimeImmutable;

/** Cálculo del SLA. Lógica pura (sin BD) para poder probarla de forma aislada. */
final class Sla
{
    public const VENCIDO    = 'VENCIDO';
    public const POR_VENCER = 'POR_VENCER';
    public const EN_TIEMPO  = 'EN_TIEMPO';

    /** Fracción del tiempo total por debajo de la cual el ticket está "por vencer". */
    public const UMBRAL_POR_VENCER = 0.25;

    public static function fechaLimite(DateTimeImmutable $creado, Prioridad $prioridad): DateTimeImmutable
    {
        return $creado->add(new DateInterval('PT' . $prioridad->horasSla() . 'H'));
    }

    /** null para tickets cerrados (el indicador solo aplica a los no cerrados). */
    public static function indicador(
        DateTimeImmutable $creado,
        DateTimeImmutable $limite,
        Estado $estado,
        DateTimeImmutable $ahora
    ): ?string {
        if (!$estado->esActivo()) {
            return null;
        }
        if ($ahora >= $limite) {
            return self::VENCIDO;
        }
        $total    = $limite->getTimestamp() - $creado->getTimestamp();
        $restante = $limite->getTimestamp() - $ahora->getTimestamp();
        return $restante < $total * self::UMBRAL_POR_VENCER ? self::POR_VENCER : self::EN_TIEMPO;
    }
}
