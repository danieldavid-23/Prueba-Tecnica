<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class CategoriaRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function todas(): array
    {
        return $this->pdo->query('SELECT id, nombre FROM categorias ORDER BY id')->fetchAll();
    }

    public function existe(int $id): bool
    {
        $st = $this->pdo->prepare('SELECT 1 FROM categorias WHERE id = :id');
        $st->execute(['id' => $id]);
        return (bool) $st->fetchColumn();
    }
}
