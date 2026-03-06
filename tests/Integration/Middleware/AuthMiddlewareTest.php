<?php
declare(strict_types=1);

namespace Tests\Integration\Middleware;

use App\Middleware\AuthMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthMiddlewareTest extends TestCase
{
    private AuthMiddleware $mw;
    private \Closure $next;

    protected function setUp(): void
    {
        parent::setUp();
        unset($_SESSION['user']);
        $this->mw = new AuthMiddleware();
        $this->next = fn(Request $r) => new Response('OK', 200);
    }

    public function test_unauthenticated_redirects(): void
    {
        $request = Request::create('/admin', 'GET');
        $resp = $this->mw->handle($request, $this->next);
        $this->assertSame(302, $resp->getStatusCode());
        $this->assertStringContainsString('/admin/login', $resp->headers->get('Location'));
    }

    public function test_authenticated_passes(): void
    {
        $_SESSION['user'] = [
            'id' => 'test-id', 'email' => 'a@b.com',
            'username' => 'admin', 'role' => 'super_admin',
        ];

        $request = Request::create('/admin', 'GET');
        $resp = $this->mw->handle($request, $this->next);
        $this->assertSame(200, $resp->getStatusCode());
    }
}