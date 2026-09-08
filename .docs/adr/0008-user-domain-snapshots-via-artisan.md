# User domain snapshots via Artisan

Household Treasurer stores production backups as PostgreSQL dumps off-site. For local admin work we also need a reversible export of one user's domain data without dumping the whole database. Artisan commands `user:backup` and `user:restore` write and read versioned JSON snapshots under `storage/app/private/user-snapshots/`. Restore wipes the target user's domain data first, then inserts records with new primary keys and remapped foreign keys so snapshots can be moved between existing users safely.
