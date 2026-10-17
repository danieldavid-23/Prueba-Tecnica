<?php
declare(strict_types=1);

namespace App\Service;

use App\Domain\Reglas;
use App\Repository\TecnicoRepository;

final class TecnicoService
{
    public function __construct(private readonly TecnicoRepository $tecnicos)
    {
    }

    /** Técnicos con carga actual y la lista de sus tickets abiertos o en curso. */
    public function listar(bool $incluirInactivos): array
    {
        $porTecnico = [];
        foreach ($this->tecnicos->ticketsActivos() as $t) {
            $porTecnico[(int) $t['tecnico_id']][] = [
                'numero'    => $t['numero'],
                'estado'    => $t['estado'],
                'prioridad' => $t['prioridad'],
            ];
        }
        return array_map(static function (array $t) use ($porTecnico): array {
            $activos = (int) $t['activos'];
            return [
                'id'              => (int) $t['id'],
                'nombre'          => $t['nombre'],
                'activo'          => (bool) $t['activo'],
                'tickets_activos' => $activos,
                'maximo'          => Reglas::CARGA_MAXIMA_TECNICO,
                'disponible'      => (bool) $t['activo'] && $activos < Reglas::CARGA_MAXIMA_TECNICO,
                'tickets'         => $porTecnico[(int) $t['id']] ?? [],
            ];
        }, $this->tecnicos->listarConCarga(!$incluirInactivos));
    }
}
