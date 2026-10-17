<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\ApiException;
use App\Core\Request;
use App\Domain\Rol;
use App\Repository\CuentaRepository;
use DateTimeImmutable;
use DateTimeZone;

final class AuthService
{
    public function __construct(
        private readonly CuentaRepository $cuentas,
        private readonly DateTimeZone $tz
    ) {
    }

    /** Inicia sesión con usuario/correo y contraseña. Retorna token y perfil del usuario. */
    public function login(array $in): array
    {
        $login    = trim((string) ($in['username'] ?? $in['login'] ?? ''));
        $password = (string) ($in['password'] ?? '');

        if ($login === '' || $password === '') {
            throw ApiException::validacion([
                'login'    => $login === '' ? 'Ingresa tu usuario o correo' : null,
                'password' => $password === '' ? 'Ingresa tu contraseña' : null,
            ], 'Credenciales requeridas');
        }

        $cuenta = $this->cuentas->porLogin($login);
        if ($cuenta === null || !password_verify($password, $cuenta['password_hash'])) {
            throw ApiException::noAutorizado('Usuario o contraseña incorrectos');
        }

        if ((int) $cuenta['activo'] !== 1) {
            throw ApiException::prohibido('Tu cuenta se encuentra inactiva. Contacta al administrador.');
        }

        $ahoraDT = new DateTimeImmutable('now', $this->tz);
        $token   = bin2hex(random_bytes(32));
        $expira  = $ahoraDT->modify('+24 hours')->format('Y-m-d H:i:s');
        $creado  = $ahoraDT->format('Y-m-d H:i:s');

        $this->cuentas->crearSesion($token, (int) $cuenta['id'], $expira, $creado);

        return [
            'token'   => $token,
            'expira'  => $expira,
            'usuario' => $this->formatearPerfil($cuenta),
        ];
    }

    public function logout(?string $token): void
    {
        if ($token !== null && trim($token) !== '') {
            $this->cuentas->eliminarSesion(trim($token));
        }
    }

    /** Extrae el usuario autenticado desde el encabezado Authorization: Bearer <token>. */
    public function usuarioActual(Request $request): ?array
    {
        $token = $this->extraerToken($request);
        if ($token === null) {
            return null;
        }

        $ahora = (new DateTimeImmutable('now', $this->tz))->format('Y-m-d H:i:s');
        $cuenta = $this->cuentas->porToken($token, $ahora);
        return $cuenta !== null ? $this->formatearPerfil($cuenta) : null;
    }

    /** Exige autenticación; si no hay sesión válida lanza 401. */
    public function exigirUsuario(Request $request): array
    {
        $usuario = $this->usuarioActual($request);
        if ($usuario === null) {
            throw ApiException::noAutorizado('Debes iniciar sesión para realizar esta acción');
        }
        return $usuario;
    }

    /** Exige uno de los roles permitidos; si no lo tiene lanza 403. */
    public function exigirRoles(Request $request, array $rolesPermitidos): array
    {
        $usuario = $this->exigirUsuario($request);
        if (!in_array($usuario['rol'], $rolesPermitidos, true)) {
            throw ApiException::prohibido('Tu rol (' . $usuario['rol'] . ') no tiene permisos para esta acción');
        }
        return $usuario;
    }

    public function extraerToken(Request $request): ?string
    {
        $auth = $request->header('Authorization');
        if ($auth !== null && preg_match('/^Bearer\s+(.+)$/i', trim($auth), $m)) {
            return trim($m[1]);
        }
        $xToken = $request->header('X-Auth-Token');
        if ($xToken !== null && trim($xToken) !== '') {
            return trim($xToken);
        }
        return null;
    }

    private function formatearPerfil(array $c): array
    {
        return [
            'id'             => (int) $c['id'],
            'username'       => (string) $c['username'],
            'nombre'         => (string) $c['nombre'],
            'email'          => (string) $c['email'],
            'rol'            => (string) $c['rol'],
            'usuario_id'     => $c['usuario_id'] !== null ? (int) $c['usuario_id'] : null,
            'usuario_codigo' => $c['usuario_codigo'] ?? null,
            'tecnico_id'     => $c['tecnico_id'] !== null ? (int) $c['tecnico_id'] : null,
            'tecnico_nombre' => $c['tecnico_nombre'] ?? null,
        ];
    }
}
