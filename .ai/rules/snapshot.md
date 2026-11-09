---
paths:
    - 'app/Actions/BackupUserData.php'
    - 'app/Actions/RestoreUserData.php'
    - 'tests/Feature/UserSnapshotCommandTest.php'
    - 'tests/Feature/ApplicationBackupRestoreTest.php'
---

# Snapshot

## Every snapshot version ever written must still restore

`BackupUserData::SNAPSHOT_VERSION` is the version `user:backup` writes. `RestoreUserData` lists the accepted versions explicitly and uses the constant for the newest one. When you bump the constant: add the previous number to every version list in `RestoreUserData`, give new fields defaults for older snapshots (for added fields; a changed shape is converted, see below), and add a restore test for each older version in `tests/Feature/UserSnapshotCommandTest.php`, so existing backups keep loading (FR-014).

## An older snapshot is converted, not just defaulted

When the model changes shape (S-18: Budget Lines with a list Category), restoring a snapshot of version 1 to 5 goes through steps (a)–(f) of ADR-0063 and uses the same rules as the data migration, so a restored snapshot equals what the migration would make of the same data. The test in `tests/Feature/UserSnapshotCommandTest.php` uses a v5 fixture with four cases: the same name in two Budgets, the same name key twice in one Budget („Rozrywka (2)”), a Category present only in an old year (archived), and names differing in letter case or spaces. S-18 writes that test.
