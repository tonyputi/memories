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
    public function __construct(public string $path, public string $disk_id, public bool $delete_after_restore = true)
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
        $disk_id = $this->disk_id;
        $disk = Disk::find($disk_id);

        try {
            if ($storage->mimeType($this->path) !== 'application/zip') {
                Log::error("Invalid archive {$this->path}...");
                $notification->title('Invalid archive...')->danger()->sendToDatabase($disk->user);

                return;
            }

            if (! $this->extractZip($storage, $this->path)) {
                Log::error("Failed to extract archive {$this->path}...");
                $notification->title('Failed to extract archive...')->danger()->sendToDatabase($disk->user);

                return;
            }

            if ($this->delete_after_restore && ! $storage->delete($this->path)) {
                Log::error("Failed to delete {$this->path} archive...");
                $notification->title('Failed to delete archive...')->danger()->sendToDatabase($disk->user);

                return;
            }

            $jobs = $this->availableFiles($storage, $disk_id)
                ->map(fn ($file) => match ($storage->mimeType($file)) {
                    'image/jpeg' => new RestoreImage($file, $this->disk_id),
                    'video/mp4' => new RestoreVideo($file, $this->disk_id),
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
                })->catch(function (Batch $batch, Throwable $e) use ($disk) {
                    Notification::make()
                        ->title('Restore failed...')
                        ->body($e->getMessage())
                        ->danger()
                        ->sendToDatabase($disk->user);
                    Log::error('Restore failed...');
                })->finally(function (Batch $batch) use ($disk_id, $disk) {
                    $storage = Storage::disk('uploads');
                    if (! $storage->deleteDirectory($disk_id)) {
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
            $notification->title('Failed to process uploaded archive...')->danger()->sendToDatabase($disk->user);
            if (! $storage->deleteDirectory($disk_id)) {
                Log::error('Failed to delete temporary storage...');
            }
        }
    }

    protected function extractZip(Filesystem $storage, string $path): bool
    {
        try {
            $zip = new ZipArchive;
            if ($zip->open($storage->path($path)) !== true) {
                Log::error('Failed to open ZIP file');

                return false;
            }

            if (! $storage->exists($this->disk_id)) {
                $storage->makeDirectory($this->disk_id);
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
                    $fullDirPath = "{$this->disk_id}/{$dirname}";
                    if (! $storage->exists($fullDirPath)) {
                        $storage->makeDirectory($fullDirPath);
                    }
                }

                // Extract the file using getStream to handle memory better
                $stream = $zip->getStream($filename);
                if ($stream) {
                    $targetPath = "{$this->disk_id}/{$filename}";
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

    protected function availableFiles(Filesystem $storage, string $disk_id): Collection
    {
        // TODO: files non deve contenere file inderiderati come .gitignore o .DS_Store etc
        [$json, $files] = collect($storage->allFiles($disk_id))
            ->reject(fn ($file) => Str::startsWith($file, '.'))
            ->partition(fn ($file) => Str::endsWith($file, '.json'));

        return $files;
    }
}
