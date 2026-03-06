<?php
declare(strict_types=1);

namespace App\Controllers;

use Symfony\Component\HttpFoundation\Response;

abstract class Controller
{
    protected function viewPath(string $view): string
    {
        $view = trim($view, '/');
        return __DIR__ . '/../Views/' . $view . '.php';
    }

    protected function render(string $view, array $data = [], int $status = 200, array $headers = []): Response
    {
        $path = $this->viewPath($view);

        if (!is_file($path)) {
            return new Response("View not found: {$view}", 500, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $path;
        $html = (string)ob_get_clean();

        $defaultHeaders = [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ];

        return new Response($html, $status, array_merge($defaultHeaders, $headers));
    }

    protected function redirect(string $to, int $status = 302, array $headers = []): Response
    {
        if (!preg_match('#^https?://#i', $to)) {
            $to = '/' . ltrim($to, '/');
        }

        $base = ['Location' => $to];
        return new Response('', $status, array_merge($base, $headers));
    }

    protected function json(array $payload, int $status = 200, array $headers = []): Response
    {
        $defaultHeaders = [
            'Content-Type' => 'application/json; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ];

        return new Response(
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            array_merge($defaultHeaders, $headers)
        );
    }

    protected function text(string $body, int $status = 200, array $headers = []): Response
    {
        $defaultHeaders = [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ];
        return new Response($body, $status, array_merge($defaultHeaders, $headers));
    }
}