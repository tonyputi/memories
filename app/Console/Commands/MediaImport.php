<?php

namespace App\Console\Commands;

use App\Models\Disk;
use App\Models\Medium;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        $storage = $disk->storage();

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

        $tempStorage = $this->extractZip($user, $file);

        [$metas, $files] = collect($tempStorage->allFiles())
            ->partition(fn ($file) => str_ends_with($file, '.json'));

        $this->info("Importing {$files->count()} files...");

        $bar = $this->output->createProgressBar($files->count());

        foreach ($files as $file) {
            if ($meta = $metas->first(fn ($meta) => $meta === "{$file}.json")) {
                $meta = json_decode($tempStorage->get($meta), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->error('Meta is not a valid JSON file...');
                    $meta = [];
                }
            }

            $name = basename($file);

            $extension = pathinfo($file, PATHINFO_EXTENSION);
            $uuid = Str::lower(Str::random(32).'.'.$extension);
            $hash = hash_file('sha256', $tempStorage->path($file));
            $createdAt = $tempStorage->lastModified($file);
            $updatedAt = $tempStorage->lastModified($file);

            if (! copy($tempStorage->path($file), $storage->path($uuid))) {
                $this->error("Failed to copy file {$file} to {$uuid}...");

                continue;
            }

            Medium::updateOrCreate([
                'user_id' => $user->getKey(),
                'disk_id' => $disk->getKey(),
                'name' => $name,
                'path' => $uuid,
                'hash' => $hash,
            ], [
                'meta' => $meta ?? [],
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);

            $bar->advance();
        }

        $bar->finish();

        if ($tempStorage->deleteDirectory($user->getKey())) {
            $this->info('Temporary storage deleted successfully!');
        } else {
            $this->error('Failed to delete temporary storage...');
        }

        $this->info('Media imported successfully!');
    }

    protected function extractZip($user, $file): Filesystem
    {
        $storage = Storage::disk('uploads');

        $zip = new ZipArchive;
        $zip->open($file);
        $zip->extractTo($storage->path($user->getKey()));
        $zip->close();

        return $storage;
    }
}
