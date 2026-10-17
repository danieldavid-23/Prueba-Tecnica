<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class UsuarioRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function porCodigo(string $codigo): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT id, codigo, nombre, cargo, area, activo FROM usuarios WHERE upper(codigo) = upper(:codigo)'
        );
        $st->execute(['codigo' => $codigo]);
        return $st->fetch() ?: null;
    }
}
