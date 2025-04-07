<?php

namespace App\Jobs;

use App\Models\Disk;
use App\Models\Medium;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

        $disk = Disk::findOrFail($this->disk_id);

        $uploads = Storage::disk('uploads');

        if (! $uploads->exists($this->path)) {
            Log::error("File {$this->path} does not exist...");

            return;
        }

        $meta = [];
        $hash = md5_file($uploads->path($this->path));
        $path = Str::lower(sprintf('%s.%s', $hash, pathinfo($this->path, PATHINFO_EXTENSION)));

        if (! copy($uploads->path($this->path), $disk->storage()->path($path))) {
            Log::error("Failed to copy file {$this->path} to {$path}...");

            return;
        }

        Medium::updateOrCreate([
            'disk_id' => $disk->getKey(),
            'name' => data_get($meta, 'filename', basename($this->path)),
            'hash' => $hash,
            'path' => $path,
        ], [
            'meta' => $meta,
            'created_at' => Carbon::createFromTimestamp(data_get($meta, 'taken_at', $uploads->lastModified($this->path))),
            'updated_at' => Carbon::createFromTimestamp(data_get($meta, 'taken_at', $uploads->lastModified($this->path))),
        ]);
    }
}
