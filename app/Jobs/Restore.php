<?php

namespace App\Jobs;

use App\Models\Disk;
use App\Models\Medium;
use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class Restore implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $path, public string $disk_id)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $disk = Disk::findOrFail($this->disk_id);
        $tempDirectory = dirname($this->path);

        try {
            $tempStorage = Storage::disk('uploads');

            if ($tempStorage->mimeType($this->path) !== 'application/zip') {
                Log::error('Invalid archive...');

                return;
            }

            if (! $this->extractZip($tempStorage, $this->path)) {
                Log::error('Failed to extract archive...');

                return;
            }

            if (! $tempStorage->delete($this->path)) {
                Log::error('Failed to delete temporary storage...');

                return;
            }

            // TODO: files non deve contenere file inderiderati come .gitignore o .DS_Store etc
            [$metas, $files] = collect($tempStorage->allFiles())
                ->partition(fn ($file) => Str::endsWith($file, '.json'));

            $storage = $disk->storage();

            Log::info('Processing files...');

            foreach ($files as $file) {
                $meta = [];

                if ($metaFile = $metas->first(fn ($meta) => $meta === "{$file}.json")) {
                    $meta = json_decode($tempStorage->get($metaFile), true);
                }

                $name = basename($file);

                $extension = pathinfo($file, PATHINFO_EXTENSION);
                $hash = md5_file($tempStorage->path($file));
                $path = Str::lower("$hash.$extension");

                $createdAt = $tempStorage->lastModified($file);
                $updatedAt = $tempStorage->lastModified($file);

                if (! copy($tempStorage->path($file), $storage->path($path))) {
                    Log::error("Failed to copy file {$file} to {$path}...");

                    continue;
                }

                Medium::updateOrCreate([
                    'user_id' => $disk->user_id,
                    'disk_id' => $disk->getKey(),
                    'name' => $name,
                    'hash' => $hash,
                    'path' => $path,
                ], [
                    'meta' => $meta ?? [],
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ]);

                Log::info("File {$file} processed...");
            }

            if (! $tempStorage->deleteDirectory($tempDirectory)) {
                Log::error('Failed to delete temporary storage...');
            }
        } catch (Exception $e) {
            Log::error('Failed to process uploaded archive...');
            if (! $tempStorage->deleteDirectory($tempDirectory)) {
                Log::error('Failed to delete temporary storage...');
            }
        }
    }

    protected function extractZip(Filesystem $storage, string $path): bool
    {
        $zip = new ZipArchive;
        $zip->open($storage->path($path));
        $zip->extractTo($storage->path(dirname($path)));
        $zip->close();

        return true;
    }
}
