<?php

namespace App\Jobs;

use App\Services\Backup\BackupPackageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunFullBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(public string $backupId, public array $actor = [])
    {
    }

    public function handle(BackupPackageService $packages): void
    {
        $packages->create($this->backupId, $this->actor);
    }
}
