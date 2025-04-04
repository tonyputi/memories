<?php

namespace App\Console\Commands;

use App\Models\Medium;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;

class MediaImport extends Command implements PromptsForMissingInput
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:media-import {user} {file} {--d|disk=local}';

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
            'user' => fn() => search(
                label: 'Which user should receive the imported media?',
                placeholder: 'Search for a user...',
                options: fn ($v) => strlen($v) > 0 
                    ? User::where('name', 'like', "%{$v}%")->pluck('name', 'id')->all() 
                    : [],
            ),
            'disk' => fn() => select(
                label: 'Which disk should the media be imported to?',
                options: array_keys(config('filesystems.disks')),
            ),
            'file' => fn() => search(
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
        $disk = $this->option('disk');
        $file = $this->argument('file');

        $storage = Storage::disk($disk);

        if (!file_exists($file)) {
            $this->error('File does not exist...');
            return;
        }
    
        if (mime_content_type($file) !== 'application/zip') {
            $this->error('File is not a zip file...');
            return;
        }

        $name = basename($file);
    
        $this->info("Importing media from {$name}...");

        // $this->extractZip($user->getKey(), $disk, $file);

        [$metas, $files] = collect($storage->allFiles())
            ->partition(fn ($file) => str_ends_with($file, '.json'));

        $i = 0;
        foreach ($files as $file) {
            $this->info("Importing media from {$file}...");

            if ($meta = $metas->first(fn ($meta) => $meta === "{$file}.json")) {
                $this->info("Importing meta from {$meta}...");
                $meta = json_decode($storage->get($meta), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->error('Meta is not a valid JSON file...');
                    $meta = [];
                }
            }

            Medium::updateOrCreate([
                'user_id' => $user->getKey(),
                'disk' => $disk,
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
        
            $i++;
        }

        // dd($i, count($metas), count($media), count($files));
    
        $this->info('Syncing media...');
    }

    protected function extractZip($user_id, $disk, $file)
    {
        $zip = new ZipArchive();
        $zip->open($file);
        $zip->extractTo($disk->path($user_id));
        $zip->close();
    }
}
