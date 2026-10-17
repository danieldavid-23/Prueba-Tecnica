<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Service\AuthService;

final class AuthController
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function login(Request $request): Response
    {
        $res = $this->auth->login($request->json());
        return Response::json(['data' => $res]);
    }

    public function logout(Request $request): Response
    {
        $token = $this->auth->extraerToken($request);
        $this->auth->logout($token);
        return Response::json(['data' => ['mensaje' => 'Sesión cerrada exitosamente']]);
    }

    public function me(Request $request): Response
    {
        $usuario = $this->auth->exigirUsuario($request);
        return Response::json(['data' => $usuario]);
    }
}
