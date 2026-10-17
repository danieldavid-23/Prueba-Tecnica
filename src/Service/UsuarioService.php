<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\ApiException;
use App\Repository\UsuarioRepository;

final class UsuarioService
{
    public function __construct(private readonly UsuarioRepository $usuarios)
    {
    }

    /** Devuelve el usuario (también si está inactivo, con activo=false); 404 si el código no existe. */
    public function obtener(string $codigo): array
    {
        $u = $this->usuarios->porCodigo($codigo);
        if ($u === null) {
            throw ApiException::noEncontrado("No existe un usuario con código $codigo");
        }
        return [
            'codigo' => $u['codigo'],
            'nombre' => $u['nombre'],
            'cargo'  => $u['cargo'],
            'area'   => $u['area'],
            'activo' => (bool) $u['activo'],
        ];
    }
}
