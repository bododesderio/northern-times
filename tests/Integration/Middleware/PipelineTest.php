<?php
declare(strict_types=1);

namespace Tests\Integration\Middleware;

use App\Middleware\Pipeline;
use App\Services\Csrf;
use App\Services\RBAC;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PipelineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        unset($_SESSION['user'], $_SESSION['_csrf']);
        RBAC::reset();
    }

    public function test_empty_stack(): void
    {
        $ctrl = fn(Request $r) => new Response('OK', 200);
        $resp = Pipeline::run(Request::create('/'), [], $ctrl);
        $this->assertSame(200, $resp->getStatusCode());
    }

    public function test_auth_blocks_guest(): void
    {
        $ctrl = fn(Request $r) => new Response('Nope', 200);
        $resp = Pipeline::run(Request::create('/admin'), ['auth'], $ctrl);
        $this->assertSame(302, $resp->getStatusCode());
    }

    public function test_csrf_blocks_no_token(): void
    {
        Csrf::token();
        $ctrl = fn(Request $r) => new Response('OK', 200);
        $resp = Pipeline::run(Request::create('/x', 'POST'), ['csrf'], $ctrl);
        $this->assertSame(419, $resp->getStatusCode());
    }

    public function test_auth_runs_before_csrf(): void
    {
        $ctrl = fn(Request $r) => new Response('OK', 200);
        $resp = Pipeline::run(Request::create('/x', 'POST'), ['auth', 'csrf'], $ctrl);
        $this->assertSame(302, $resp->getStatusCode());
    }

    public function test_full_valid_stack(): void
    {
        $_SESSION['user'] = ['id'=>'u','email'=>'a@b.com','username'=>'a','role'=>'super_admin'];
        $token = Csrf::token();
        $ctrl = fn(Request $r) => new Response('OK', 200);
        $resp = Pipeline::run(Request::create('/x', 'POST', ['_csrf'=>$token]), ['auth','csrf'], $ctrl);
        $this->assertSame(200, $resp->getStatusCode());
    }

    public function test_role_blocks_insufficient(): void
    {
        $_SESSION['user'] = ['id'=>'u','email'=>'a@b.com','username'=>'a','role'=>'author'];
        RBAC::reset();
        $ctrl = fn(Request $r) => new Response('OK', 200);
        $resp = Pipeline::run(Request::create('/admin/users'), ['auth','role:super_admin'], $ctrl);
        $this->assertSame(403, $resp->getStatusCode());
    }

    public function test_role_allows_higher_level(): void
    {
        $_SESSION['user'] = ['id'=>'u','email'=>'a@b.com','username'=>'a','role'=>'super_admin'];
        RBAC::reset();
        $ctrl = fn(Request $r) => new Response('OK', 200);
        $resp = Pipeline::run(Request::create('/admin/x'), ['auth','role:editor'], $ctrl);
        $this->assertSame(200, $resp->getStatusCode());
    }
}