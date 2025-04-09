<?php

namespace App\Jobs;

use App\Models\Medium;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class RestoreVideo extends RestoreMedium
{
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        if (! file_exists($this->path)) {
            Log::error("File {$this->path} does not exist...");

            return;
        }

        $meta = $this->getMeta($this->path);
        $hash = md5_file($this->path);
        $path = Str::lower(sprintf('%s.%s', $hash, pathinfo($this->path, PATHINFO_EXTENSION)));

        if (! copy($this->path, $this->disk->storage()->path($path))) {
            Log::error("Failed to copy file {$this->path} to {$path}...");

            return;
        }

        Medium::updateOrCreate([
            'disk_id' => $this->disk->getKey(),
            'name' => data_get($meta, 'filename', basename($this->path)),
            'hash' => $hash,
            'path' => $path,
        ], [
            'meta' => $meta,
            'created_at' => Carbon::createFromTimestamp(data_get($meta, 'taken_at') ?? filemtime($this->path)),
            'updated_at' => Carbon::createFromTimestamp(data_get($meta, 'taken_at') ?? filemtime($this->path)),
        ]);
    }

    public function getMeta(string $path): array
    {
        $ffprobe = $this->getMetaFromFFProbe($path);

        $meta = [];
        foreach ($ffprobe['streams'] as $stream) {
            if ($stream['codec_type'] === 'video') {
                $meta['width'] = $stream['width'];
                $meta['height'] = $stream['height'];
                if (isset($stream['side_data_list'])) {
                    foreach ($stream['side_data_list'] as $item) {
                        if (isset($item['rotation'])) {
                            $meta['orientation'] = intval($item['rotation']);
                            break;
                        }
                    }
                }

                if (isset($stream['tags']['rotate'])) {
                    $meta['orientation'] = intval($stream['tags']['rotate']);
                }
                break;
            }
        }

        $location = rtrim(data_get($ffprobe, 'format.tags.location'), '/');

        if (preg_match('/^([+-][0-9.]+)([+-][0-9.]+)$/', $location, $matches)) {
            $meta['gps'] = [
                'lat' => floatval($matches[1]),
                'lng' => floatval($matches[2]),
            ];
        }

        return $meta;
    }

    public function getMetaFromFFProbe(string $path): array
    {
        $command = sprintf('ffprobe -v quiet -print_format json -show_format -show_streams "%s"', $path);
        $result = Process::command($command)->run();

        if (! $result->successful()) {
            Log::error("Failed to get metadata for {$path} using ffprobe...");

            return [];
        }

        return json_decode($result->output(), true);
    }
}
