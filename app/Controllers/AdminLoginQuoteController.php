<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\LoginQuote;
use App\Services\Csrf;
use App\Services\Flash;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Manages the rotating quotes shown on the admin login page.
 */
final class AdminLoginQuoteController extends Controller
{
    public function index(): Response
    {
        return $this->render('admin/login-quotes/index', [
            'quotes'        => LoginQuote::allForAdmin(),
            'csrf'          => Csrf::token(),
            'activeNav'     => 'login-quotes',
            'pageTitle'     => 'Login Page Quotes',
            'flash_success' => Flash::get('success'),
            'flash_error'   => Flash::get('error'),
        ]);
    }

    public function store(): Response
    {
        $r      = Request::createFromGlobals();
        $quote  = trim((string)$r->request->get('quote', ''));
        $author = trim((string)$r->request->get('author', ''));
        $sort   = (int)$r->request->get('sort_order', 0);

        if ($quote === '') {
            Flash::set('error', 'Quote text is required.');
            return $this->redirect('/admin/login-quotes');
        }

        LoginQuote::createQuote($quote, $author, $sort);
        Flash::set('success', 'Quote added.');
        return $this->redirect('/admin/login-quotes');
    }

    public function update(string $id): Response
    {
        $r      = Request::createFromGlobals();
        $quote  = trim((string)$r->request->get('quote', ''));
        $author = trim((string)$r->request->get('author', ''));
        $sort   = (int)$r->request->get('sort_order', 0);

        if ($quote === '') {
            Flash::set('error', 'Quote text is required.');
            return $this->redirect('/admin/login-quotes');
        }

        if (!LoginQuote::find($id)) {
            Flash::set('error', 'Quote not found.');
            return $this->redirect('/admin/login-quotes');
        }

        LoginQuote::updateQuote($id, $quote, $author, $sort);
        Flash::set('success', 'Quote updated.');
        return $this->redirect('/admin/login-quotes');
    }

    public function delete(string $id): Response
    {
        if (!LoginQuote::find($id)) {
            Flash::set('error', 'Quote not found.');
            return $this->redirect('/admin/login-quotes');
        }

        LoginQuote::delete($id);
        Flash::set('success', 'Quote deleted.');
        return $this->redirect('/admin/login-quotes');
    }

    public function toggle(string $id): Response
    {
        $row = LoginQuote::find($id);
        if (!$row) {
            return new Response(json_encode(['ok' => false]), 404, ['Content-Type' => 'application/json']);
        }
        $active = LoginQuote::toggleActive($id);
        return new Response(json_encode(['ok' => true, 'active' => $active]), 200, ['Content-Type' => 'application/json']);
    }
}