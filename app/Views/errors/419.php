<?php
$code    = 419;
$title   = 'Session Expired';
$message = 'Your session or security token has expired. This usually happens when a page is left open too long. Please go back and try again — your data should still be there.';
$detail  = $detail ?? '';
$actions = [
    [
        'href' => 'javascript:history.back()', 'label' => 'Go Back & Retry', 'primary' => true,
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>',
    ],
    [
        'href' => '/admin', 'label' => 'Dashboard',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
    ],
];
require __DIR__ . '/_base.php';