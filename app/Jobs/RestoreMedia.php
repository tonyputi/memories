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
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300;

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
                Log::error("Invalid archive {$this->path}...");

                return;
            }

            if (! $this->extractZip($storage, $this->path)) {
                Log::error("Failed to extract archive {$this->path}...");

                return;
            }

            if ($this->delete_after_restore && ! $storage->delete($this->path)) {
                Log::error("Failed to delete {$this->path} archive...");

                return;
            }

            $jobs = $this->availableFiles($storage, $disk_id)
                ->map(fn ($file) => new RestoreImage($file, $disk_id));

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
        try {
            $zip = new ZipArchive;
            if ($zip->open($storage->path($path)) !== true) {
                Log::error("Failed to open ZIP file");
                return false;
            }

            $extractPath = $storage->path($this->disk_id);
            
            // Assicuriamoci che la directory base esista
            if (!file_exists($extractPath)) {
                mkdir($extractPath, 0755, true);
            }

            // Estrai file per file
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $filename = $stat['name'];

                // Salta le directory
                if (substr($filename, -1) === '/') {
                    continue;
                }

                // Crea le directory necessarie
                $dirname = dirname($filename);
                if ($dirname !== '.') {
                    $fullDirPath = $extractPath . '/' . $dirname;
                    if (!file_exists($fullDirPath)) {
                        mkdir($fullDirPath, 0755, true);
                    }
                }

                // Estrai il file usando getStream per gestire meglio la memoria
                $stream = $zip->getStream($filename);
                if ($stream) {
                    $targetPath = $extractPath . '/' . $filename;
                    $targetHandle = fopen($targetPath, 'wb');
                    
                    if ($targetHandle) {
                        // Leggi e scrivi in chunks di 1MB
                        while (!feof($stream)) {
                            $chunk = fread($stream, 1024 * 1024);
                            fwrite($targetHandle, $chunk);
                            
                            // Libera la memoria dopo ogni chunk
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
            Log::error("ZIP extraction failed: " . $e->getMessage());
            if (isset($zip)) {
                $zip->close();
            }
            return false;
        }
    }

    protected function availableFiles(Filesystem $storage, string $disk_id): Collection
    {
        // TODO: files non deve contenere file inderiderati come .gitignore o .DS_Store etc
        [$meta, $files] = collect($storage->allFiles($disk_id))
            ->partition(fn ($file) => Str::endsWith($file, '.json'));

        return $files;
    }
}
