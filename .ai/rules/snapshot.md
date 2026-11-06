---
paths:
    - 'app/Actions/BackupUserData.php'
    - 'app/Actions/RestoreUserData.php'
    - 'tests/Feature/UserSnapshotCommandTest.php'
    - 'tests/Feature/ApplicationBackupRestoreTest.php'
---

# Snapshot

## Every snapshot version ever written must still restore

`BackupUserData::SNAPSHOT_VERSION` is the version `user:backup` writes. `RestoreUserData` lists the accepted versions explicitly and uses the constant for the newest one. When you bump the constant: add the previous number to every version list in `RestoreUserData`, give new fields defaults for older snapshots, and add a restore test for each older version in `tests/Feature/UserSnapshotCommandTest.php`, so existing backups keep loading (FR-014).
