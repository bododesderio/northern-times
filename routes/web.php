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
$routes->add('api_tags',       new Route('/api/tags',        ['_controller' => [$front, 'tagsApi']],      [], [], '', [], ['GET']));
$routes->add('tag_search_api', new Route('/api/tags/search', ['_controller' => [$front, 'tagSearchApi']], [], [], '', [], ['GET']));
$routes->add('about',    new Route('/about',            ['_controller' => [$front, 'about'],    '_middleware' => ['visitor']], [], [], '', [], ['GET']));
$routes->add('contact',  new Route('/contact',          ['_controller' => [$front, 'contact'],  '_middleware' => ['visitor']], [], [], '', [], ['GET']));
$routes->add('contact_post', new Route('/contact',      ['_controller' => [$front, 'contactPost'], '_middleware' => ['visitor', 'csrf']], [], [], '', [], ['POST']));
$routes->add('policy',   new Route('/policy/{slug}',   ['_controller' => [$policy, 'show'],    '_middleware' => ['visitor']], [], [], '', [], ['GET']));

// --- Feeds & API ---
$routes->add('rss',        new Route('/rss.xml',        ['_controller' => [$front, 'rss']],        [], [], '', [], ['GET']));
$routes->add('rss_category', new Route('/category/{slug}/rss.xml', ['_controller' => [$front, 'feed']], [], [], '', [], ['GET']));
$routes->add('robots',     new Route('/robots.txt',     ['_controller' => [$front, 'robotsTxt']],   [], [], '', [], ['GET']));
$routes->add('sitemap',    new Route('/sitemap.xml',    ['_controller' => [$front, 'sitemap']],    [], [], '', [], ['GET']));
$routes->add('newsletter', new Route('/api/newsletter', ['_controller' => [$front, 'newsletter'], '_middleware' => ['csrf']], [], [], '', [], ['POST']));
$routes->add('unsubscribe', new Route('/unsubscribe',   ['_controller' => [$front, 'unsubscribe'], '_middleware' => ['csrf']], [], [], '', [], ['GET', 'POST']));
$routes->add('comment',    new Route('/api/comment',    ['_controller' => [$front, 'commentPost'], '_middleware' => ['csrf']], [], [], '', [], ['POST']));
$routes->add('ad_click',          new Route('/api/ad-click/{id}',      ['_controller' => [$front, 'adClick']],          [], [], '', [], ['POST']));
$routes->add('ad_impression',     new Route('/api/ad-impression',     ['_controller' => [$front, 'adImpression']],     [], [], '', [], ['POST']));
$routes->add('visitor_location',  new Route('/api/visitor-location',  ['_controller' => [$front, 'visitorLocation'],  '_middleware' => ['csrf']], [], [], '', [], ['POST']));

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

// --- Health Check ---
$routes->add('api_health', new Route('/api/health', ['_controller' => [$front, 'health']], [], [], '', [], ['GET']));
$routes->add('api_share_track', new Route('/api/share-track', ['_controller' => [$front, 'shareTrack']], [], [], '', [], ['POST']));
$routes->add('api_engagement', new Route('/api/engagement', ['_controller' => [$front, 'engagementTrack']], [], [], '', [], ['POST']));
$routes->add('api_trending', new Route('/api/trending', ['_controller' => [$front, 'trending']], [], [], '', [], ['GET']));

// --- Follow Topics (Email Alerts) ---
$routes->add('api_follow_topic', new Route('/api/follow-topic', ['_controller' => [$front, 'followTopic'], '_middleware' => ['csrf']], [], [], '', [], ['POST']));
$routes->add('api_unfollow_topic', new Route('/api/unfollow-topic', ['_controller' => [$front, 'unfollowTopic']], [], [], '', [], ['GET']));

// --- Syndication API (v1) ---
$routes->add('api_syndication',        new Route('/api/v1/articles',        ['_controller' => [$front, 'syndicationApi']],     [], [], '', [], ['GET']));
$routes->add('api_syndication_single', new Route('/api/v1/articles/{slug}', ['_controller' => [$front, 'syndicationArticle']], [], [], '', [], ['GET']));

// --- Push Notification API (Phase 11) ---
$routes->add('api_push_subscribe',   new Route('/api/push/subscribe',   ['_controller' => [$front, 'pushSubscribe'],   '_middleware' => ['csrf']], [], [], '', [], ['POST']));
$routes->add('api_push_unsubscribe', new Route('/api/push/unsubscribe', ['_controller' => [$front, 'pushUnsubscribe'], '_middleware' => ['csrf']], [], [], '', [], ['POST']));