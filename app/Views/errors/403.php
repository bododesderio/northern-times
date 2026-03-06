<?php
$code    = 403;
$title   = 'Access Denied';
$message = 'You don\'t have permission to view this page. If you believe this is an error, please contact your administrator or try logging in with different credentials.';
$detail  = $detail ?? '';
$actions = [
    [
        'href' => '/admin', 'label' => 'Admin Dashboard', 'primary' => true,
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
    ],
    [
        'href' => '/admin/login', 'label' => 'Login',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>',
    ],
    [
        'href' => 'javascript:history.back()', 'label' => 'Go Back',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>',
    ],
];
require __DIR__ . '/_base.php';