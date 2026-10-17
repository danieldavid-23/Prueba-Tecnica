<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class CuentaRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function porLogin(string $login): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT c.id, c.username, c.email, c.password_hash, c.rol, c.usuario_id, c.tecnico_id,
                    c.nombre, c.activo, u.codigo AS usuario_codigo, t.nombre AS tecnico_nombre
             FROM cuentas c
             LEFT JOIN usuarios u ON u.id = c.usuario_id
             LEFT JOIN tecnicos t ON t.id = c.tecnico_id
             WHERE (LOWER(c.username) = LOWER(:login1) OR LOWER(c.email) = LOWER(:login2))'
        );
        $val = trim($login);
        $st->execute(['login1' => $val, 'login2' => $val]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function porId(int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT c.id, c.username, c.email, c.rol, c.usuario_id, c.tecnico_id,
                    c.nombre, c.activo, u.codigo AS usuario_codigo, t.nombre AS tecnico_nombre
             FROM cuentas c
             LEFT JOIN usuarios u ON u.id = c.usuario_id
             LEFT JOIN tecnicos t ON t.id = c.tecnico_id
             WHERE c.id = :id'
        );
        $st->execute(['id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function crearSesion(string $token, int $cuentaId, string $expiraEn, string $creadoEn): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO sesiones (id, cuenta_id, expira_en, creado_en) VALUES (:id, :cuenta_id, :expira_en, :creado_en)'
        );
        $st->execute([
            'id'        => $token,
            'cuenta_id' => $cuentaId,
            'expira_en' => $expiraEn,
            'creado_en' => $creadoEn,
        ]);
    }

    public function porToken(string $token, string $ahora): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT c.id, c.username, c.email, c.rol, c.usuario_id, c.tecnico_id,
                    c.nombre, c.activo, u.codigo AS usuario_codigo, t.nombre AS tecnico_nombre, s.expira_en
             FROM sesiones s
             INNER JOIN cuentas c ON c.id = s.cuenta_id
             LEFT JOIN usuarios u ON u.id = c.usuario_id
             LEFT JOIN tecnicos t ON t.id = c.tecnico_id
             WHERE s.id = :token AND s.expira_en > :ahora AND c.activo = 1'
        );
        $st->execute(['token' => $token, 'ahora' => $ahora]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function eliminarSesion(string $token): void
    {
        $st = $this->pdo->prepare('DELETE FROM sesiones WHERE id = :token');
        $st->execute(['token' => $token]);
    }

    public function limpiarSesionesExpiradas(string $ahora): void
    {
        $st = $this->pdo->prepare('DELETE FROM sesiones WHERE expira_en <= :ahora');
        $st->execute(['ahora' => $ahora]);
    }
}
