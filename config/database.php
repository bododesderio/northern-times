<?php
declare(strict_types=1);

return [
  'driver' => 'pgsql',
  'host' => $_ENV['DB_HOST'] ?? 'db',
  'port' => (int)($_ENV['DB_PORT'] ?? 5432),
  'database' => $_ENV['DB_NAME'] ?? 'northern_times',
  'username' => $_ENV['DB_USER'] ?? 'northern',
  'password' => $_ENV['DB_PASS'] ?? 'northern_secret',
];