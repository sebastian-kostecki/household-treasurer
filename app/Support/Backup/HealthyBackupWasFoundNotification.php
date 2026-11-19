<?php

namespace App\Support\Backup;

use Carbon\CarbonImmutable;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification as PackageHealthyBackupWasFoundNotification;

/**
 * The weekly "backups are healthy" mail: the heartbeat whose absence tells the user that the
 * scheduler, maintenance mode or mail stopped the backups. `backup:monitor` runs every night,
 * so the mail goes out only on Mondays to stay a signal rather than noise.
 *
 * The class name must stay HealthyBackupWasFoundNotification: the package finds the notification
 * for an event by the short class name in config('backup.notifications.notifications').
 */
class HealthyBackupWasFoundNotification extends PackageHealthyBackupWasFoundNotification
{
    /**
     * @return array<string, string>
     */
    public function via(): array
    {
        if (! CarbonImmutable::now('Europe/Warsaw')->isMonday()) {
            return [];
        }

        return parent::via();
    }
}
