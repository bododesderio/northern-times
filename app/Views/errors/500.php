<?php
$code    = 500;
$title   = 'Something Went Wrong';
$message = 'We\'re experiencing a technical difficulty. Our team has been notified and is working on it. Please try again in a few moments.';
$detail  = $detail ?? '';
$actions = [
    [
        'href' => '/', 'label' => 'Front Page', 'primary' => true,
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
    ],
    [
        'href' => 'javascript:location.reload()', 'label' => 'Try Again',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>',
    ],
];
require __DIR__ . '/_base.php';