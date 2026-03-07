<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();
require_once __DIR__ . '/../app/Support/helpers.php';

$pdo = App\Models\BaseModel::pdo();

$merges = [
    'Nigeria'    => 'World',
    'South South'=> 'World',
    'Show Types' => 'Entertainment',
    'Promoted'   => 'Top Stories',
    'Columns'    => 'Opinion',
    'Oped'       => 'Opinion',
    'More News'  => 'Top Stories',
    'Top News'   => 'Top Stories',
    'National'   => 'Top Stories',
    'People'     => 'Lifestyle',
    'Women'      => 'Lifestyle',
    'Society'    => 'Lifestyle',
    'Interview'  => 'Top Stories',
    'Art'        => 'Entertainment',
];

foreach ($merges as $from => $to) {
    $target = $pdo->prepare("SELECT id FROM categories WHERE name = :n LIMIT 1");
    $target->execute([':n' => $to]);
    $targetId = $target->fetchColumn();

    $source = $pdo->prepare("SELECT id FROM categories WHERE name = :n LIMIT 1");
    $source->execute([':n' => $from]);
    $sourceId = $source->fetchColumn();

    if (!$sourceId || !$targetId) { echo "Skip: {$from}\n"; continue; }

    $move = $pdo->prepare("UPDATE articles SET category_id = :to WHERE category_id = :from");
    $move->execute([':to' => $targetId, ':from' => $sourceId]);
    $moved = $move->rowCount();

    $pdo->prepare("UPDATE crawl_sources SET default_category_id = :to WHERE default_category_id = :from")
        ->execute([':to' => $targetId, ':from' => $sourceId]);

    $pdo->prepare("DELETE FROM categories WHERE id = :id")->execute([':id' => $sourceId]);
    echo "Merged: {$from} -> {$to} ({$moved} articles)\n";
}

$pdo->exec("UPDATE categories SET show_in_sidebar = false WHERE name IN ('Real Estate', 'Travel', 'Religion', 'Investigations')");

echo "\nRemaining categories:\n";
$cats = $pdo->query("SELECT name, slug FROM categories ORDER BY sort_order, name")->fetchAll(\PDO::FETCH_ASSOC);
foreach ($cats as $c) echo "  {$c['name']} ({$c['slug']})\n";
