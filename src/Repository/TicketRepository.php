<?php
declare(strict_types=1);

namespace App\Repository;

use DateTimeImmutable;
use PDO;

/** Acceso a datos de tickets e historial. Todo con sentencias preparadas. */
final class TicketRepository
{
    private const SELECT = "
        SELECT t.id, t.numero, t.descripcion, t.prioridad, t.estado, t.solucion,
               t.creado_en, t.fecha_limite, t.actualizado_en, t.cerrado_en,
               u.codigo AS usuario_codigo, u.nombre AS usuario_nombre,
               u.cargo AS usuario_cargo, u.area AS usuario_area,
               c.id AS categoria_id, c.nombre AS categoria,
               te.id AS tecnico_id, te.nombre AS tecnico
          FROM tickets t
          JOIN usuarios   u  ON u.id  = t.usuario_id
          JOIN categorias c  ON c.id  = t.categoria_id
          JOIN tecnicos   te ON te.id = t.tecnico_id";

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** Formato de fecha de la BD (DATETIME local). */
    public const FORMATO_FECHA = 'Y-m-d H:i:s';

    // ---- Transacciones (las coordina el servicio) ------------------------
    public function begin(): void
    {
        // READ COMMITTED: cada lectura ve lo último confirmado, de modo que, tras bloquear la fila
        // del técnico, el conteo de su carga incluye lo que otras transacciones ya confirmaron.
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    // ---- Consultas -------------------------------------------------------
    /**
     * @param array{estado?:string,prioridad?:string,tecnico_id?:int,q?:string} $f
     * @return array{0: array, 1: int} [filas, total]
     */
    public function buscar(array $f, int $limite, int $offset): array
    {
        [$where, $params] = $this->filtros($f);

        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM tickets t JOIN usuarios u ON u.id = t.usuario_id $where"
        );
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $st = $this->pdo->prepare(
            self::SELECT . " $where ORDER BY t.creado_en DESC, t.id DESC LIMIT :limite OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->bindValue('limite', $limite, PDO::PARAM_INT);
        $st->bindValue('offset', $offset, PDO::PARAM_INT);
        $st->execute();

        return [$st->fetchAll(), $total];
    }

    private function filtros(array $f): array
    {
        $cond   = [];
        $params = [];
        if (isset($f['estado'])) {
            $cond[] = 't.estado = :estado';
            $params['estado'] = $f['estado'];
        }
        if (isset($f['prioridad'])) {
            $cond[] = 't.prioridad = :prioridad';
            $params['prioridad'] = $f['prioridad'];
        }
        if (isset($f['tecnico_id'])) {
            $cond[] = 't.tecnico_id = :tecnico_id';
            $params['tecnico_id'] = (int) $f['tecnico_id'];
        }
        if (isset($f['usuario_id'])) {
            $cond[] = 't.usuario_id = :usuario_id';
            $params['usuario_id'] = (int) $f['usuario_id'];
        }
        if (isset($f['q']) && $f['q'] !== '') {
            // En MariaDB/MySQL el carácter de escape de LIKE es la barra invertida por defecto
            // (y la intercalación utf8mb4_unicode_ci ya ignora mayúsculas y acentos).
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $cond[] = "(t.numero LIKE :q1 OR u.nombre LIKE :q2)";
            $params['q1'] = $like;
            $params['q2'] = $like;
        }
        return [$cond ? 'WHERE ' . implode(' AND ', $cond) : '', $params];
    }

    public function porNumero(string $numero, bool $bloquear = false): ?array
    {
        if ($bloquear) {
            // Bloquea solo la fila del ticket (la consulta con JOIN bloquearía también otras tablas).
            $lock = $this->pdo->prepare('SELECT id FROM tickets WHERE numero = :numero FOR UPDATE');
            $lock->execute(['numero' => $numero]);
            if ($lock->fetchColumn() === false) {
                return null;
            }
        }
        $st = $this->pdo->prepare(self::SELECT . ' WHERE t.numero = :numero');
        $st->execute(['numero' => $numero]);
        return $st->fetch() ?: null;
    }

    public function historial(int $ticketId): array
    {
        $st = $this->pdo->prepare(
            'SELECT h.id, h.tipo, h.estado_anterior, h.estado_nuevo, h.observacion, h.creado_en,
                    ta.nombre AS tecnico_anterior, tn.nombre AS tecnico_nuevo
               FROM ticket_historial h
          LEFT JOIN tecnicos ta ON ta.id = h.tecnico_anterior_id
          LEFT JOIN tecnicos tn ON tn.id = h.tecnico_nuevo_id
              WHERE h.ticket_id = :id
           ORDER BY h.creado_en, h.id'
        );
        $st->execute(['id' => $ticketId]);
        return $st->fetchAll();
    }

    // ---- Escritura -------------------------------------------------------
    /**
     * Siguiente consecutivo del año, seguro ante concurrencia. Debe llamarse dentro de una transacción:
     *  1) se asegura que exista la fila del año (no-op si ya existe),
     *  2) SELECT ... FOR UPDATE bloquea esa fila hasta el COMMIT (otra transacción espera),
     *  3) se incrementa. Dos transacciones simultáneas nunca obtienen el mismo número, y si la
     *     transacción falla el número no se consume (sin huecos).
     */
    public function siguienteConsecutivo(int $anio): int
    {
        $this->pdo->prepare('INSERT INTO consecutivos (anio, ultimo) VALUES (:anio, 0) ON DUPLICATE KEY UPDATE anio = anio')
            ->execute(['anio' => $anio]);

        $st = $this->pdo->prepare('SELECT ultimo FROM consecutivos WHERE anio = :anio FOR UPDATE');
        $st->execute(['anio' => $anio]);
        $siguiente = (int) $st->fetchColumn() + 1;

        $this->pdo->prepare('UPDATE consecutivos SET ultimo = :ultimo WHERE anio = :anio')
            ->execute(['ultimo' => $siguiente, 'anio' => $anio]);
        return $siguiente;
    }

    public function crear(array $d): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO tickets (numero, usuario_id, categoria_id, tecnico_id, descripcion, prioridad,
                                  estado, creado_en, fecha_limite, actualizado_en)
             VALUES (:numero, :usuario_id, :categoria_id, :tecnico_id, :descripcion, :prioridad,
                     :estado, :creado, :limite, :creado2)'
        );
        $st->execute([
            'numero'       => $d['numero'],
            'usuario_id'   => $d['usuario_id'],
            'categoria_id' => $d['categoria_id'],
            'tecnico_id'   => $d['tecnico_id'],
            'descripcion'  => $d['descripcion'],
            'prioridad'    => $d['prioridad'],
            'estado'       => $d['estado'],
            'creado'       => $d['creado'],
            'limite'       => $d['limite'],
            'creado2'      => $d['creado'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function actualizarEstado(int $id, string $estado, ?string $solucion, DateTimeImmutable $ahora): void
    {
        $cerrado = $estado === 'CERRADO';
        $st = $this->pdo->prepare(
            'UPDATE tickets
                SET estado = :estado,
                    solucion = COALESCE(:solucion, solucion),
                    cerrado_en = :cerrado_en,
                    actualizado_en = :ahora
              WHERE id = :id'
        );
        $st->execute([
            'estado'     => $estado,
            'solucion'   => $solucion,
            'cerrado_en' => $cerrado ? $ahora->format(self::FORMATO_FECHA) : null,
            'ahora'      => $ahora->format(self::FORMATO_FECHA),
            'id'         => $id,
        ]);
    }

    public function actualizarTecnico(int $id, int $tecnicoId, DateTimeImmutable $ahora): void
    {
        $st = $this->pdo->prepare(
            'UPDATE tickets SET tecnico_id = :tecnico, actualizado_en = :ahora WHERE id = :id'
        );
        $st->execute(['tecnico' => $tecnicoId, 'ahora' => $ahora->format(self::FORMATO_FECHA), 'id' => $id]);
    }

    public function agregarHistorial(
        int $ticketId,
        string $tipo,
        ?string $estadoAnterior,
        string $estadoNuevo,
        ?int $tecnicoAnterior,
        ?int $tecnicoNuevo,
        ?string $observacion,
        DateTimeImmutable $cuando
    ): void {
        $st = $this->pdo->prepare(
            'INSERT INTO ticket_historial
                (ticket_id, tipo, estado_anterior, estado_nuevo, tecnico_anterior_id, tecnico_nuevo_id, observacion, creado_en)
             VALUES (:ticket, :tipo, :ant, :nuevo, :tec_ant, :tec_nuevo, :obs, :cuando)'
        );
        $st->execute([
            'ticket'    => $ticketId,
            'tipo'      => $tipo,
            'ant'       => $estadoAnterior,
            'nuevo'     => $estadoNuevo,
            'tec_ant'   => $tecnicoAnterior,
            'tec_nuevo' => $tecnicoNuevo,
            'obs'       => $observacion,
            'cuando'    => $cuando->format(self::FORMATO_FECHA),
        ]);
    }

    // ---- Tablero ---------------------------------------------------------
    /** @return array<string,int> */
    public function totalesPorEstado(): array
    {
        $rows = $this->pdo->query('SELECT estado, COUNT(*) AS n FROM tickets GROUP BY estado')->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['estado']] = (int) $r['n'];
        }
        return $out;
    }

    public function contarVencidos(DateTimeImmutable $ahora): int
    {
        $st = $this->pdo->prepare("SELECT COUNT(*) FROM tickets WHERE estado <> 'CERRADO' AND fecha_limite <= :ahora");
        $st->execute(['ahora' => $ahora->format(self::FORMATO_FECHA)]);
        return (int) $st->fetchColumn();
    }
}
