<?php
declare(strict_types=1);

namespace Tests\Integration\Middleware;

use App\Middleware\CsrfMiddleware;
use App\Services\Csrf;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CsrfMiddlewareTest extends TestCase
{
    private CsrfMiddleware $mw;
    private \Closure $next;

    protected function setUp(): void
    {
        parent::setUp();
        unset($_SESSION['_csrf']);
        $this->mw = new CsrfMiddleware();
        $this->next = fn(Request $r) => new Response('OK', 200);
    }

    public function test_get_passes_through(): void
    {
        $request = Request::create('/admin/articles', 'GET');
        $resp = $this->mw->handle($request, $this->next);
        $this->assertSame(200, $resp->getStatusCode());
    }

    public function test_post_valid_token_passes(): void
    {
        $token = Csrf::token();
        $request = Request::create('/admin/articles', 'POST', ['_csrf' => $token]);
        $resp = $this->mw->handle($request, $this->next);
        $this->assertSame(200, $resp->getStatusCode());
    }

    public function test_post_missing_token_419(): void
    {
        Csrf::token();
        $request = Request::create('/admin/articles', 'POST');
        $resp = $this->mw->handle($request, $this->next);
        $this->assertSame(419, $resp->getStatusCode());
    }

    public function test_post_wrong_token_419(): void
    {
        Csrf::token();
        $request = Request::create('/admin/articles', 'POST', ['_csrf' => 'wrong']);
        $resp = $this->mw->handle($request, $this->next);
        $this->assertSame(419, $resp->getStatusCode());
    }

    public function test_token_via_header(): void
    {
        $token = Csrf::token();
        $request = Request::create('/admin/articles', 'POST', [], [], [], [
            'HTTP_X_CSRF_TOKEN' => $token,
        ]);
        $resp = $this->mw->handle($request, $this->next);
        $this->assertSame(200, $resp->getStatusCode());
    }
}