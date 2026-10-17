<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class TecnicoRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** Técnicos con su carga activa (tickets ABIERTO o EN CURSO). */
    public function listarConCarga(bool $soloActivos): array
    {
        $sql = "SELECT te.id, te.nombre, te.email, te.activo, COUNT(t.id) AS activos
                  FROM tecnicos te
             LEFT JOIN tickets t ON t.tecnico_id = te.id AND t.estado <> 'CERRADO'"
             . ($soloActivos ? ' WHERE te.activo = 1' : '')
             . ' GROUP BY te.id, te.nombre, te.email, te.activo ORDER BY te.nombre';
        return $this->pdo->query($sql)->fetchAll();
    }

    /** Tickets abiertos o en curso, para mostrar junto a cada técnico. */
    public function ticketsActivos(): array
    {
        return $this->pdo->query(
            "SELECT t.tecnico_id, t.numero, t.estado, t.prioridad
               FROM tickets t
              WHERE t.estado <> 'CERRADO'
              ORDER BY t.creado_en, t.id"
        )->fetchAll();
    }

    /** Busca un técnico; con $bloquear toma un bloqueo de fila (FOR UPDATE) dentro de la transacción actual. */
    public function buscar(int $id, bool $bloquear = false): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT id, nombre, activo FROM tecnicos WHERE id = :id' . ($bloquear ? ' FOR UPDATE' : '')
        );
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    public function contarActivos(int $id): int
    {
        $st = $this->pdo->prepare("SELECT COUNT(*) FROM tickets WHERE tecnico_id = :id AND estado <> 'CERRADO'");
        $st->execute(['id' => $id]);
        return (int) $st->fetchColumn();
    }
}
