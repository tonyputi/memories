<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class RestoreVideo extends RestoreMedium
{
    public function meta(): array
    {
        $ffprobe = $this->getMetaFromFFProbe($this->path);

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
        $result = Process::command('which ffprobe')->run();
        if (! $result->successful()) {
            Log::warning('ffprobe is not installed, skipping video metadata extraction');
            return [];
        }

        $command = sprintf('ffprobe -v quiet -print_format json -show_format -show_streams "%s"', $path);
        $result = Process::command($command)->run();

        if (! $result->successful()) {
            Log::error("Failed to get metadata for {$path} using ffprobe...");

            return [];
        }

        return json_decode($result->output(), true);
    }
}
