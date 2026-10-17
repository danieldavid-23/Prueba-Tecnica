<?php
declare(strict_types=1);

namespace App\Core;

/** Enrutador mínimo: rutas con parámetros {nombre}, 404 y 405 con el formato único de error. */
final class Router
{
    /** @var array<int, array{string, string, callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = [$method, $regex, $handler];
    }

    public function dispatch(Request $request): Response
    {
        $pathMatched = false;
        foreach ($this->routes as [$method, $regex, $handler]) {
            if (!preg_match($regex, $request->path, $m)) {
                continue;
            }
            $pathMatched = true;
            if ($method !== $request->method) {
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            return $handler($request, $params);
        }
        if ($pathMatched) {
            return Response::error(405, 'METODO_NO_PERMITIDO', 'Método no permitido para este recurso');
        }
        return Response::error(404, 'RUTA_NO_ENCONTRADA', 'La ruta solicitada no existe');
    }
}
