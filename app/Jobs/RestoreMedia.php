<?php

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class RestoreMedia implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $path, public string $disk_id, public bool $delete_after_restore = true)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $storage = Storage::disk('uploads');
            $disk_id = $this->disk_id;

            if ($storage->mimeType($this->path) !== 'application/zip') {
                Log::error('Invalid archive...');

                return;
            }

            if (! $this->extractZip($storage, $this->path)) {
                Log::error('Failed to extract archive...');

                return;
            }

            if ($this->delete_after_restore && ! $storage->delete($this->path)) {
                Log::error("Failed to delete {$this->path} archive...");

                return;
            }

            $jobs = $this->availableFiles($storage, $disk_id)
                ->map(fn ($file) => new RestoreMedium($file, $disk_id));

            Bus::batch($jobs)
                ->name('Restore Media Batch')
                ->allowFailures()
                ->onConnection('database')
                ->before(function (Batch $batch) {
                    Log::info('Batch created...');
                })->progress(function (Batch $batch) {
                    Log::info('Batch progress...');
                })->then(function (Batch $batch) {
                    Log::info('Batch completed...');
                })->catch(function (Batch $batch, Throwable $e) {
                    Log::error('Batch failed...');
                })->finally(function (Batch $batch) use ($disk_id) {
                    $storage = Storage::disk('uploads');
                    if (! $storage->deleteDirectory($disk_id)) {
                        Log::error('Failed to delete temporary storage...');
                    }
                })
                ->dispatch();
        } catch (Exception $e) {
            Log::error('Failed to process uploaded archive...', ['error' => $e->getMessage()]);
            if (! $storage->deleteDirectory($disk_id)) {
                Log::error('Failed to delete temporary storage...');
            }
        }
    }

    protected function extractZip(Filesystem $storage, string $path): bool
    {
        $zip = new ZipArchive;
        $zip->open($storage->path($path));
        // We create the same directory as the disk
        $zip->extractTo($storage->path($this->disk_id));
        $zip->close();

        return true;
    }

    protected function availableFiles(Filesystem $storage, string $disk_id): Collection
    {
        // TODO: files non deve contenere file inderiderati come .gitignore o .DS_Store etc
        [$meta, $files] = collect($storage->allFiles($disk_id))
            ->partition(fn ($file) => Str::endsWith($file, '.json'));

        return $files;
    }
}
