<?php

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
    public function __construct(public string $path, public string $disk_id)
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

            if ($storage->mimeType($this->path) !== 'application/zip') {
                Log::error('Invalid archive...');

                return;
            }

            if (! $this->extractZip($storage, $this->path)) {
                Log::error('Failed to extract archive...');

                return;
            }

            if (! $storage->delete($this->path)) {
                Log::error('Failed to delete temporary storage...');

                return;
            }

            // TODO: files non deve contenere file inderiderati come .gitignore o .DS_Store etc
            [$meta, $files] = collect($storage->allFiles())
                ->partition(fn ($file) => Str::endsWith($file, '.json'));

            $jobs = $files->map(fn ($file) => new RestoreMedium($file, $this->disk_id));

            Bus::batch($jobs)
                ->before(function (Batch $batch) {
                    Log::info('Batch created...');
                })->progress(function (Batch $batch) {
                    Log::info('Batch progress...');
                })->then(function (Batch $batch) {
                    Log::info('Batch completed...');
                })->catch(function (Batch $batch, Throwable $e) {
                    Log::error('Batch failed...');
                })->finally(function (Batch $batch) {
                    $storage = Storage::disk('uploads');
                    if (! $storage->deleteDirectory(dirname($this->path))) {
                        Log::error('Failed to delete temporary storage...');
                    }
                })->dispatch();
        } catch (Exception $e) {
            Log::error('Failed to process uploaded archive...', ['error' => $e->getMessage()]);
            if (! $storage->deleteDirectory(dirname($this->path))) {
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
