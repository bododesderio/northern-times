"""
Database backup management command.

Creates a compressed PostgreSQL dump, verifies integrity, records it in DbBackup,
cleans up old backups (30-day daily, 90-day monthly), and optionally uploads to S3.

Usage:
    python manage.py db_backup              # Local backup only
    python manage.py db_backup --upload-s3  # Local + S3 upload
"""
import gzip
import logging
import os
import subprocess
from datetime import datetime, timedelta
from pathlib import Path

from django.conf import settings
from django.core.management.base import BaseCommand
from django.utils import timezone

logger = logging.getLogger(__name__)

BACKUP_DIR = Path(settings.BASE_DIR) / 'storage' / 'backups'
DAILY_RETENTION_DAYS = 30
MONTHLY_RETENTION_DAYS = 90


class Command(BaseCommand):
    help = 'Create a verified, compressed database backup with optional S3 upload'

    def add_arguments(self, parser):
        parser.add_argument(
            '--upload-s3',
            action='store_true',
            help='Upload backup to S3 after creation',
        )

    def handle(self, *args, **options):
        from apps.system.models import DbBackup

        BACKUP_DIR.mkdir(parents=True, exist_ok=True)

        timestamp = datetime.now().strftime('%Y-%m-%d_%H%M%S')
        sql_file = BACKUP_DIR / f'nt_backup_{timestamp}.sql'
        gz_file = BACKUP_DIR / f'nt_backup_{timestamp}.sql.gz'

        db = settings.DATABASES['default']

        # ── 1. pg_dump ──────────────────────────────────────────────
        self.stdout.write('Creating database dump...')
        env = os.environ.copy()
        env['PGPASSWORD'] = db['PASSWORD']

        result = subprocess.run(
            [
                'pg_dump',
                '-h', db['HOST'],
                '-p', str(db['PORT']),
                '-U', db['USER'],
                '-d', db['NAME'],
                '--no-owner',
                '--no-privileges',
                '-f', str(sql_file),
            ],
            env=env,
            capture_output=True,
            text=True,
        )

        if result.returncode != 0:
            self.stderr.write(f'pg_dump failed: {result.stderr}')
            raise SystemExit(1)

        # ── 2. Compress ─────────────────────────────────────────────
        self.stdout.write('Compressing...')
        with open(sql_file, 'rb') as f_in:
            with gzip.open(gz_file, 'wb', compresslevel=6) as f_out:
                while chunk := f_in.read(8192):
                    f_out.write(chunk)
        sql_file.unlink()

        file_size = gz_file.stat().st_size
        self.stdout.write(f'Backup: {gz_file.name} ({file_size / 1024 / 1024:.1f} MB)')

        # ── 3. Verify integrity ──────────────────────────────────────
        self.stdout.write('Verifying backup integrity...')
        verify = subprocess.run(
            [
                'pg_restore', '--list', str(gz_file),
            ],
            env=env,
            capture_output=True,
            text=True,
        )
        # pg_restore --list works on custom format; for plain SQL gzip we
        # verify by decompressing a portion and checking for valid SQL
        if verify.returncode != 0:
            # Fallback verification: decompress and check header
            try:
                with gzip.open(gz_file, 'rt') as f:
                    header = f.read(256)
                if '--' not in header and 'PostgreSQL' not in header:
                    self.stderr.write('WARNING: Backup may be corrupt — no SQL header found')
                else:
                    self.stdout.write('Backup verified (SQL header check passed)')
            except Exception as e:
                self.stderr.write(f'WARNING: Could not verify backup: {e}')
        else:
            self.stdout.write('Backup verified (pg_restore --list passed)')

        # ── 4. Record in database ────────────────────────────────────
        DbBackup.objects.create(
            filename=gz_file.name,
            size=file_size,
        )
        self.stdout.write(self.style.SUCCESS(f'Recorded backup: {gz_file.name}'))

        # ── 5. Upload to S3 (optional) ──────────────────────────────
        if options['upload_s3']:
            self._upload_to_s3(gz_file)

        # ── 6. Clean old backups ─────────────────────────────────────
        self._cleanup_old_backups()

        self.stdout.write(self.style.SUCCESS('Backup complete.'))

    def _upload_to_s3(self, filepath):
        """Upload backup file to S3-compatible storage."""
        try:
            import boto3
        except ImportError:
            self.stderr.write('boto3 not installed — skipping S3 upload')
            return

        endpoint = os.environ.get('S3_ENDPOINT')
        bucket = os.environ.get('S3_BUCKET', 'northern-times')
        key_id = os.environ.get('S3_KEY')
        secret = os.environ.get('S3_SECRET')
        region = os.environ.get('S3_REGION', 'auto')

        if not all([endpoint, key_id, secret]):
            self.stderr.write('S3 credentials not configured — skipping upload')
            return

        self.stdout.write(f'Uploading to S3: {bucket}/backups/{filepath.name}...')
        try:
            s3 = boto3.client(
                's3',
                endpoint_url=endpoint,
                aws_access_key_id=key_id,
                aws_secret_access_key=secret,
                region_name=region,
            )
            s3.upload_file(
                str(filepath),
                bucket,
                f'backups/{filepath.name}',
            )
            self.stdout.write(self.style.SUCCESS('S3 upload complete'))
        except Exception as e:
            self.stderr.write(f'S3 upload failed: {e}')
            logger.error(f'Backup S3 upload failed: {e}')

    def _cleanup_old_backups(self):
        """Remove old backups: keep 30 days of dailies, 90 days of monthlies (1st of month)."""
        from apps.system.models import DbBackup

        now = timezone.now()
        daily_cutoff = now - timedelta(days=DAILY_RETENTION_DAYS)
        monthly_cutoff = now - timedelta(days=MONTHLY_RETENTION_DAYS)

        old_records = DbBackup.objects.filter(created_at__lt=daily_cutoff)
        removed = 0

        for record in old_records:
            # Keep monthly backups (created on 1st of month) for 90 days
            if record.created_at.day == 1 and record.created_at > monthly_cutoff:
                continue

            # Delete file from disk
            backup_path = BACKUP_DIR / record.filename
            if backup_path.exists():
                backup_path.unlink()

            record.delete()
            removed += 1

        if removed:
            self.stdout.write(f'Cleaned up {removed} old backup(s)')
