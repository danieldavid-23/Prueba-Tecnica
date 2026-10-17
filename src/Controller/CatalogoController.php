<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Repository\CategoriaRepository;
use App\Service\TecnicoService;
use App\Service\UsuarioService;

/** Usuarios, técnicos y categorías (datos de apoyo para la captura). */
final class CatalogoController
{
    public function __construct(
        private readonly UsuarioService $usuarios,
        private readonly TecnicoService $tecnicos,
        private readonly CategoriaRepository $categorias
    ) {
    }

    public function usuario(Request $req, array $p): Response
    {
        return Response::json(['data' => $this->usuarios->obtener($p['codigo'])]);
    }

    public function tecnicos(Request $req, array $p): Response
    {
        $todos = ($req->query['todos'] ?? '') === '1';
        return Response::json(['data' => $this->tecnicos->listar($todos)]);
    }

    public function categorias(Request $req, array $p): Response
    {
        return Response::json(['data' => $this->categorias->todas()]);
    }
}
