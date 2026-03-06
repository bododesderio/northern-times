<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use App\Controllers\FrontendController;
use App\Controllers\FrontendPolicyController;

/**
 * Controller Initialization
 */
$front  = new FrontendController();
$policy = new FrontendPolicyController();

// --- Frontend Routes (with visitor tracking) ---
$routes->add('home',     new Route('/',                ['_controller' => [$front, 'home'],     '_middleware' => ['visitor']], [], [], '', [], ['GET']));
$routes->add('article',  new Route('/article/{slug}',  ['_controller' => [$front, 'article'],  '_middleware' => ['visitor']], [], [], '', [], ['GET']));
$routes->add('category', new Route('/category/{slug}', ['_controller' => [$front, 'category'], '_middleware' => ['visitor']], [], [], '', [], ['GET']));
$routes->add('search',   new Route('/search',          ['_controller' => [$front, 'search'],   '_middleware' => ['visitor']], [], [], '', [], ['GET']));
$routes->add('search_api', new Route('/api/search',    ['_controller' => [$front, 'searchApi']], [], [], '', [], ['GET']));
$routes->add('tag',       new Route('/tag/{slug}',      ['_controller' => [$front, 'tag'],      '_middleware' => ['visitor']], [], [], '', [], ['GET']));
$routes->add('author',    new Route('/author/{username}',['_controller' => [$front, 'author'],  '_middleware' => ['visitor']], [], [], '', [], ['GET']));
$routes->add('tag_search_api', new Route('/api/tags/search', ['_controller' => [$front, 'tagSearchApi']], [], [], '', [], ['GET']));
$routes->add('policy',   new Route('/policy/{slug}',   ['_controller' => [$policy, 'show'],    '_middleware' => ['visitor']], [], [], '', [], ['GET']));

// --- Feeds & API ---
$routes->add('rss',        new Route('/rss.xml',        ['_controller' => [$front, 'rss']],        [], [], '', [], ['GET']));
$routes->add('robots',     new Route('/robots.txt',     ['_controller' => [$front, 'robotsTxt']],   [], [], '', [], ['GET']));
$routes->add('sitemap',    new Route('/sitemap.xml',    ['_controller' => [$front, 'sitemap']],    [], [], '', [], ['GET']));
$routes->add('newsletter', new Route('/api/newsletter', ['_controller' => [$front, 'newsletter']], [], [], '', [], ['POST']));
$routes->add('unsubscribe', new Route('/unsubscribe',   ['_controller' => [$front, 'unsubscribe']], [], [], '', [], ['GET', 'POST']));
$routes->add('comment',    new Route('/api/comment',    ['_controller' => [$front, 'commentPost']], [], [], '', [], ['POST']));
$routes->add('ad_click',   new Route('/api/ad-click/{id}', ['_controller' => [$front, 'adClick']],  [], [], '', [], ['GET', 'POST']));

// /api/geo — lightweight GeoIP lookup via Services/GeoIP.php
$routes->add('api_geo', new Route('/api/geo', ['_controller' => function (): Response {
    $ip   = \App\Services\GeoIP::clientIP();
    $data = \App\Services\GeoIP::lookup($ip);
    return new Response(json_encode($data), 200, [
        'Content-Type'  => 'application/json; charset=UTF-8',
        'Cache-Control' => 'private, max-age=3600',
    ]);
}], [], [], '', [], ['GET']));
// --- Popup API (Phase 6) ---
$popupApi = new \App\Controllers\AdminPopupController();
$routes->add('api_popups',       new Route('/api/popups',       ['_controller' => [$popupApi, 'apiActive']], [], [], '', [], ['GET']));
$routes->add('api_popup_track',  new Route('/api/popup-track',  ['_controller' => [$popupApi, 'apiTrack']],  [], [], '', [], ['POST']));

// --- Push Notification API (Phase 11) ---
$routes->add('api_push_subscribe',   new Route('/api/push/subscribe',   ['_controller' => [$front, 'pushSubscribe']],   [], [], '', [], ['POST']));
$routes->add('api_push_unsubscribe', new Route('/api/push/unsubscribe', ['_controller' => [$front, 'pushUnsubscribe']], [], [], '', [], ['POST']));