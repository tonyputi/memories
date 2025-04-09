<?php

namespace App\Jobs;

use App\Models\Disk;
use Exception;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
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
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 1800;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $path, public Disk $disk, public bool $delete_after_restore = true)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $notification = Notification::make();
        $storage = Storage::disk('uploads');
        $disk = $this->disk;

        $contentType = mime_content_type($this->path);

        try {
            if ($contentType !== 'application/zip') {
                Log::error("Invalid archive {$this->path}...");
                $notification->title('Invalid archive...')->danger()->sendToDatabase($disk->user);

                return;
            }

            if (! $this->extractZip($this->path, $storage)) {
                Log::error("Failed to extract archive {$this->path}...");
                $notification->title('Failed to extract archive...')->danger()->sendToDatabase($disk->user);

                return;
            }

            if ($this->delete_after_restore && ! unlink($this->path)) {
                Log::error("Failed to delete {$this->path} archive...");
                $notification->title('Failed to delete archive...')->danger()->sendToDatabase($disk->user);

                return;
            }

            $jobs = $this->availableFiles($storage, $disk->getKey())
                ->map(fn ($file) => match ($storage->mimeType($file)) {
                    'image/jpeg' => new RestoreImage($file, $disk),
                    'video/mp4' => new RestoreVideo($file, $disk),
                    default => null,
                })
                ->filter();

            Bus::batch($jobs)
                ->name('Restore Media Batch')
                ->allowFailures()
                ->onConnection('database')
                ->before(function (Batch $batch) use ($disk) {
                    Notification::make()
                        ->title('Restore started...')
                        ->success()
                        ->sendToDatabase($disk->user);
                })->progress(function (Batch $batch) {
                    Log::info("Batch {$batch->progress()} progress...");
                })->then(function (Batch $batch) use ($disk) {
                    Notification::make()
                        ->title('Restore completed...')
                        ->body(__(':count of :total media restored successfully...', [
                            'count' => $batch->successfulJobsCount,
                            'total' => $batch->totalJobs,
                        ]))
                        ->actions([
                            Action::make('view')
                                ->button()
                                ->url(route('filament.admin.resources.media.index')),
                        ])
                        ->success()
                        ->sendToDatabase($disk->user);
                })->catch(function (Batch $batch, Throwable $e) use ($disk) {
                    Notification::make()
                        ->title('Restore failed...')
                        ->body($e->getMessage())
                        ->danger()
                        ->sendToDatabase($disk->user);
                    Log::error('Restore failed...');
                })
                ->finally(function (Batch $batch) use ($disk) {
                    $storage = Storage::disk('uploads');
                    if (! $storage->deleteDirectory($disk->getKey())) {
                        Log::error('Failed to delete temporary storage...');
                    }

                    $body = __(':count of :total media restored successfully...', [
                        'count' => $batch->successfulJobsCount,
                        'total' => $batch->totalJobs,
                    ]);

                    Notification::make()
                        ->title('Restore completed...')
                        ->body($body)
                        ->actions([
                            Action::make('view')
                                ->button()
                                ->url(route('filament.admin.resources.media.index')),
                        ])
                        ->success()
                        ->sendToDatabase($disk->user);
                })
                ->dispatch();
        } catch (Exception $e) {
            Log::error('Failed to process uploaded archive...', ['error' => $e->getMessage()]);
            $notification->title('Failed to process uploaded archive...')->danger()->sendToDatabase($this->disk->user);
            // if (! $storage->deleteDirectory($this->disk->getKey())) {
            //     Log::error('Failed to delete temporary storage...');
            // }
        }
    }

    // TODO: Move this into a helper method
    protected function extractZip(string $path, Filesystem $storage): bool
    {
        try {
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                Log::error('Failed to open ZIP file');

                return false;
            }

            if (! $storage->exists($this->disk->getKey())) {
                $storage->makeDirectory($this->disk->getKey());
            }

            // Extract files one by one
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $filename = $stat['name'];

                // Skip directories
                if (substr($filename, -1) === '/') {
                    continue;
                }

                // Create necessary directories
                $dirname = dirname($filename);
                if ($dirname !== '.') {
                    $fullDirPath = "{$this->disk->getKey()}/{$dirname}";
                    if (! $storage->exists($fullDirPath)) {
                        $storage->makeDirectory($fullDirPath);
                    }
                }

                // Extract the file using getStream to handle memory better
                $stream = $zip->getStream($filename);
                if ($stream) {
                    $targetPath = "{$this->disk->getKey()}/{$filename}";
                    $targetHandle = fopen($storage->path($targetPath), 'wb');

                    if ($targetHandle) {
                        // Read and write in chunks of 1MB
                        while (! feof($stream)) {
                            $chunk = fread($stream, 1024 * 1024);
                            fwrite($targetHandle, $chunk);

                            // Free memory after each chunk
                            unset($chunk);
                            if (function_exists('gc_collect_cycles')) {
                                gc_collect_cycles();
                            }
                        }

                        fclose($targetHandle);
                    }

                    fclose($stream);
                }
            }

            $zip->close();

            return true;
        } catch (Exception $e) {
            Log::error('ZIP extraction failed: '.$e->getMessage());
            if (isset($zip)) {
                $zip->close();
            }

            return false;
        }
    }

    protected function availableFiles(Filesystem $storage, string $path): Collection
    {
        // TODO: files non deve contenere file inderiderati come .gitignore o .DS_Store etc
        [$json, $files] = collect($storage->allFiles($path))
            ->reject(fn ($file) => Str::startsWith($file, '.'))
            ->partition(fn ($file) => Str::endsWith($file, '.json'));

        return $files;
    }
}
