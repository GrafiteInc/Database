<?php

namespace Grafite\Database\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class DatabaseDownload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:download {filename} {backup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Upload a db dump to a longer term storage.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $backupPath = $this->argument('backup');
        $filename = $this->argument('filename');

        if (is_null(config('filesystems.disks.backup'))) {
            $this->warning("You need to create a backup filesystem to download from.");
        }

        $backupStoragePath = config('backup.path', base_path('database/backups'));
        $dbDumpContents = Storage::disk('backup')->get("backups/{$filename}");
        File::put("{$backupStoragePath}/{$filename}", $dbDumpContents);

        $this->info('Your db dump has been downloaded.');
    }
}
