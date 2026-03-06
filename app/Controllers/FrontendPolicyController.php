<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\PolicyPage;
use Symfony\Component\HttpFoundation\Response;

final class FrontendPolicyController extends Controller
{
    public function show(string $slug): Response
    {
        $page = PolicyPage::findPublished($slug);

        if (!$page) {
            return new Response("404 Not Found", 404, ['Content-Type' => 'text/plain']);
        }

        $siteTitle = site_name();
        $meta = [
            'title'       => $page['title'] . ' — ' . $siteTitle,
            'description' => trim(strip_tags(mb_substr($page['content'], 0, 160))),
            'canonical'   => \app_url('/policy/' . $slug),
        ];

        ob_start();
        require __DIR__ . '/../Views/frontend/policy.php';
        $content = ob_get_clean();

        ob_start();
        require __DIR__ . '/../Views/frontend/layout.php';
        $html = ob_get_clean();

        return new Response($html, 200, [
            'Content-Type'          => 'text/html; charset=UTF-8',
            'X-Content-Type-Options'=> 'nosniff',
        ]);
    }
}