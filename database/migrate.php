<?php
declare(strict_types=1);

use App\Services\DB;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$pdo = DB::pdo();

$pdo->exec("
  CREATE TABLE IF NOT EXISTS migrations (
    id SERIAL PRIMARY KEY,
    migration VARCHAR(255) UNIQUE NOT NULL,
    run_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
  )
");

$dir = __DIR__ . '/migrations';
if (!is_dir($dir)) {
  fwrite(STDERR, "Migrations directory not found: {$dir}\n");
  exit(1);
}

$files = glob($dir . '/*.sql');
sort($files);

$applied = $pdo->query("SELECT migration FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
$appliedSet = array_flip($applied);

$ran = 0;
foreach ($files as $file) {
  $name = basename($file);
  if (isset($appliedSet[$name])) {
    continue;
  }

  $sql = file_get_contents($file);
  if ($sql === false) {
    fwrite(STDERR, "Failed to read migration: {$name}\n");
    exit(1);
  }

  echo "Running migration: {$name}\n";

  try {
    $pdo->beginTransaction();
    $pdo->exec($sql);

    $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (:m)");
    $stmt->execute([':m' => $name]);

    $pdo->commit();
    $ran++;
  } catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Migration failed: {$name}\n");
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
  }
}

echo $ran === 0 ? "No new migrations.\n" : "Done. Ran {$ran} migration(s).\n";