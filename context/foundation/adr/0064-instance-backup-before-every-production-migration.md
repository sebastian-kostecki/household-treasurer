# A full instance backup before every production migration, and dumps without an owner

`scripts/deploy.sh` ran `php artisan migrate --force` straight after `up -d`; the only backup was the scheduled `backup:run` at 03:00 Europe/Warsaw (ADR-0013), so a failed migration could be undone only to the state of up to a day earlier. The first irreversible data migration (S-18, the Category list of ADR-0063) makes that gap unacceptable. This ADR complements ADR-0013 and does not change it.

Backup before migration. `scripts/deploy.sh` runs a full `php artisan backup:run --disable-notifications` as the `sail` user after `up -d` and the `storage` permissions fix, and before `migrate --force`. It is the full backup (PostgreSQL dump plus `storage/app`), not `--only-db`: the retention of `config/backup.php` keeps the newest archive of each day after seven days, so a database-only archive made during a deployment would displace the full archive of 03:00 of the same day. It runs on every deployment, not only when migrations are pending, so the rule has no condition to get wrong and the deployment step stays the same whatever the change. A failed backup stops the deployment (`set -euo pipefail`; no `|| true`). The new image makes a dump of the old schema, which is fine for `pg_dump`. `BACKUP_HOST_DIR` is resolved in one order for Compose and for the script: shell, then `.env.prod`, then the default, so the directory the script prepares is the one Compose mounts.

Dump without an owner. `database.connections.pgsql.dump.add_extra_option` is `--no-owner --no-privileges`, so every dump (from deployments and from the nightly schedule) loads under any role. Without it the dump holds `OWNER TO treasurer` (the production role) and `backup:restore`, which runs `psql` with `ON_ERROR_STOP=on`, stops on a development machine where the role is `sail`. A test builds the dumper and checks both options. Archives made before this ADR still hold `OWNER TO treasurer`; loading them locally needs `CREATE ROLE treasurer` first.

Restore. Production is restored with `backup:restore`, as the README section “Wdrożenie i przywracanie” describes, together with reverting the code, because the old schema does not work with the new code. The restore loses everything created after the archive was made, so the copy covers a failure during or right after a deployment. Rolling back after days of work on the new schema is the job of the plan of that migration.

Not a replacement for the snapshots. The instance backup does not replace `user:backup` of every User before a data migration (PRD v3), and restoring it loses the operations added after the deployment, while the snapshot is the way back without that loss. S-18 adds that step, together with the control numbers that stop a deployment on a difference.

We rejected:

- `--only-db` (displaces the full archive of the day, see above, and leaves `storage/app` without a copy at the moment of the change);
- a conditional backup, only when migrations are pending (a condition to maintain and to get wrong, for a saving of a few seconds);
- the `treasurer` role created locally instead of `--no-owner` (every new machine and every restore needs the extra step, and the dump stays tied to a production role name).
