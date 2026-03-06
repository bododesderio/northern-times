<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Middleware\CsrfMiddleware;
use App\Middleware\Pipeline;
use App\Services\Csrf;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class LoginFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        unset($_SESSION["_csrf"]);
        Pipeline::alias("csrf", CsrfMiddleware::class);
    }

    public function testLoginPostWithoutCsrfReturns419(): void
    {
        $request = Request::create("/admin/login", "POST", [
            "email" => "admin@test.com",
            "password" => "password",
        ]);
        $controller = fn(Request $r) => new Response("should not reach", 200);
        $response = Pipeline::run($request, ["csrf"], $controller);
        $this->assertSame(419, $response->getStatusCode());
    }

    public function testLoginPostWithValidCsrfReachesController(): void
    {
        $token = Csrf::token();
        $request = Request::create("/admin/login", "POST", [
            "email" => "admin@test.com",
            "password" => "password",
            "_csrf" => $token,
        ]);
        $reached = false;
        $controller = function (Request $r) use (&$reached) {
            $reached = true;
            return new Response("login handler", 200);
        };
        $response = Pipeline::run($request, ["csrf"], $controller);
        $this->assertTrue($reached);
        $this->assertSame(200, $response->getStatusCode());
    }
}