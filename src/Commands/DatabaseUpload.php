<?php

namespace Grafite\Database\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class DatabaseUpload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:upload {filename} {backup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Upload a db dump to a longer term storage.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

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
            $this->info("You need to create a backup filesystem to upload to.");
        }

        Storage::disk('backup')->put("backups/{$filename}.sql", File::get($backupPath), 'private');

        $this->info('Your db dump has been uploaded.');
    }
}
