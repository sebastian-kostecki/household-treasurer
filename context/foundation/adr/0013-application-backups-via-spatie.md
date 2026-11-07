# Application backups via Spatie Laravel Backup

Production keeps full-instance backups through `spatie/laravel-backup`: PostgreSQL plus `storage/app` (excluding backup working directories). Archives land on the local `backups` disk for seven days and, when enabled, on Hetzner Object Storage via the `backups-offsite` S3-compatible disk. Off-site retention beyond seven days is enforced with bucket lifecycle rules (target: thirty days), not Spatie cleanup, because cleanup strategy is shared across destinations.

Scheduled tasks run only in `production` at 03:00/03:30/04:00 Europe/Warsaw. Failure notifications go to `BACKUP_NOTIFICATION_MAIL`. Restore is a separate `backup:restore` Artisan command that replays the SQL dump and copies archived storage files back into `storage/app`. Per-user JSON snapshots (`user:backup` / `user:restore`, ADR-0008) stay for domain-data migration between users or environments.
