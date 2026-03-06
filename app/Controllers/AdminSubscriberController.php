<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Subscriber;
use App\Services\Csrf;
use App\Services\Flash;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminSubscriberController extends Controller
{
    public function index(): Response
    {
        $req  = Request::createFromGlobals();
        $q    = trim((string)$req->query->get('q', ''));
        $page = max(1, (int)$req->query->get('page', 1));

        $stats  = Subscriber::dashboardCounts();
        $result = Subscriber::adminList($page, 50, $q);

        return $this->render('admin/subscribers/index', [
            'subscribers'    => $result['rows'],
            'q'              => $q,
            'page'           => $result['page'],
            'totalPages'     => $result['totalPages'],
            'filteredTotal'  => $result['total'],
            'totalCount'     => $stats['total'],
            'activeCount'    => $stats['active'],
            'csrf'           => Csrf::token(),
            'flash_success'  => Flash::get('success'),
            'flash_error'    => Flash::get('error'),
        ]);
    }

    public function delete(string $id): Response
    {
        Subscriber::delete($id);
        Flash::set('success', 'Subscriber removed.');
        return $this->redirect('/admin/subscribers');
    }

    public function toggleConfirm(string $id): Response
    {
        Subscriber::toggleStatus($id);
        Flash::set('success', 'Subscriber status updated.');
        return $this->redirect('/admin/subscribers');
    }

    public function export(): Response
    {
        $rows = Subscriber::export();

        $csv = "Email,Name,Status,Source,Subscribed Date\n";
        foreach ($rows as $r) {
            $csv .= '"' . str_replace('"', '""', (string)$r['email']) . '",';
            $csv .= '"' . str_replace('"', '""', (string)($r['name'] ?? '')) . '",';
            $csv .= '"' . (string)$r['status'] . '",';
            $csv .= '"' . (string)($r['source'] ?? '') . '",';
            $csv .= '"' . (string)$r['created_at'] . '"' . "\n";
        }

        return new Response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="subscribers-' . date('Y-m-d') . '.csv"',
        ]);
    }
}