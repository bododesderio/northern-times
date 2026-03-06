<?php
$code    = 404;
$title   = 'Page Not Found';
$message = 'The story you\'re looking for doesn\'t exist, may have been moved, or the URL might be incorrect. Check the address or head back to the front page.';
$detail  = $detail ?? '';
$actions = [
    [
        'href' => '/', 'label' => 'Front Page', 'primary' => true,
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
    ],
    [
        'href' => '/search', 'label' => 'Search',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
    ],
    [
        'href' => 'javascript:history.back()', 'label' => 'Go Back',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>',
    ],
];
require __DIR__ . '/_base.php';