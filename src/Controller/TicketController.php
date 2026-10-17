<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Service\AuthService;
use App\Service\TicketService;

final class TicketController
{
    public function __construct(
        private readonly TicketService $service,
        private readonly AuthService $auth
    ) {
    }

    public function index(Request $req, array $p): Response
    {
        $usuario = $this->auth->usuarioActual($req);
        return Response::json($this->service->listar($req->query, $usuario));
    }

    public function resumen(Request $req, array $p): Response
    {
        return Response::json(['data' => $this->service->resumen()]);
    }

    public function show(Request $req, array $p): Response
    {
        $usuario = $this->auth->usuarioActual($req);
        return Response::json(['data' => $this->service->detalle($p['numero'], $usuario)]);
    }

    public function store(Request $req, array $p): Response
    {
        $usuario = $this->auth->usuarioActual($req);
        $ticket = $this->service->crear($req->json(), $usuario);
        return Response::json(['data' => $ticket], 201, ['Location' => '/api/tickets/' . $ticket['numero']]);
    }

    public function estado(Request $req, array $p): Response
    {
        $usuario = $this->auth->usuarioActual($req);
        return Response::json(['data' => $this->service->cambiarEstado($p['numero'], $req->json(), $usuario)]);
    }

    public function tecnico(Request $req, array $p): Response
    {
        $usuario = $this->auth->usuarioActual($req);
        return Response::json(['data' => $this->service->reasignar($p['numero'], $req->json(), $usuario)]);
    }
}
