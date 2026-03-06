<?php
declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;

/**
 * Validates Docker container lifecycle wiring:
 * - Dockerfile ENTRYPOINT present and correctly configured
 * - entrypoint.sh handles all boot tasks (storage, DB wait, migrations, cron, php-fpm)
 * - All three cron jobs registered (crawler, scheduler, email queue)
 * - Security: ca-certificates installed for SSL verification
 */
class DockerEntrypointTest extends TestCase
{
    private string $dockerfile;
    private string $entrypoint;

    protected function setUp(): void
    {
        $this->dockerfile = file_get_contents(__DIR__ . '/../../../docker/php/Dockerfile');
        $this->entrypoint = file_get_contents(__DIR__ . '/../../../docker/php/entrypoint.sh');
    }

    // ═══════════════════════════════════════════════════════════
    //  DOCKERFILE WIRING
    // ═══════════════════════════════════════════════════════════

    public function test_dockerfile_copies_entrypoint(): void
    {
        $this->assertStringContainsString(
            'COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh',
            $this->dockerfile,
            'Dockerfile must COPY entrypoint.sh into the image'
        );
    }

    public function test_dockerfile_makes_entrypoint_executable(): void
    {
        $this->assertStringContainsString(
            'chmod +x /usr/local/bin/entrypoint.sh',
            $this->dockerfile,
            'Dockerfile must chmod +x the entrypoint'
        );
    }

    public function test_dockerfile_declares_entrypoint(): void
    {
        $this->assertStringContainsString(
            'ENTRYPOINT ["entrypoint.sh"]',
            $this->dockerfile,
            'Dockerfile must declare ENTRYPOINT so the script runs on container start'
        );
    }

    public function test_dockerfile_installs_ca_certificates(): void
    {
        $this->assertStringContainsString(
            'ca-certificates',
            $this->dockerfile,
            'Dockerfile must install ca-certificates for SSL verification in crawler'
        );
    }

    public function test_dockerfile_installs_postgresql_client(): void
    {
        $this->assertStringContainsString(
            'postgresql-client',
            $this->dockerfile,
            'postgresql-client required for pg_isready in entrypoint'
        );
    }

    public function test_dockerfile_installs_dcron(): void
    {
        $this->assertStringContainsString(
            'dcron',
            $this->dockerfile,
            'dcron required for scheduled tasks'
        );
    }

    // ═══════════════════════════════════════════════════════════
    //  ENTRYPOINT BOOT SEQUENCE
    // ═══════════════════════════════════════════════════════════

    public function test_entrypoint_has_shebang(): void
    {
        $this->assertStringStartsWith(
            '#!/bin/sh',
            $this->entrypoint,
            'Entrypoint must use /bin/sh for Alpine compatibility'
        );
    }

    public function test_entrypoint_sets_errexit(): void
    {
        $this->assertStringContainsString(
            'set -e',
            $this->entrypoint,
            'Entrypoint must set -e to fail fast on errors'
        );
    }

    public function test_entrypoint_creates_storage_directories(): void
    {
        $this->assertStringContainsString('storage/uploads', $this->entrypoint);
        $this->assertStringContainsString('storage/cache', $this->entrypoint);
        $this->assertStringContainsString('storage/logs', $this->entrypoint);
        $this->assertStringContainsString('storage/backups', $this->entrypoint);
    }

    public function test_entrypoint_chowns_storage(): void
    {
        $this->assertStringContainsString(
            'chown -R www-data:www-data',
            $this->entrypoint,
            'Must chown storage to www-data for PHP-FPM writes'
        );
    }

    public function test_entrypoint_waits_for_postgresql(): void
    {
        $this->assertStringContainsString(
            'pg_isready',
            $this->entrypoint,
            'Must wait for PostgreSQL before running migrations'
        );
    }

    public function test_entrypoint_has_db_wait_timeout(): void
    {
        $this->assertStringContainsString(
            'MAX_WAIT=30',
            $this->entrypoint,
            'DB wait must have a timeout to prevent infinite hang'
        );
    }

    public function test_entrypoint_runs_migrations(): void
    {
        $this->assertStringContainsString(
            'php database/migrate.php',
            $this->entrypoint,
            'Must run database migrations on boot'
        );
    }

    public function test_entrypoint_runs_composer_install_if_needed(): void
    {
        $this->assertStringContainsString(
            'composer install',
            $this->entrypoint,
            'Must run composer install when vendor/ is missing'
        );
    }

    // ═══════════════════════════════════════════════════════════
    //  CRON JOBS
    // ═══════════════════════════════════════════════════════════

    public function test_entrypoint_registers_crawler_cron(): void
    {
        $this->assertStringContainsString(
            'cron/crawl.php',
            $this->entrypoint,
            'Must register crawler cron job'
        );
        $this->assertStringContainsString(
            '*/5 * * * *',
            $this->entrypoint,
            'Crawler must run every 5 minutes'
        );
    }

    public function test_entrypoint_registers_scheduler_cron(): void
    {
        $this->assertStringContainsString(
            'schedule.php',
            $this->entrypoint,
            'Must register article scheduler cron job'
        );
        $this->assertStringContainsString(
            '* * * * *',
            $this->entrypoint,
            'Scheduler must run every minute for timely publishing'
        );
    }

    public function test_entrypoint_registers_email_queue_cron(): void
    {
        $this->assertStringContainsString(
            'process-queue.php',
            $this->entrypoint,
            'Must register email queue processor cron job'
        );
        $this->assertStringContainsString(
            '*/2 * * * *',
            $this->entrypoint,
            'Email queue must run every 2 minutes'
        );
    }

    public function test_entrypoint_registers_log_rotation(): void
    {
        $this->assertStringContainsString(
            'truncate -s 0',
            $this->entrypoint,
            'Must have log rotation to prevent disk fill'
        );
    }

    public function test_entrypoint_starts_crond(): void
    {
        $this->assertStringContainsString(
            'crond -b',
            $this->entrypoint,
            'Must start crond in background'
        );
    }

    // ═══════════════════════════════════════════════════════════
    //  PID 1 — PHP-FPM
    // ═══════════════════════════════════════════════════════════

    public function test_entrypoint_execs_php_fpm(): void
    {
        $this->assertStringContainsString(
            'exec php-fpm',
            $this->entrypoint,
            'Must exec php-fpm as PID 1 for signal handling'
        );
    }

    public function test_entrypoint_php_fpm_is_last_command(): void
    {
        $lines = array_filter(
            array_map('trim', explode("\n", $this->entrypoint)),
            fn($l) => $l !== '' && !str_starts_with($l, '#')
        );
        $last = end($lines);
        $this->assertSame(
            'exec php-fpm',
            $last,
            'exec php-fpm must be the very last executable line'
        );
    }

    // ═══════════════════════════════════════════════════════════
    //  CRON LOG PATHS
    // ═══════════════════════════════════════════════════════════

    public function test_cron_logs_go_to_storage_logs(): void
    {
        $this->assertStringContainsString('storage/logs/crawler.log', $this->entrypoint);
        $this->assertStringContainsString('storage/logs/schedule.log', $this->entrypoint);
        $this->assertStringContainsString('storage/logs/queue.log', $this->entrypoint);
    }
}
