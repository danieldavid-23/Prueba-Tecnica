<?php
declare(strict_types=1);

namespace App;

use App\Controller\AuthController;
use App\Controller\CatalogoController;
use App\Controller\TicketController;
use App\Core\ApiException;
use App\Core\ApiKeyGuard;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Repository\CategoriaRepository;
use App\Repository\CuentaRepository;
use App\Repository\TecnicoRepository;
use App\Repository\TicketRepository;
use App\Repository\UsuarioRepository;
use App\Service\AuthService;
use App\Service\TecnicoService;
use App\Service\TicketMapper;
use App\Service\TicketService;
use App\Service\UsuarioService;
use DateTimeZone;
use Throwable;

/** Punto de entrada: carga config, conecta, cablea dependencias y despacha la petición. */
final class Kernel
{
    private const ROOT = __DIR__ . '/..';

    public static function api(): void
    {
        try {
            Config::load(self::ROOT . '/config/config.php');
            date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

            $request = Request::fromGlobals();
            ApiKeyGuard::check($request);                       // 401 antes que cualquier otra cosa

            $response = self::router()->dispatch($request);
        } catch (ApiException $e) {
            $response = Response::error($e->status, $e->errorCode, $e->getMessage(), $e->fields);
        } catch (Throwable $e) {
            // Nunca se expone la traza ni el SQL: solo al log del servidor.
            error_log('[soporte-ti] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $response = Response::error(500, 'ERROR_INTERNO', 'Ocurrió un error interno. Intenta de nuevo más tarde.');
        }
        $response->send();
    }

    /** Sirve la SPA (HTML) inyectando la API key para que la interfaz consuma la API. */
    public static function spa(): void
    {
        try {
            Config::load(self::ROOT . '/config/config.php');
            $apiKey = (string) Config::get('api_key', '');
        } catch (Throwable $e) {
            error_log('[soporte-ti] ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'La aplicación no está configurada. Revisa el README (config/config.php).';
            return;
        }
        header('Content-Type: text/html; charset=utf-8');
        require self::ROOT . '/views/app.php';
    }

    private static function router(): Router
    {
        $pdo = Database::connect();
        $tz  = new DateTimeZone((string) Config::get('app.timezone', 'UTC'));

        $cuentasRepo  = new CuentaRepository($pdo);
        $authService  = new AuthService($cuentasRepo, $tz);
        $authCtrl     = new AuthController($authService);

        $tecnicosRepo = new TecnicoRepository($pdo);
        $categorias   = new CategoriaRepository($pdo);
        $tickets = new TicketController(
            new TicketService(
                new TicketRepository($pdo), $tecnicosRepo, new UsuarioRepository($pdo), $categorias, new TicketMapper($tz), $tz
            ),
            $authService
        );
        $catalogo = new CatalogoController(
            new UsuarioService(new UsuarioRepository($pdo)), new TecnicoService($tecnicosRepo), $categorias
        );

        $r = new Router();
        // Autenticación y perfil
        $r->add('POST',  '/api/auth/login',               [$authCtrl, 'login']);
        $r->add('POST',  '/api/auth/logout',              [$authCtrl, 'logout']);
        $r->add('GET',   '/api/auth/me',                  [$authCtrl, 'me']);

        // Tickets y catálogos
        $r->add('GET',   '/api/tickets',                  [$tickets, 'index']);
        $r->add('POST',  '/api/tickets',                  [$tickets, 'store']);
        $r->add('GET',   '/api/tickets/{numero}',         [$tickets, 'show']);
        $r->add('PATCH', '/api/tickets/{numero}/estado',  [$tickets, 'estado']);
        $r->add('PATCH', '/api/tickets/{numero}/tecnico', [$tickets, 'tecnico']);
        $r->add('GET',   '/api/resumen',                  [$tickets, 'resumen']);
        $r->add('GET',   '/api/usuarios/{codigo}',        [$catalogo, 'usuario']);
        $r->add('GET',   '/api/tecnicos',                 [$catalogo, 'tecnicos']);
        $r->add('GET',   '/api/categorias',               [$catalogo, 'categorias']);
        return $r;
    }
}
