<?php

namespace App\Console\Commands;

use App\Models\Disk;
use App\Models\Medium;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

use function Laravel\Prompts\search;

class MediaImport extends Command implements PromptsForMissingInput
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:media-import {user} {disk} {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import media from a file';

    /**
     * Prompt for missing input arguments using the returned questions.
     *
     * @return array<string, string>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'user' => fn () => search(
                label: 'Which user should receive the imported media?',
                placeholder: 'Search for a user...',
                options: fn ($v) => strlen($v) > 0
                    ? User::where('name', 'like', "%{$v}%")->pluck('name', 'id')->all()
                    : [],
            ),
            'disk' => fn () => search(
                label: 'Which disk should the media be imported to?',
                placeholder: 'Search for a disk...',
                options: fn ($v) => strlen($v) > 0
                    ? Disk::where('name', 'like', "%{$v}%")->pluck('name', 'id')->all()
                    : [],
            ),
            'file' => fn () => search(
                label: 'Which file should be imported?',
                placeholder: 'Search for a file...',
                options: fn ($v) => strlen($v) > 0
                    ? Storage::disk('uploads')->files()
                    : [],
                transform: fn ($v) => Storage::disk('uploads')->path($v),
            ),
        ];
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $user = User::find($this->argument('user'));
        $disk = Disk::find($this->argument('disk'));
        $file = $this->argument('file');

        $storage = Storage::build($disk->config);

        if (! file_exists($file)) {
            $this->error('File does not exist...');

            return;
        }

        if (mime_content_type($file) !== 'application/zip') {
            $this->error('File is not a zip file...');

            return;
        }

        $name = basename($file);

        $this->info("Importing media from {$name}...");

        $this->extractZip($storage, $file);

        [$metas, $files] = collect($storage->allFiles())
            ->partition(fn ($file) => str_ends_with($file, '.json'));

        $this->info("Importing {$files->count()} files...");

        $bar = $this->output->createProgressBar($files->count());

        foreach ($files as $file) {
            if ($meta = $metas->first(fn ($meta) => $meta === "{$file}.json")) {
                $meta = json_decode($storage->get($meta), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->error('Meta is not a valid JSON file...');
                    $meta = [];
                }
            }

            Medium::updateOrCreate([
                'user_id' => $user->getKey(),
                'disk_id' => $disk->getKey(),
                'name' => basename($file),
                'path' => $file,
                'hash' => hash_file('sha256', $storage->path($file)),
            ], [
                'size' => $storage->size($file),
                'type' => $storage->mimeType($file),
                'meta' => $meta ?? [],
                'created_at' => $storage->lastModified($file),
                'updated_at' => $storage->lastModified($file),
            ]);

            $bar->advance();
        }

        $bar->finish();

        $this->info('Media imported successfully!');
    }

    protected function extractZip($storage, $file)
    {
        $zip = new ZipArchive;
        $zip->open($file);
        $zip->extractTo($storage->path('/'));
        $zip->close();
    }
}
