<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\ApiException;
use App\Domain\Estado;
use App\Domain\Prioridad;
use App\Domain\Reglas;
use App\Domain\Sla;
use App\Repository\CategoriaRepository;
use App\Repository\TecnicoRepository;
use App\Repository\TicketRepository;
use App\Repository\UsuarioRepository;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Lógica de negocio de tickets: validación, flujo de estados, tope de carga, SLA e historial. */
final class TicketService
{
    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly TecnicoRepository $tecnicos,
        private readonly UsuarioRepository $usuarios,
        private readonly CategoriaRepository $categorias,
        private readonly TicketMapper $mapper,
        private readonly DateTimeZone $tz
    ) {
    }

    private function ahora(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->tz);
    }

    // =====================================================================
    // Consultas
    // =====================================================================
    public function listar(array $query, ?array $usuario = null): array
    {
        $errores = [];
        $filtros = [];

        if (self::presente($query, 'estado')) {
            $e = Estado::desdeTexto($query['estado']);
            $e ? $filtros['estado'] = $e->value : $errores['estado'] = 'Estado inválido (ABIERTO, EN CURSO, PENDIENTE o CERRADO)';
        }
        if (self::presente($query, 'prioridad')) {
            $p = Prioridad::desdeTexto($query['prioridad']);
            $p ? $filtros['prioridad'] = $p->value : $errores['prioridad'] = 'Prioridad inválida (Alta, Media o Baja)';
        }
        if (self::presente($query, 'tecnico')) {
            $id = is_string($query['tecnico']) ? filter_var($query['tecnico'], FILTER_VALIDATE_INT) : false;
            ($id !== false && $id > 0) ? $filtros['tecnico_id'] = $id : $errores['tecnico'] = 'El técnico debe ser un id numérico';
        }
        // Rol Solicitante: solo ve sus propios requerimientos
        if ($usuario !== null && $usuario['rol'] === 'SOLICITANTE' && !empty($usuario['usuario_id'])) {
            $filtros['usuario_id'] = (int) $usuario['usuario_id'];
        }
        if (self::presente($query, 'q')) {
            if (is_string($query['q'])) {
                $filtros['q'] = mb_substr(trim($query['q']), 0, 100);
            } else {
                $errores['q'] = 'El texto de búsqueda debe ser una cadena';
            }
        }
        if ($errores) {
            throw ApiException::validacion($errores, 'Parámetros de consulta inválidos');
        }

        $page    = max(1, self::entero($query['page'] ?? null, 1));
        $perPage = min(Reglas::POR_PAGINA_MAXIMO, max(1, self::entero($query['per_page'] ?? null, Reglas::POR_PAGINA_DEFECTO)));

        [$filas, $total] = $this->tickets->buscar($filtros, $perPage, ($page - 1) * $perPage);
        $ahora = $this->ahora();

        return [
            'data' => array_map(fn (array $r): array => $this->mapper->resumen($r, $ahora), $filas),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
        ];
    }

    public function detalle(string $numero, ?array $usuario = null): array
    {
        $fila = self::formatoNumeroValido($numero) ? $this->tickets->porNumero($numero) : null;
        if ($fila === null) {
            throw ApiException::noEncontrado("No existe el ticket $numero");
        }
        if ($usuario !== null && $usuario['rol'] === 'SOLICITANTE' && !empty($usuario['usuario_id'])) {
            if ((int) $fila['usuario_id'] !== (int) $usuario['usuario_id']) {
                throw ApiException::prohibido('No tienes permiso para consultar este ticket');
            }
        }
        return $this->mapper->detalle($fila, $this->tickets->historial((int) $fila['id']), $this->ahora());
    }

    /** Totales por estado, vencidos y carga activa por técnico (tablero resumen). */
    public function resumen(): array
    {
        $porEstado = $this->tickets->totalesPorEstado();
        $totales = [];
        foreach (Estado::cases() as $e) {
            $totales[$e->value] = $porEstado[$e->value] ?? 0;
        }
        $carga = array_map(static fn (array $t): array => [
            'id'      => (int) $t['id'],
            'nombre'  => $t['nombre'],
            'activos' => (int) $t['activos'],
            'maximo'  => Reglas::CARGA_MAXIMA_TECNICO,
        ], $this->tecnicos->listarConCarga(true));

        return [
            'total'          => array_sum($totales),
            'por_estado'     => $totales,
            'vencidos'       => $this->tickets->contarVencidos($this->ahora()),
            'carga_tecnicos' => $carga,
        ];
    }

    // =====================================================================
    // Registro
    // =====================================================================
    public function crear(array $in, ?array $usuarioAuth = null): array
    {
        $errores = [];

        // Si es solicitante autenticado, se asocia directamente su código de usuario
        if ($usuarioAuth !== null && $usuarioAuth['rol'] === 'SOLICITANTE' && !empty($usuarioAuth['usuario_codigo'])) {
            $codigo = (string) $usuarioAuth['usuario_codigo'];
        } else {
            $codigo = is_string($in['codigo_usuario'] ?? null) ? trim($in['codigo_usuario']) : '';
        }
        $usuario = null;
        if ($codigo === '' || mb_strlen($codigo) > 20) {
            $errores['codigo_usuario'] = 'El código del usuario es obligatorio (máx. 20 caracteres)';
        } elseif (($usuario = $this->usuarios->porCodigo($codigo)) === null) {
            $errores['codigo_usuario'] = 'El código de usuario no existe';
        } elseif (!$usuario['activo']) {
            $errores['codigo_usuario'] = 'El usuario está inactivo y no puede registrar tickets';
        }

        $categoriaId = self::entero($in['categoria_id'] ?? null, 0);
        if ($categoriaId <= 0 || !$this->categorias->existe($categoriaId)) {
            $errores['categoria_id'] = 'Selecciona una categoría válida';
        }

        $descripcion = is_string($in['descripcion'] ?? null) ? trim($in['descripcion']) : '';
        $len = mb_strlen($descripcion);
        if ($len < Reglas::DESCRIPCION_MIN || $len > Reglas::DESCRIPCION_MAX) {
            $errores['descripcion'] = sprintf(
                'La descripción debe tener entre %d y %d caracteres (tiene %d)',
                Reglas::DESCRIPCION_MIN,
                Reglas::DESCRIPCION_MAX,
                $len
            );
        }

        $prioridad = Prioridad::desdeTexto($in['prioridad'] ?? null);
        if ($prioridad === null) {
            $errores['prioridad'] = 'La prioridad debe ser Alta, Media o Baja';
        }

        $tecnicoId = self::entero($in['tecnico_id'] ?? null, 0);
        if ($tecnicoId <= 0) {
            $errores['tecnico_id'] = 'Selecciona un técnico';
        } elseif (($t = $this->tecnicos->buscar($tecnicoId)) === null || !$t['activo']) {
            $errores['tecnico_id'] = 'El técnico no existe o está inactivo';
        }

        if ($errores) {
            throw ApiException::validacion($errores);
        }

        $this->tickets->begin();
        try {
            // Bloqueo de la fila del técnico: serializa asignaciones concurrentes y hace fiable el conteo.
            $tecnico = $this->tecnicos->buscar($tecnicoId, true);
            if ($tecnico === null || !$tecnico['activo']) {
                throw ApiException::validacion(['tecnico_id' => 'El técnico no existe o está inactivo']);
            }
            $this->verificarCapacidad($tecnicoId, $tecnico['nombre'], 'tecnico_id', 422);

            $ahora  = $this->ahora();
            $numero = sprintf('TK-%04d-%06d', (int) $ahora->format('Y'), $this->tickets->siguienteConsecutivo((int) $ahora->format('Y')));

            $id = $this->tickets->crear([
                'numero'       => $numero,
                'usuario_id'   => (int) $usuario['id'],
                'categoria_id' => $categoriaId,
                'tecnico_id'   => $tecnicoId,
                'descripcion'  => $descripcion,
                'prioridad'    => $prioridad->value,
                'estado'       => Estado::Abierto->value,
                'creado'       => $ahora->format(TicketRepository::FORMATO_FECHA),
                'limite'       => Sla::fechaLimite($ahora, $prioridad)->format(TicketRepository::FORMATO_FECHA),
            ]);
            $this->tickets->agregarHistorial($id, 'CREACION', null, Estado::Abierto->value, null, $tecnicoId, 'Ticket registrado.', $ahora);
            $this->tickets->commit();
        } catch (Throwable $e) {
            $this->tickets->rollBack();
            throw $e;
        }

        return $this->detalle($numero);
    }

    // =====================================================================
    // Cambio de estado
    // =====================================================================
    public function cambiarEstado(string $numero, array $in, ?array $usuarioAuth = null): array
    {
        $nuevo = Estado::desdeTexto($in['estado'] ?? null);
        $errores = [];
        if ($nuevo === null) {
            $errores['estado'] = 'El estado debe ser ABIERTO, EN CURSO, PENDIENTE o CERRADO';
        }
        $observacion = $this->observacion($in, $errores);
        if ($nuevo === Estado::Pendiente && ($observacion === null || mb_strlen($observacion) < 5)) {
            $errores['observacion'] = 'Para pasar a PENDIENTE debes registrar el motivo (mínimo 5 caracteres)';
        }
        if (!self::formatoNumeroValido($numero)) {
            throw ApiException::noEncontrado("No existe el ticket $numero");
        }
        if ($errores) {
            throw ApiException::validacion($errores);
        }

        $this->tickets->begin();
        try {
            $fila = $this->tickets->porNumero($numero, true);
            if ($fila === null) {
                throw ApiException::noEncontrado("No existe el ticket $numero");
            }

            // Control de acceso basado en roles (RBAC):
            if ($usuarioAuth !== null) {
                if ($usuarioAuth['rol'] === 'SOLICITANTE') {
                    throw ApiException::prohibido('Los solicitantes no pueden cambiar el estado de un ticket');
                }
                if ($usuarioAuth['rol'] === 'TECNICO' && (int) $fila['tecnico_id'] !== (int) $usuarioAuth['tecnico_id']) {
                    throw ApiException::prohibido('Solo el técnico asignado a este ticket o un administrador puede cambiar su estado');
                }
            }

            $actual = Estado::from($fila['estado']);

            if (!$actual->puedePasarA($nuevo)) {
                throw ApiException::conflicto('TRANSICION_INVALIDA', match (true) {
                    $actual === $nuevo              => "El ticket ya está en estado {$actual->value}",
                    $actual === Estado::Cerrado     => 'Un ticket CERRADO no puede reabrirse',
                    default                         => "Un ticket {$actual->value} no puede pasar a {$nuevo->value}",
                });
            }

            $solucion = null;
            if ($nuevo === Estado::Cerrado) {
                // La solución puede venir en "solucion" o, por compatibilidad, en "observacion".
                $candidata = $in['solucion'] ?? $in['observacion'] ?? null;
                $solucion  = is_string($candidata) ? trim($candidata) : '';
                if (mb_strlen($solucion) < Reglas::SOLUCION_MIN || mb_strlen($solucion) > 2000) {
                    throw ApiException::validacion(
                        ['solucion' => sprintf('Para cerrar debes registrar la solución aplicada (mínimo %d caracteres)', Reglas::SOLUCION_MIN)],
                        'Falta la solución aplicada'
                    );
                }
            }

            $ahora = $this->ahora();
            $this->tickets->actualizarEstado((int) $fila['id'], $nuevo->value, $solucion, $ahora);
            $this->tickets->agregarHistorial(
                (int) $fila['id'], 'ESTADO', $actual->value, $nuevo->value, null, null,
                $observacion ?? $solucion, $ahora
            );
            $this->tickets->commit();
        } catch (Throwable $e) {
            $this->tickets->rollBack();
            throw $e;
        }

        return $this->detalle($numero);
    }

    // =====================================================================
    // Reasignación de técnico
    // =====================================================================
    public function reasignar(string $numero, array $in, ?array $usuarioAuth = null): array
    {
        if ($usuarioAuth !== null && $usuarioAuth['rol'] !== 'ADMIN') {
            throw ApiException::prohibido('Solo los administradores pueden reasignar tickets');
        }

        $errores = [];
        $tecnicoId = self::entero($in['tecnico_id'] ?? $in['tecnico'] ?? null, 0);
        if ($tecnicoId <= 0) {
            $errores['tecnico_id'] = 'Indica el id del técnico';
        }
        $observacion = $this->observacion($in, $errores);
        if (!self::formatoNumeroValido($numero)) {
            throw ApiException::noEncontrado("No existe el ticket $numero");
        }
        if ($errores) {
            throw ApiException::validacion($errores);
        }

        $this->tickets->begin();
        try {
            $fila = $this->tickets->porNumero($numero, true);
            if ($fila === null) {
                throw ApiException::noEncontrado("No existe el ticket $numero");
            }
            if ($fila['estado'] === Estado::Cerrado->value) {
                throw ApiException::conflicto('TICKET_CERRADO', 'No se puede reasignar un ticket CERRADO');
            }
            // Se bloquean ambos técnicos (el actual y el nuevo) SIEMPRE en orden de id: evita
            // interbloqueos entre dos reasignaciones cruzadas (A->B y B->A) simultáneas.
            $ids = array_unique([(int) $fila['tecnico_id'], $tecnicoId]);
            sort($ids);
            $bloqueados = [];
            foreach ($ids as $idTecnico) {
                $bloqueados[$idTecnico] = $this->tecnicos->buscar($idTecnico, true);
            }
            $tecnico = $bloqueados[$tecnicoId];
            if ($tecnico === null || !$tecnico['activo']) {
                throw ApiException::validacion(['tecnico_id' => 'El técnico no existe o está inactivo']);
            }
            if ((int) $fila['tecnico_id'] === $tecnicoId) {
                throw ApiException::validacion(['tecnico_id' => 'El ticket ya está asignado a ese técnico']);
            }

            // Validar si todos los demás técnicos activos tienen su carga máxima (máx. 5 tickets)
            $otrosDisponibles = array_filter(
                $this->tecnicos->listarConCarga(true),
                fn (array $tec): bool => (int) $tec['id'] !== (int) $fila['tecnico_id'] && (int) $tec['activos'] < Reglas::CARGA_MAXIMA_TECNICO
            );
            if (empty($otrosDisponibles)) {
                throw ApiException::conflicto(
                    'TODOS_TECNICOS_LLENOS',
                    'No es posible reasignar: todos los demás técnicos activos tienen su carga máxima (' . Reglas::CARGA_MAXIMA_TECNICO . ' tickets activos)'
                );
            }

            $this->verificarCapacidad($tecnicoId, $tecnico['nombre'], 'tecnico_id', 409);

            $ahora = $this->ahora();
            $this->tickets->actualizarTecnico((int) $fila['id'], $tecnicoId, $ahora);
            $this->tickets->agregarHistorial(
                (int) $fila['id'], 'REASIGNACION', $fila['estado'], $fila['estado'],
                (int) $fila['tecnico_id'], $tecnicoId, $observacion ?? 'Reasignación de técnico.', $ahora
            );
            $this->tickets->commit();
        } catch (Throwable $e) {
            $this->tickets->rollBack();
            throw $e;
        }

        return $this->detalle($numero);
    }

    // =====================================================================
    // Helpers
    // =====================================================================
    /** Un técnico con 5 tickets activos no puede recibir más. Llamar con el técnico ya bloqueado. */
    private function verificarCapacidad(int $tecnicoId, string $nombre, string $campo, int $status): void
    {
        if ($this->tecnicos->contarActivos($tecnicoId) >= Reglas::CARGA_MAXIMA_TECNICO) {
            $msg = sprintf('%s ya tiene %d tickets activos (máximo permitido)', $nombre, Reglas::CARGA_MAXIMA_TECNICO);
            throw $status === 409
                ? ApiException::conflicto('TECNICO_CARGA_MAXIMA', $msg, [$campo => $msg])
                : new ApiException(422, 'TECNICO_CARGA_MAXIMA', $msg, [$campo => $msg]);
        }
    }

    private function observacion(array $in, array &$errores): ?string
    {
        if (!isset($in['observacion']) || $in['observacion'] === '') {
            return null;
        }
        if (!is_string($in['observacion']) || mb_strlen(trim($in['observacion'])) > Reglas::OBSERVACION_MAX) {
            $errores['observacion'] = sprintf('La observación debe ser texto de máximo %d caracteres', Reglas::OBSERVACION_MAX);
            return null;
        }
        $t = trim($in['observacion']);
        return $t === '' ? null : $t;
    }

    private static function formatoNumeroValido(string $numero): bool
    {
        return (bool) preg_match('/^TK-\d{4}-\d{6}$/', $numero);
    }

    private static function presente(array $q, string $k): bool
    {
        return array_key_exists($k, $q) && $q[$k] !== '';
    }

    private static function entero(mixed $v, int $defecto): int
    {
        if (is_int($v)) {
            return $v;
        }
        if (is_string($v) && preg_match('/^-?\d{1,9}$/', trim($v))) {
            return (int) trim($v);
        }
        return $defecto;
    }
}
