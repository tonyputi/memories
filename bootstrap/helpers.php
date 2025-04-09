<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

if (! function_exists('memoriesVersion')) {
    /**
     * Get the current Genesis version.
     */
    function memoriesVersion(): string
    {
        return Cache::rememberForever('memories.version', function () {
            $manifest = json_decode(File::get(__DIR__.'/../composer.json'), true);

            return ltrim(data_get($manifest, 'version', 'v2.x'), 'v');
        });
    }
}

if (! function_exists('extractZip')) {
    /**
     * Extract a zip file.
     */
    function extractZip(string $archivePath, string $targetPath): bool
    {
        try {
            $zip = new ZipArchive;
            if ($zip->open($archivePath) !== true) {
                Log::error('Failed to open ZIP file');

                return false;
            }

            if (! file_exists($targetPath)) {
                mkdir($targetPath, 0777, true);
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
                    $fullDirPath = "{$targetPath}/{$dirname}";
                    if (! file_exists($fullDirPath)) {
                        mkdir($fullDirPath, 0777, true);
                    }
                }

                // Extract the file using getStream to handle memory better
                $stream = $zip->getStream($filename);
                if ($stream) {
                    $fullFilePath = "{$targetPath}/{$filename}";
                    $targetHandle = fopen($fullFilePath, 'wb');

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
}
