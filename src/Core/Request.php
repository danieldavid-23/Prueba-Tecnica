<?php
declare(strict_types=1);

namespace App\Core;

use JsonException;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        private readonly array $server,
        private readonly string $rawBody
    ) {
    }

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = '/' . trim(rawurldecode($path), '/');
        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $_SERVER,
            (string) file_get_contents('php://input')
        );
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $this->server[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    /** Cuerpo JSON como arreglo asociativo. Cuerpo vacío => []. JSON inválido => 400. */
    public function json(): array
    {
        if (trim($this->rawBody) === '') {
            return [];
        }
        try {
            $data = json_decode($this->rawBody, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiException(400, 'JSON_INVALIDO', 'El cuerpo de la petición no es un JSON válido');
        }
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new ApiException(400, 'JSON_INVALIDO', 'El cuerpo de la petición debe ser un objeto JSON');
        }
        return $data;
    }
}
