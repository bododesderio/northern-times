<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
chdir('/var/www/html');
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();
try {
    $result = App\Models\Article::adminList(1, 5);
    echo 'adminList OK: ' . count($result['rows']) . ' rows' . PHP_EOL;
} catch (Throwable $e) {
    echo 'adminList FAILED: ' . $e->getMessage() . PHP_EOL;
    echo 'File: ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
try {
    $cats = App\Models\Category::nameSlugList();
    echo 'Categories OK: ' . count($cats) . PHP_EOL;
} catch (Throwable $e) {
    echo 'Categories FAILED: ' . $e->getMessage() . PHP_EOL;
}
