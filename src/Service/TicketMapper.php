<?php
declare(strict_types=1);

namespace App\Service;

use App\Domain\Estado;
use App\Domain\Sla;
use DateTimeImmutable;
use DateTimeZone;

/** Convierte filas de BD al contrato JSON de la API (fechas ISO 8601 con zona horaria). */
final class TicketMapper
{
    public function __construct(private readonly DateTimeZone $tz)
    {
    }

    public function fecha(?string $timestamp): ?string
    {
        return $timestamp === null ? null : $this->fechaObj($timestamp)->format('c');
    }

    public function fechaObj(string $timestamp): DateTimeImmutable
    {
        // La BD guarda DATETIME en hora local de la aplicación (sin zona): se interpreta con esa zona.
        return new DateTimeImmutable($timestamp, $this->tz);
    }

    public function resumen(array $r, DateTimeImmutable $ahora): array
    {
        return [
            'numero'     => $r['numero'],
            'usuario'    => $r['usuario_nombre'],
            'categoria'  => $r['categoria'],
            'prioridad'  => $r['prioridad'],
            'tecnico'    => $r['tecnico'],
            'tecnico_id' => (int) $r['tecnico_id'],
            'estado'     => $r['estado'],
            'creado'     => $this->fecha($r['creado_en']),
            'vence'      => $this->fecha($r['fecha_limite']),
            'sla'        => Sla::indicador(
                $this->fechaObj($r['creado_en']),
                $this->fechaObj($r['fecha_limite']),
                Estado::from($r['estado']),
                $ahora
            ),
        ];
    }

    public function detalle(array $r, array $historial, DateTimeImmutable $ahora): array
    {
        return $this->resumen($r, $ahora) + [
            'descripcion'  => $r['descripcion'],
            'solucion'     => $r['solucion'],
            'cerrado'      => $this->fecha($r['cerrado_en']),
            'actualizado'  => $this->fecha($r['actualizado_en']),
            'categoria_id' => (int) $r['categoria_id'],
            'solicitante'  => [
                'codigo' => $r['usuario_codigo'],
                'nombre' => $r['usuario_nombre'],
                'cargo'  => $r['usuario_cargo'],
                'area'   => $r['usuario_area'],
            ],
            'historial'    => array_map(fn (array $h): array => [
                'fecha'            => $this->fecha($h['creado_en']),
                'tipo'             => $h['tipo'],
                'estado_anterior'  => $h['estado_anterior'],
                'estado_nuevo'     => $h['estado_nuevo'],
                'tecnico_anterior' => $h['tecnico_anterior'],
                'tecnico_nuevo'    => $h['tecnico_nuevo'],
                'observacion'      => $h['observacion'],
            ], $historial),
        ];
    }
}
