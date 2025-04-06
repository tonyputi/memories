<?php

namespace App\Jobs;

use App\Models\Disk;
use App\Models\Medium;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RestoreImage extends RestoreMedium
{
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        $disk = Disk::findOrFail($this->disk_id);

        $uploads = Storage::disk('uploads');

        if (! $uploads->exists($this->path)) {
            Log::error("File {$this->path} does not exist...");

            return;
        }

        $meta = [];

        if ($uploads->exists("{$this->path}.json")) {
            $meta = json_decode($uploads->get("{$this->path}.json"), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $meta = [];
            }
        }

        $name = data_get($meta, 'title', basename($this->path));
        $createdAt = $this->getCreatedAt($meta, $uploads->lastModified($this->path));
        $updatedAt = $this->getUpdatedAt($meta, $uploads->lastModified($this->path));
        $hash = $this->getHash($uploads->path($this->path));
        $path = Str::lower(sprintf('%s.%s', $hash, pathinfo($this->path, PATHINFO_EXTENSION)));

        if (! copy($uploads->path($this->path), $disk->storage()->path($path))) {
            Log::error("Failed to copy file {$this->path} to {$path}...");

            return;
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
    }

    public function getCreatedAt(array $meta, $default): Carbon
    {
        return Carbon::createFromTimestamp(data_get($meta, 'photoTakenTime.timestamp', data_get($meta, 'creationTime.timestamp', $default)));
    }

    public function getUpdatedAt(array $meta, $default): Carbon
    {
        return Carbon::createFromTimestamp(data_get($meta, 'photoTakenTime.timestamp', data_get($meta, 'creationTime.timestamp', $default)));
    }

    public function getHash(string $path): string
    {
        return md5_file($path);
    }
}
