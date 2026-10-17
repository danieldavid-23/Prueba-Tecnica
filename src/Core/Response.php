<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    private function __construct(
        public readonly int $status,
        public readonly array $body,
        public readonly array $headers = []
    ) {
    }

    public static function json(array $body, int $status = 200, array $headers = []): self
    {
        return new self($status, $body, $headers);
    }

    /** Formato único de error de la API. */
    public static function error(int $status, string $code, string $message, array $fields = []): self
    {
        return new self($status, [
            'error' => [
                'code'    => $code,
                'message' => $message,
                'fields'  => (object) $fields, // {} cuando no hay campos
            ],
        ]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
